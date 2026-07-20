<?php
/**
 * Módulo Contratos — funções de apoio (template, extração da venda, renderização,
 * numeração e valor por extenso). Arquivo de funções puras: não executa nada ao ser incluído.
 */

require_once __DIR__ . '/helpers.php';

const CONTRATO_STATUS = [
    'emitido'   => 'Emitido',
    'rascunho'  => 'Rascunho',
    'cancelado' => 'Cancelado',
];

/* ---------------- configurações (empresa / testemunhas) ---------------- */

function config_all(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    foreach (db()->query('SELECT chave, valor FROM configuracoes')->fetchAll() as $r) {
        $cache[$r['chave']] = $r['valor'];
    }
    return $cache;
}

function config_get(string $chave, string $default = ''): string
{
    $all = config_all();
    return isset($all[$chave]) && $all[$chave] !== null ? (string) $all[$chave] : $default;
}

function config_set(string $chave, ?string $valor): void
{
    if (DB_DRIVER === 'mysql') {
        $sql = 'INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)';
    } else {
        $sql = 'INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
                ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor';
    }
    db()->prepare($sql)->execute([$chave, $valor]);
}

/* ---------------- numeração automática CTR-ANO-000001 ---------------- */

function contrato_proximo_numero(): string
{
    $ano = date('Y');
    $prefixo = "CTR-$ano-";
    $stmt = db()->prepare(
        "SELECT numero FROM contratos WHERE numero LIKE ? ORDER BY numero DESC LIMIT 1"
    );
    $stmt->execute([$prefixo . '%']);
    $ultimo = $stmt->fetchColumn();
    $seq = 1;
    if ($ultimo) {
        $seq = (int) substr($ultimo, strlen($prefixo)) + 1;
    }
    return $prefixo . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
}

/* ---------------- valor por extenso (reais) ---------------- */

function valor_por_extenso(float $valor): string
{
    $valor = round($valor, 2);
    $inteiro = (int) floor($valor);
    $centavos = (int) round(($valor - $inteiro) * 100);

    $texto = $inteiro === 0 ? 'zero real' : numero_extenso($inteiro) . ' ' . ($inteiro === 1 ? 'real' : 'reais');
    if ($centavos > 0) {
        $texto .= ' e ' . numero_extenso($centavos) . ' ' . ($centavos === 1 ? 'centavo' : 'centavos');
    }
    return $texto;
}

function numero_extenso(int $n): string
{
    if ($n === 0) {
        return 'zero';
    }
    if ($n === 100) {
        return 'cem';
    }

    $unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
        'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    $dezenas = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
        'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    $partes = [];

    $bilhoes = intdiv($n, 1000000000);
    $n %= 1000000000;
    if ($bilhoes > 0) {
        $partes[] = numero_extenso($bilhoes) . ($bilhoes === 1 ? ' bilhão' : ' bilhões');
    }

    $milhoes = intdiv($n, 1000000);
    $n %= 1000000;
    if ($milhoes > 0) {
        $partes[] = numero_extenso($milhoes) . ($milhoes === 1 ? ' milhão' : ' milhões');
    }

    $milhares = intdiv($n, 1000);
    $n %= 1000;
    if ($milhares > 0) {
        $partes[] = ($milhares === 1 ? 'mil' : numero_extenso($milhares) . ' mil');
    }

    if ($n > 0) {
        $partes[] = grupo_centena($n, $centenas, $dezenas, $unidades);
    }

    // junta com "e" conforme a norma (simplificado, mas natural em contratos)
    return implode(' e ', $partes);
}

function grupo_centena(int $n, array $centenas, array $dezenas, array $unidades): string
{
    if ($n === 100) {
        return 'cem';
    }
    $c = intdiv($n, 100);
    $resto = $n % 100;
    $out = [];
    if ($c > 0) {
        $out[] = $centenas[$c];
    }
    if ($resto > 0) {
        if ($resto < 20) {
            $out[] = $unidades[$resto];
        } else {
            $d = intdiv($resto, 10);
            $u = $resto % 10;
            $out[] = $u > 0 ? $dezenas[$d] . ' e ' . $unidades[$u] : $dezenas[$d];
        }
    }
    return implode(' e ', $out);
}

/* ---------------- data por extenso ---------------- */

function data_extenso(?string $data = null): string
{
    $ts = $data ? strtotime($data) : time();
    $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $d = (int) date('d', $ts);
    $m = (int) date('n', $ts);
    $a = date('Y', $ts);
    return sprintf('%02d de %s de %s', $d, $meses[$m], $a);
}

/* ---------------- lista de campos do contrato (snapshot) ---------------- */

/**
 * Todos os campos variáveis do contrato, agrupados. Usado pelo formulário de geração
 * e pela renderização. As chaves são as mesmas usadas nos placeholders {{chave}}.
 */
function contrato_campos_definicao(): array
{
    return [
        'comprador' => [
            'titulo' => 'Dados do comprador',
            'campos' => [
                'comprador_nome'        => ['label' => 'Nome completo', 'req' => true],
                'comprador_cpf'         => ['label' => 'CPF/CNPJ', 'req' => true],
                'comprador_rg'          => ['label' => 'RG'],
                'comprador_orgao'       => ['label' => 'Órgão emissor'],
                'comprador_nascimento'  => ['label' => 'Data de nascimento', 'tipo' => 'date'],
                'comprador_nacionalidade' => ['label' => 'Nacionalidade'],
                'comprador_estado_civil' => ['label' => 'Estado civil'],
                'comprador_profissao'   => ['label' => 'Profissão'],
                'comprador_logradouro'  => ['label' => 'Endereço (rua/av.)'],
                'comprador_numero'      => ['label' => 'Número'],
                'comprador_complemento' => ['label' => 'Complemento'],
                'comprador_bairro'      => ['label' => 'Bairro'],
                'comprador_cidade'      => ['label' => 'Cidade', 'req' => true],
                'comprador_uf'          => ['label' => 'UF', 'req' => true],
                'comprador_cep'         => ['label' => 'CEP'],
                'comprador_telefone'    => ['label' => 'Telefone', 'req' => true],
                'comprador_whatsapp'    => ['label' => 'WhatsApp'],
                'comprador_email'       => ['label' => 'E-mail'],
            ],
        ],
        'moto' => [
            'titulo' => 'Dados da moto',
            'campos' => [
                'moto_marca'       => ['label' => 'Marca', 'req' => true],
                'moto_modelo'      => ['label' => 'Modelo', 'req' => true],
                'moto_versao'      => ['label' => 'Versão'],
                'moto_cor'         => ['label' => 'Cor'],
                'moto_combustivel' => ['label' => 'Combustível'],
                'moto_ano_fab'     => ['label' => 'Ano fabricação'],
                'moto_ano_mod'     => ['label' => 'Ano modelo'],
                'moto_km'          => ['label' => 'Quilometragem'],
                'moto_placa'       => ['label' => 'Placa'],
                'moto_renavam'     => ['label' => 'Renavam'],
                'moto_chassi'      => ['label' => 'Chassi'],
                'moto_motor'       => ['label' => 'Número do motor'],
                'moto_cilindrada'  => ['label' => 'Cilindrada'],
                'moto_fipe'        => ['label' => 'Valor FIPE', 'tipo' => 'dinheiro'],
            ],
        ],
        'vendedor' => [
            'titulo' => 'Vendedor responsável',
            'campos' => [
                'vendedor_nome'     => ['label' => 'Nome do vendedor'],
                'vendedor_cpf'      => ['label' => 'CPF do vendedor'],
                'vendedor_comissao' => ['label' => 'Comissão (%)'],
            ],
        ],
        'pagamento' => [
            'titulo' => 'Pagamento',
            'campos' => [
                'valor_venda'         => ['label' => 'Valor da venda', 'tipo' => 'dinheiro', 'req' => true],
                'data_venda'          => ['label' => 'Data da venda', 'tipo' => 'date'],
                'entrada_valor'       => ['label' => 'Entrada (PIX/TED/dinheiro)', 'tipo' => 'dinheiro'],
                'entrada_data'        => ['label' => 'Data da entrada', 'tipo' => 'date'],
                'fin_valor'           => ['label' => 'Valor financiado', 'tipo' => 'dinheiro'],
                'fin_parcelas'        => ['label' => 'Nº de parcelas', 'tipo' => 'numero'],
                'fin_valor_parcela'   => ['label' => 'Valor da parcela', 'tipo' => 'dinheiro'],
                'fin_financeira'      => ['label' => 'Financeira'],
                'fin_primeira_data'   => ['label' => 'Data da 1ª parcela', 'tipo' => 'date'],
            ],
        ],
        'troca' => [
            'titulo' => 'Veículo na troca (opcional)',
            'campos' => [
                'troca_marca'  => ['label' => 'Marca'],
                'troca_modelo' => ['label' => 'Modelo'],
                'troca_ano'    => ['label' => 'Ano'],
                'troca_placa'  => ['label' => 'Placa'],
                'troca_chassi' => ['label' => 'Chassi'],
                'troca_valor'  => ['label' => 'Valor de avaliação', 'tipo' => 'dinheiro'],
            ],
        ],
        'documentacao' => [
            'titulo' => 'Documentação e regularidade',
            'campos' => [
                'doc_transferencia' => ['label' => 'Dados da transferência', 'tipo' => 'textarea'],
                'reg_tributos'      => ['label' => 'Valor aproximado dos tributos'],
                'reg_furto'         => ['label' => 'Furto'],
                'reg_multas'        => ['label' => 'Multas e taxas anuais em aberto'],
                'reg_alienacao'     => ['label' => 'Alienação fiduciária'],
                'reg_outros'        => ['label' => 'Outros registros impeditivos'],
            ],
        ],
        'observacoes' => [
            'titulo' => 'Observações',
            'campos' => [
                'observacoes' => ['label' => 'Observações do contrato', 'tipo' => 'textarea'],
            ],
        ],
    ];
}

/** Lista plana de todas as chaves de campo. */
function contrato_todas_chaves(): array
{
    $chaves = [];
    foreach (contrato_campos_definicao() as $grupo) {
        foreach ($grupo['campos'] as $chave => $_) {
            $chaves[] = $chave;
        }
    }
    return $chaves;
}

/* ---------------- extração automática a partir da venda (moto) ---------------- */

/**
 * Pré-preenche os campos do contrato com o que já existe no cadastro da venda.
 * O que não existir no banco fica em branco para o usuário completar.
 */
function contrato_dados_da_venda(array $m): array
{
    $dados = array_fill_keys(contrato_todas_chaves(), '');

    // comprador
    $dados['comprador_nome']     = $m['cliente_nome'] ?? '';
    $dados['comprador_cpf']      = $m['cliente_documento'] ?? '';
    $dados['comprador_cidade']   = $m['cliente_cidade'] ?? '';
    $dados['comprador_uf']       = $m['cliente_estado'] ?? '';
    $dados['comprador_telefone'] = $m['cliente_telefone'] ?? '';
    $dados['comprador_nacionalidade'] = 'Brasileiro(a)';

    // moto
    $dados['moto_marca']  = $m['marca'] ?? '';
    $dados['moto_modelo'] = $m['modelo'] ?? '';
    $dados['moto_cor']    = $m['cor'] ?? '';
    $ano = (string) ($m['ano'] ?? '');
    $dados['moto_ano_fab'] = $ano;
    $dados['moto_ano_mod'] = $ano;
    $dados['moto_km']     = isset($m['km']) && $m['km'] !== '' ? (string) $m['km'] : '';
    $dados['moto_placa']  = $m['placa'] ?? '';
    $dados['moto_chassi'] = $m['chassi'] ?? '';

    // vendedor
    $dados['vendedor_nome'] = $m['vendedor'] ?? '';

    // pagamento
    $dados['valor_venda'] = isset($m['valor_venda']) ? valor_input((float) $m['valor_venda']) : '';
    $dados['data_venda']  = !empty($m['vendido_em']) ? substr($m['vendido_em'], 0, 10) : date('Y-m-d');

    // documentação / observações
    $dados['observacoes'] = $m['venda_obs'] ?? '';

    return $dados;
}

/** Resume a forma de pagamento a partir do snapshot (para a coluna do histórico). */
function contrato_forma_pagamento(array $d): string
{
    $entrada = parse_dinheiro($d['entrada_valor'] ?? '0');
    $fin = parse_dinheiro($d['fin_valor'] ?? '0');
    if ($entrada > 0 && $fin > 0) {
        return 'Entrada + Financiamento';
    }
    if ($fin > 0) {
        return 'Financiamento';
    }
    if ($entrada > 0) {
        return 'À vista / Entrada';
    }
    return '—';
}

/* ---------------- renderização do contrato ---------------- */

/**
 * Monta os blocos compostos (pagamento, troca) e devolve o mapa completo de
 * placeholders {{chave}} => valor já formatado para exibição no documento.
 */
function contrato_placeholders(array $d): array
{
    $cfg = config_all();
    $ph = [];

    // empresa (das configurações)
    $ph['empresa_razao']    = $cfg['empresa_razao'] ?? '';
    $ph['empresa_cnpj']     = $cfg['empresa_cnpj'] ?? '';
    $ph['empresa_endereco'] = $cfg['empresa_endereco'] ?? '';
    $ph['empresa_numero']   = $cfg['empresa_numero'] ?? '';
    $ph['empresa_bairro']   = $cfg['empresa_bairro'] ?? '';
    $ph['empresa_cidade']   = $cfg['empresa_cidade'] ?? '';
    $ph['empresa_uf']       = $cfg['empresa_uf'] ?? '';

    // comprador / moto / etc. (direto do snapshot)
    foreach (contrato_todas_chaves() as $chave) {
        $ph[$chave] = trim((string) ($d[$chave] ?? ''));
    }

    // valores monetários formatados
    $valorVenda = parse_dinheiro($d['valor_venda'] ?? '0');
    $ph['valor_venda']   = formatar_reais($valorVenda);
    $ph['valor_extenso'] = $valorVenda > 0 ? valor_por_extenso($valorVenda) : '';

    // datas em DD/MM/AAAA
    $ph['data_venda'] = !empty($d['data_venda']) ? data_br($d['data_venda']) : '';
    $ph['comprador_nascimento'] = !empty($d['comprador_nascimento']) ? data_br($d['comprador_nascimento']) : '';

    // responsabilidade civil (cláusula 4) — data/hora da emissão
    $ph['resp_data'] = date('d/m/Y');
    $ph['resp_hora'] = date('H:i:s');

    // data por extenso das assinaturas
    $ph['data_extenso'] = data_extenso($d['data_venda'] ?? null);

    // testemunhas / assinaturas
    $ph['testemunha1_nome'] = $cfg['testemunha1_nome'] ?? '';
    $ph['testemunha1_cpf']  = $cfg['testemunha1_cpf'] ?? '';
    $ph['testemunha2_nome'] = $cfg['testemunha2_nome'] ?? '';
    $ph['testemunha2_cpf']  = $cfg['testemunha2_cpf'] ?? '';

    // bloco de pagamento (composto)
    $linhas = [];
    $entrada = parse_dinheiro($d['entrada_valor'] ?? '0');
    if ($entrada > 0) {
        $l = 'TED, DOC, PIX, Transferência bancária ' . formatar_reais($entrada);
        if (!empty($d['entrada_data'])) {
            $l .= ' — Data: ' . data_br($d['entrada_data']);
        }
        $linhas[] = $l;
    }
    $fin = parse_dinheiro($d['fin_valor'] ?? '0');
    if ($fin > 0) {
        $l = 'Financiamento ' . formatar_reais($fin);
        if (!empty($d['fin_parcelas'])) {
            $l .= ' em ' . (int) $d['fin_parcelas'] . ' parcelas';
            $vp = parse_dinheiro($d['fin_valor_parcela'] ?? '0');
            if ($vp > 0) {
                $l .= ' de ' . formatar_reais($vp);
            }
        }
        if (!empty($d['fin_financeira'])) {
            $l .= ' — Financeira: ' . $d['fin_financeira'];
        }
        if (!empty($d['fin_primeira_data'])) {
            $l .= ' — Data: ' . data_br($d['fin_primeira_data']);
        }
        $linhas[] = $l;
    }
    if (!$linhas) {
        // sem detalhamento: mostra a forma de pagamento genérica, se houver
        $linhas[] = 'Conforme condições acordadas entre as partes.';
    }
    $ph['bloco_pagamento'] = '<p class="ct-pgto">' . implode('</p><p class="ct-pgto">', array_map('e', $linhas)) . '</p>';

    // bloco de troca (composto, opcional)
    $trocaValor = parse_dinheiro($d['troca_valor'] ?? '0');
    $temTroca = $trocaValor > 0 || trim((string) ($d['troca_marca'] ?? '')) !== '';
    if ($temTroca) {
        $ph['bloco_troca'] =
            '<p class="ct-sub"><strong>VEÍCULO RECEBIDO NA TROCA:</strong></p>'
            . '<p class="ct-obj">Marca: ' . e($d['troca_marca'] ?? '')
            . ' &nbsp; Modelo: ' . e($d['troca_modelo'] ?? '')
            . ' &nbsp; Ano: ' . e($d['troca_ano'] ?? '') . '<br>'
            . 'Placa: ' . e($d['troca_placa'] ?? '')
            . ' &nbsp; Chassi: ' . e($d['troca_chassi'] ?? '')
            . ' &nbsp; Avaliação: ' . formatar_reais($trocaValor) . '</p>';
    } else {
        $ph['bloco_troca'] = '';
    }

    return $ph;
}

/** Substitui os placeholders {{chave}} no corpo do modelo pelos valores. */
function contrato_render(string $template, array $dados): string
{
    $ph = contrato_placeholders($dados);
    // blocos compostos entram como HTML; os demais campos são escapados.
    $htmlKeys = ['bloco_pagamento', 'bloco_troca'];
    $subs = [];
    foreach ($ph as $k => $v) {
        $subs['{{' . $k . '}}'] = in_array($k, $htmlKeys, true) ? $v : e((string) $v);
    }
    return strtr($template, $subs);
}

/**
 * Modelo padrão de Contrato de Compra e Venda (fiel ao documento oficial).
 * Cláusulas fixas em texto integral; campos variáveis como {{placeholders}}.
 */
function contrato_template_padrao(): string
{
    return <<<'HTML'
<div class="ct-doc">
  <h1 class="ct-titulo">CONTRATO DE COMPRA E VENDA</h1>

  <p class="ct-label"><strong>VENDEDOR:</strong></p>
  <p class="ct-parte">{{empresa_razao}}, pessoa jurídica de direito privado, inscrita no CNPJ {{empresa_cnpj}}, com sede em {{empresa_cidade}}/{{empresa_uf}}, na {{empresa_endereco}}, nº {{empresa_numero}} bairro {{empresa_bairro}}.</p>

  <p class="ct-label"><strong>COMPRADOR:</strong></p>
  <p class="ct-parte">{{comprador_nome}} RG/IE nº. {{comprador_rg}} e inscrito no CPF/CNPJ sob o nº. {{comprador_cpf}}, com endereço em {{comprador_cidade}}/{{comprador_uf}}, na {{comprador_logradouro}} nº {{comprador_numero}}, {{comprador_complemento}} bairro {{comprador_bairro}}, cep {{comprador_cep}}, telefone {{comprador_telefone}}.</p>

  <p class="ct-intro">As partes acima referidas e qualificadas resolvem entabular a presente compra e venda na forma e termos a seguir expostos:</p>

  <h2 class="ct-clausula">CLÁUSULA PRIMEIRA – DO OBJETO</h2>
  <p class="ct-obj">
    Marca: {{moto_marca}}<br>
    Modelo: {{moto_modelo}}<br>
    Cor: {{moto_cor}}<br>
    Combustível: {{moto_combustivel}}<br>
    Vendedor: {{vendedor_nome}}<br>
    Data da venda: {{data_venda}}<br>
    Ano Fab/Mod: {{moto_ano_fab}}/{{moto_ano_mod}}<br>
    Km: {{moto_km}}<br>
    Placa: {{moto_placa}}<br>
    Renavam: {{moto_renavam}}<br>
    Chassi: {{moto_chassi}}
  </p>

  <h2 class="ct-clausula">CLÁUSULA SEGUNDA - DO PREÇO E FORMA DE PAGAMENTO</h2>
  <p class="ct-preco">{{valor_venda}} valor da venda <span class="ct-extenso">({{valor_extenso}})</span></p>
  <p>O pagamento se dará da seguinte forma:</p>
  {{bloco_pagamento}}
  {{bloco_troca}}

  <p class="ct-sub"><strong>DOCUMENTAÇÃO</strong></p>
  <p>Dados da transferência: {{doc_transferencia}}</p>

  <p class="ct-sub"><strong>OBSERVAÇÕES:</strong></p>
  <p class="ct-obs">{{observacoes}}</p>

  <h2 class="ct-clausula">CLÁUSULA TERCEIRA - DA VISTORIA E AVALIAÇÃO DO VEÍCULO</h2>
  <p>O COMPRADOR declara ter vistoriado e avaliado o estado em que se encontra o veículo ora negociado, estando o mesmo em perfeitas condições de funcionamento e estado de conservação.</p>

  <h2 class="ct-clausula">CLÁUSULA QUARTA - DA RESPONSABILIDADE CIVIL E CRIMINAL</h2>
  <p>A partir desta data {{resp_data}} e hora {{resp_hora}}, o COMPRADOR se responsabiliza por quaisquer danos, seja no âmbito civil ou criminal, decorrente da utilização do veículo ora adquirido, inclusive multas e pontuações na CNH decorrentes de tais infrações, sejam elas de âmbito Municipal, Estadual e ou Federal, bem como, fica responsável também, nos mesmos termos acima, até a presente data e hora, por eventual veículo dado na compra do objeto do presente, respondendo ainda o comprador, pela evicção e eventuais vícios redibitórios do mesmo. O VENDEDOR, acaso tenha recebido algum veículo do COMPRADOR, como forma de pagamento do bem objeto do presente, fica responsável, por quaisquer danos, seja no âmbito civil ou penal, decorrente da utilização do veículo ora recebido, inclusive multas e pontuações na CNH decorrentes de tais infrações, sejam elas de âmbito Municipal, Estadual e ou Federal; Deste modo, o presente instrumento é firmado nos termos do artigo 784, III do CPC, razão pela qual é um título executivo extrajudicial, mesmo porque, o &ldquo;quantum debeatur&rdquo; depende de simples cálculo aritmético, à partir de dados consignados em documentos comprobatórios do débito (multas de trânsito, IPVA, licenciamento e outros). Nesta seara, a VENDEDORA poderá executar o presente para cobrança de eventuais valores encontrados e de responsabilidade do COMPRADOR.</p>

  <h2 class="ct-clausula">CLÁUSULA QUINTA – DA GARANTIA</h2>
  <p>Para os veículos usados a VENDEDORA fornece a garantia pelo tempo exigido por lei, e somente para os componente internos de motor e caixa de câmbio, contudo, a mesma estará automaticamente cancelada, no caso de mau uso do veículo em questão, em caso deste último ter suas características originais (as quais são especificadas pelo fabricante do manual do veículo), alteradas, bem como, quando o mesmo for utilizado fora dos padrões e ou limites de carga e/ou de rotação especificados pelo fabricantes ou ainda, se for utilizado em competições de qualquer espécie ou natureza, além do que, se tiver sua manutenção negligenciada. Todo e qualquer serviço e ou conserto coberto por esta garantia deverá ser executado por assistência técnica ou oficina mecânica indicada por esta VENDEDORA e somente após orçamento aprovado pela VENDEDORA; Qualquer manutenção feita pelo COMPRADOR em oficina propria do mesmo, não será ressarcido pelo VENDEDOR; Para os veículos novos, isto é, 0KM, a garantia é a de fábrica; Ficam de fora da presente garantia, os componentes eletro-eletrônicos e do sistema de arrefecimento do veículo, como por exemplo: sensores, módulos em geral, centrais, mangueiras, bomba d&rsquo;água, fiações, etc. Do mesmo modo, também não são cobertos eventuais vazamentos de óleo advindos de falhas ou danos em juntas, vedações ou retentores, bem como quebras de correntes da falta de lubrificantes ou por uso indevido do veículo, bem como eventuais danos gerados pelo superaquecimento do motor, seja por falha da bomba d&rsquo;água, falta de refrigeração ou ainda em decorrência da alteração do tipo de combustível utilizado pelo veículo, além do que, utilização de combustível adulterado. Estão fora da presente garantia, itens de desgaste natural e vida úteis pré-determinados, tais como: discos e platô de embreagem, discos, tambores, pastilhas e lonas de freio, cabos de vela, correias em geral, bateria, amortecedores e molas, entre outros, incluindo-se ainda os itens considerados de manutenção normal, como limpeza de bicos injetores, fluídos e óleos em geral. A garantia das peças eventualmente substituídas na vigência deste, finda-se com o término do mesmo. Todo e qualquer custo não relacionado diretamente com a garantia do veículo, tais como despesas com taxi, guincho, alimentação, hospedagem, etc, não é de responsabilidade da VENDEDORA;</p>

  <h2 class="ct-clausula">CLÁUSULA SEXTA - DA TRANSFERÊNCIA DO BEM</h2>
  <p>A transferência do bem objeto do presente instrumento para o nome do comprador ou de alguém por ele determinado, só se dará após a total quitação do bem descrito na cláusula primeira deste, sendo que na hipótese de pagamento em cheque(s) ou qualquer outro título de crédito, após a compensação ou quitação do(s) mesmo(s);</p>
  <p>Dados da transferência: {{doc_transferencia}}</p>

  <h2 class="ct-clausula">CLÁUSULA SÉTIMA - DA CLÁUSULA RESOLUTIVA</h2>
  <p>As partes VENDEDOR(A) e COMPRADOR(A), estabelecem desde já, que no caso de não cumprimento do presente, quanto aos pagamentos devidos pelo COMPRADOR ao VENDEDOR, na forma e prazos estabelecidos no bojo deste instrumento particular de contrato, os quais foram avençados de comum acordo entre partes, permitirá ao VENDEDOR, como melhor lhe aprouver, pedir a resolução do contrato ou, se preferir, exigir o cumprimento do mesmo, independentemente de notificação ou interpelação, nos termos do que reza o artigo 475 do Código Civil. Fica desde já avençado entre estas partes, que na hipótese de resolução do contrato, em se tratando de veículo usado, o COMPRADOR deverá pagar ao VENDEDOR, até a devolução do bem objeto deste instrumento, o valor diário pelo uso do veículo, na base de 0,5% (meio por cento) sobre o valor do mesmo, em se tratando de veículo novo (0 Km), o COMPRADOR deverá pagar ao VENDEDOR, até a devolução do bem objeto deste instrumento, o valor diário pelo uso do veículo, na base de 0,5% (meio por cento) sobre o valor do bem, mais o valor decorrente da depreciação sofrida pelo veículo em razão de não ser mais o mesmo um bem 0Km, servindo-se para tal verificação (depreciação), a tabela FIPE atualizada;</p>

  <h2 class="ct-clausula">CLÁUSULA OITAVA</h2>
  <p>Nos termos do que estabelece o artigo 629 do Código Civil, o COMPRADOR assume, de forma gratuita, a condição de depositário do bem objeto do presente, obrigando-se pela guarda e conservação do mesmo, até o integral pagamento do preço;</p>

  <h2 class="ct-clausula">CLÁUSULA NONA - SITUAÇÃO DE REGULARIDADE</h2>
  <p class="ct-obj">
    - valor aproximado dos tributos: {{reg_tributos}}<br>
    - furto: {{reg_furto}}<br>
    - multas e taxas anuais em aberto: {{reg_multas}}<br>
    - alienação fiduciaria: {{reg_alienacao}}<br>
    - outros registros impeditivos a circulação do veículo: {{reg_outros}}
  </p>

  <h2 class="ct-clausula">CLÁUSULA DÉCIMA - LEI GERAL DE PROTEÇÃO DE DADOS</h2>
  <p>A empresa VENDEDORA em questão, está em conformidade com a Lei 13.709/2018 – LGPD, protegendo os direitos fundamentais de liberdade e de privacidade e o livre desenvolvimento da personalidade da pessoa natural no que diz respeito às operações realizadas com os dados pessoais armazenados de forma física ou digital ora fornecidos para o fim de elaborar este documento e realizar os trâmites necessários para a perfeita prestação de serviços contratada em questão. Em respeito ao artigo 7º, inciso I e artigo 8º, § 1º da aludida Lei, tais dados serão utilizados somente, e tão somente, com a finalidade de manutenção necessária para este contrato, estando o titular autorizando e dando sua ciência de fornecimento para este fim específico;</p>

  <h2 class="ct-clausula">CLÁUSULA DÉCIMA PRIMEIRA - FORO DE ELEIÇÃO</h2>
  <p>Para dirimir quaisquer dúvidas decorrentes do presente, as partes estabelecem desde já, com exclusividade, o foro da Comarca da VENDEDORA, por mais privilegiado que outro possa ser. O COMPRADOR, de livre e espontânea vontade, RENUNCIA ao foro previsto no artigo 101, I do Código de Defesa do Consumidor;</p>

  <p class="ct-fecho">E, para que produza seus legais efeitos, firmo o presente termo, na presença de 2 (duas) testemunhas.</p>

  <p class="ct-data">Dia {{data_extenso}}.</p>

  <div class="ct-assinaturas">
    <div class="ct-assina">
      <div class="ct-linha"></div>
      <p>{{comprador_nome}}<br>{{comprador_cpf}}</p>
    </div>
    <div class="ct-assina">
      <div class="ct-linha"></div>
      <p>Testemunha 1<br>{{testemunha1_nome}}<br>{{testemunha1_cpf}}</p>
    </div>
    <div class="ct-assina">
      <div class="ct-linha"></div>
      <p>{{empresa_razao}}<br>{{empresa_cnpj}}</p>
    </div>
    <div class="ct-assina">
      <div class="ct-linha"></div>
      <p>Testemunha 2<br>{{testemunha2_nome}}<br>{{testemunha2_cpf}}</p>
    </div>
  </div>
</div>
HTML;
}

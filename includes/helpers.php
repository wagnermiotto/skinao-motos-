<?php

const CATEGORIAS = [
    'street'    => 'Street',
    'sport'     => 'Esportiva',
    'trail'     => 'Trail/Adventure',
    'custom'    => 'Custom/Cruiser',
    'scooter'   => 'Scooter',
    'outra'     => 'Outra',
];

const STATUS_MOTO = [
    'disponivel' => 'Disponível',
    'reservada'  => 'Reservada',
    'vendida'    => 'Vendida',
];

const FORMAS_PAGAMENTO = [
    'Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito',
    'Financiamento', 'Consórcio', 'Troca', 'Boleto',
];

const UFS = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
    'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
];

function categoria_label(string $categoria): string
{
    return CATEGORIAS[$categoria] ?? 'Outra';
}

function status_label(string $status): string
{
    return STATUS_MOTO[$status] ?? 'Disponível';
}

/* ---------------- cálculos financeiros da moto (automáticos) ----------------
   Nunca armazenados: derivados sempre dos valores lançados no cadastro. */

function moto_custo_total(array $m): float
{
    return (float) ($m['valor_compra'] ?? 0)
        + (float) ($m['gasto_manutencao'] ?? 0)
        + (float) ($m['gasto_documentacao'] ?? 0)
        + (float) ($m['gasto_transporte'] ?? 0)
        + (float) ($m['outros_custos'] ?? 0);
}

function moto_lucro_bruto(array $m): float
{
    return (float) ($m['valor_venda'] ?? 0) - (float) ($m['valor_compra'] ?? 0);
}

function moto_lucro_liquido(array $m): float
{
    return (float) ($m['valor_venda'] ?? 0) - moto_custo_total($m);
}

function moto_margem(array $m): float
{
    $venda = (float) ($m['valor_venda'] ?? 0);
    return $venda > 0 ? (moto_lucro_liquido($m) / $venda) * 100 : 0.0;
}

function formatar_reais(float $valor): string
{
    // valores negativos com o sinal antes do "R$" (formato brasileiro correto)
    $sinal = $valor < 0 ? '-' : '';
    return $sinal . 'R$ ' . number_format(abs($valor), 2, ',', '.');
}

/**
 * Converte um valor em dinheiro digitado (formato brasileiro) para float.
 * Ponto = separador de milhar; vírgula = separador decimal.
 * Ex.: "1.005.670,00" -> 1005670.0 ; "25.900" -> 25900.0 ; "25900,50" -> 25900.5
 * Mesma lógica do cálculo ao vivo em JS, garantindo que o valor salvo seja idêntico ao exibido.
 */
function parse_dinheiro($valor): float
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return 0.0;
    }
    $valor = str_replace('.', '', $valor);   // remove separador de milhar
    $valor = str_replace(',', '.', $valor);  // vírgula decimal -> ponto
    $valor = preg_replace('/[^0-9.\-]/', '', $valor);
    return $valor === '' ? 0.0 : (float) $valor;
}

/**
 * Formata um valor para exibição em campo de formulário (formato brasileiro, sem "R$").
 */
function valor_input(float $valor): string
{
    return number_format($valor, 2, ',', '.');
}

function formatar_percent(float $valor): string
{
    return number_format($valor, 1, ',', '.') . '%';
}

/**
 * Converte uma data/hora do banco ("YYYY-MM-DD HH:MM:SS") para o padrão brasileiro "DD/MM/AAAA".
 */
function data_br(?string $datetime): string
{
    if (empty($datetime)) {
        return '';
    }
    $data = substr($datetime, 0, 10); // YYYY-MM-DD
    $partes = explode('-', $data);
    if (count($partes) !== 3) {
        return $data;
    }
    return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
}

function formatar_preco(float $preco): string
{
    return 'R$ ' . number_format($preco, 0, ',', '.');
}

function formatar_km(int $km): string
{
    return number_format($km, 0, ',', '.') . ' km';
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function moto_fotos(int $motoId): array
{
    $stmt = db()->prepare('SELECT * FROM moto_fotos WHERE moto_id = ? ORDER BY capa DESC, ordem ASC, id ASC');
    $stmt->execute([$motoId]);
    return $stmt->fetchAll();
}

function moto_foto_capa(int $motoId): ?string
{
    $fotos = moto_fotos($motoId);
    return $fotos[0]['arquivo'] ?? null;
}

/**
 * Salva uma foto enviada por upload dentro de UPLOADS_DIR/{moto_id}/ com nome aleatório.
 * Retorna o nome do arquivo salvo (relativo à pasta da moto) ou null se não houver upload válido.
 */
function salvar_upload_foto(array $file, int $motoId): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio da imagem (código ' . $file['error'] . ').');
    }
    if ($file['size'] > 6 * 1024 * 1024) {
        throw new RuntimeException('Imagem maior que 6MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensoesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensoesPermitidas[$mime])) {
        throw new RuntimeException('Formato de imagem não suportado. Use JPG, PNG ou WEBP.');
    }

    $dir = UPLOADS_DIR . '/' . $motoId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $nomeArquivo = bin2hex(random_bytes(8)) . '.' . $extensoesPermitidas[$mime];
    move_uploaded_file($file['tmp_name'], $dir . '/' . $nomeArquivo);

    return $nomeArquivo;
}

function moto_foto_url(int $motoId, string $arquivo): string
{
    return UPLOADS_URL . '/' . $motoId . '/' . $arquivo;
}

/**
 * Filtra motos ativas e em destaque que possuem foto de capa, para uso no carrossel do topo.
 */
function motos_destaque_com_foto(array $motos, int $limite = 5): array
{
    $filtradas = array_values(array_filter($motos, function ($m) {
        return (bool) $m['destaque'] && moto_foto_capa((int) $m['id']) !== null;
    }));
    return array_slice($filtradas, 0, $limite);
}

/* ---------------- clientes satisfeitos ---------------- */

/**
 * Retorna as fotos de clientes. Por padrão só as ativas (para o site público);
 * passe $apenasAtivos = false para o painel administrativo listar todas.
 */
function clientes_fotos(bool $apenasAtivos = true): array
{
    $sql = 'SELECT * FROM clientes';
    if ($apenasAtivos) {
        $sql .= ' WHERE ativo = 1';
    }
    $sql .= ' ORDER BY ordem ASC, id DESC';
    return db()->query($sql)->fetchAll();
}

function cliente_foto_url(string $arquivo): string
{
    return UPLOADS_CLIENTES_URL . '/' . $arquivo;
}

/**
 * Salva a foto de um cliente em UPLOADS_CLIENTES_DIR com nome aleatório.
 * Retorna o nome do arquivo salvo, ou null se não houver upload, ou lança em caso de erro.
 */
function salvar_upload_cliente(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio da imagem (código ' . $file['error'] . ').');
    }
    if ($file['size'] > 6 * 1024 * 1024) {
        throw new RuntimeException('Imagem maior que 6MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensoesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensoesPermitidas[$mime])) {
        throw new RuntimeException('Formato de imagem não suportado. Use JPG, PNG ou WEBP.');
    }

    if (!is_dir(UPLOADS_CLIENTES_DIR)) {
        mkdir(UPLOADS_CLIENTES_DIR, 0755, true);
    }

    $nomeArquivo = bin2hex(random_bytes(8)) . '.' . $extensoesPermitidas[$mime];
    move_uploaded_file($file['tmp_name'], UPLOADS_CLIENTES_DIR . '/' . $nomeArquivo);

    return $nomeArquivo;
}

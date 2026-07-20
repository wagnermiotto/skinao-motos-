<?php
/**
 * Métricas do Dashboard executivo — Skinão Motos.
 *
 * Tudo é derivado do cadastro de motos existente (tabela `motos`), em tempo de leitura.
 * Nenhum dado é digitado duas vezes nem armazenado de forma redundante: sempre que uma
 * moto é cadastrada, editada, vendida, reservada ou removida, este cálculo reflete a
 * mudança na próxima carga da página.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function metrics_dias_desde(?string $data, DateTimeImmutable $agora): int
{
    if (empty($data)) {
        return 0;
    }
    try {
        $d = new DateTimeImmutable(substr($data, 0, 10));
        return max(0, (int) $d->diff($agora)->format('%a'));
    } catch (Exception $e) {
        return 0;
    }
}

function metrics_mes_curto(string $ym): string
{
    $meses = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun',
              '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
    [$ano, $mes] = explode('-', $ym);
    return ($meses[$mes] ?? $mes) . '/' . substr($ano, 2);
}

function dashboard_metrics(): array
{
    $motos = db()->query('SELECT * FROM motos ORDER BY criado_em DESC')->fetchAll();

    $agora = new DateTimeImmutable('now');
    $hoje = $agora->format('Y-m-d');
    $mesAtual = $agora->format('Y-m');
    $anoAtual = $agora->format('Y');
    $dow = (int) $agora->format('N'); // 1 = segunda
    $inicioSemana = $agora->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');

    $disponiveis = $vendidas = $reservadas = [];
    foreach ($motos as $m) {
        $status = $m['status'] ?? 'disponivel';
        if ($status === 'vendida') {
            $vendidas[] = $m;
        } elseif ($status === 'reservada') {
            $reservadas[] = $m;
        } else {
            $disponiveis[] = $m;
        }
    }

    // ---------------- vendas / receita / lucro ----------------
    $receitaDia = $receitaSemana = $receitaMes = $receitaAno = 0.0;
    $receitaTotal = $lucroBrutoTotal = $lucroLiquidoTotal = $custoVendas = 0.0;
    $temposVenda = [];
    $vendasMes = $receitaMesMap = $lucroMesMap = [];
    $vendasPorMarca = $vendasPorModelo = [];

    foreach ($vendidas as $m) {
        $venda = (float) $m['valor_venda'];
        $receitaTotal += $venda;
        $lucroBrutoTotal += moto_lucro_bruto($m);
        $lucroLiquidoTotal += moto_lucro_liquido($m);
        $custoVendas += moto_custo_total($m);

        $vend = $m['vendido_em'] ?? null;
        if ($vend) {
            $dia = substr($vend, 0, 10);
            $ym = substr($vend, 0, 7);
            if ($dia === $hoje) $receitaDia += $venda;
            if ($dia >= $inicioSemana) $receitaSemana += $venda;
            if ($ym === $mesAtual) $receitaMes += $venda;
            if (substr($vend, 0, 4) === $anoAtual) $receitaAno += $venda;

            $vendasMes[$ym] = ($vendasMes[$ym] ?? 0) + 1;
            $receitaMesMap[$ym] = ($receitaMesMap[$ym] ?? 0) + $venda;
            $lucroMesMap[$ym] = ($lucroMesMap[$ym] ?? 0) + moto_lucro_liquido($m);

            $temposVenda[] = metrics_dias_desde($m['criado_em'] ?? null, new DateTimeImmutable($dia));
        }

        $vendasPorMarca[$m['marca']] = ($vendasPorMarca[$m['marca']] ?? 0) + 1;
        $modelo = trim($m['marca'] . ' ' . $m['modelo']);
        $vendasPorModelo[$modelo] = ($vendasPorModelo[$modelo] ?? 0) + 1;
    }

    $qtdVendidas = count($vendidas);
    $ticketMedio = $qtdVendidas ? $receitaTotal / $qtdVendidas : 0.0;
    $margemMedia = $receitaTotal > 0 ? ($lucroLiquidoTotal / $receitaTotal) * 100 : 0.0;
    $tempoMedioVenda = $temposVenda ? array_sum($temposVenda) / count($temposVenda) : 0.0;
    $roi = $custoVendas > 0 ? ($lucroLiquidoTotal / $custoVendas) * 100 : 0.0;

    // ---------------- estoque disponível ----------------
    $valorEstoque = $investidoEstoque = 0.0;
    $estoquePorCategoria = $estoquePorMarca = $estoquePorFaixa = [];
    $faixas = [
        'Até R$ 15 mil'     => [0, 15000],
        'R$ 15–25 mil'      => [15000, 25000],
        'R$ 25–40 mil'      => [25000, 40000],
        'Acima de R$ 40 mil' => [40000, INF],
    ];
    foreach (array_keys($faixas) as $rot) {
        $estoquePorFaixa[$rot] = 0;
    }
    $parados30 = $parados60 = $parados90 = [];

    foreach ($disponiveis as $m) {
        $valorEstoque += (float) $m['preco'];
        $investidoEstoque += moto_custo_total($m);

        $cat = categoria_label($m['categoria']);
        $estoquePorCategoria[$cat] = ($estoquePorCategoria[$cat] ?? 0) + 1;
        $estoquePorMarca[$m['marca']] = ($estoquePorMarca[$m['marca']] ?? 0) + 1;

        foreach ($faixas as $rot => $lim) {
            if ((float) $m['preco'] >= $lim[0] && (float) $m['preco'] < $lim[1]) {
                $estoquePorFaixa[$rot]++;
                break;
            }
        }

        $dias = metrics_dias_desde($m['criado_em'] ?? null, $agora);
        $registro = ['moto' => $m, 'dias' => $dias];
        if ($dias >= 90) {
            $parados90[] = $registro;
        } elseif ($dias >= 60) {
            $parados60[] = $registro;
        } elseif ($dias >= 30) {
            $parados30[] = $registro;
        }
    }

    $giro = count($disponiveis) > 0 ? $qtdVendidas / count($disponiveis) : 0.0;

    // ---------------- séries mensais (últimos 12 meses) ----------------
    $base = new DateTimeImmutable($agora->format('Y-m-01'));
    $mesesLabels = $serieVendas = $serieReceita = $serieLucro = [];
    for ($i = 11; $i >= 0; $i--) {
        $ym = $base->modify("-{$i} months")->format('Y-m');
        $mesesLabels[] = metrics_mes_curto($ym);
        $serieVendas[] = $vendasMes[$ym] ?? 0;
        $serieReceita[] = round($receitaMesMap[$ym] ?? 0, 2);
        $serieLucro[] = round($lucroMesMap[$ym] ?? 0, 2);
    }
    $acumFat = $acumLucro = [];
    $af = $al = 0.0;
    foreach ($serieReceita as $idx => $r) {
        $af += $r;
        $al += $serieLucro[$idx];
        $acumFat[] = round($af, 2);
        $acumLucro[] = round($al, 2);
    }

    // ---------------- rankings ----------------
    arsort($vendasPorMarca);
    arsort($vendasPorModelo);
    arsort($estoquePorMarca);

    // ---------------- últimas atividades ----------------
    $ultimasCadastradas = array_slice($motos, 0, 6);
    $ordVendidas = $vendidas;
    usort($ordVendidas, fn($a, $b) => strcmp($b['vendido_em'] ?? '', $a['vendido_em'] ?? ''));
    $ultimasVendidas = array_slice($ordVendidas, 0, 6);

    // ---------------- alertas inteligentes ----------------
    $alertas = [];
    $paradosLongos = count($parados60) + count($parados90);
    if ($paradosLongos > 0) {
        $alertas[] = ['nivel' => 'alto', 'texto' => $paradosLongos . ' moto(s) paradas há mais de 60 dias no estoque.'];
    }
    if (count($parados90) > 0) {
        $alertas[] = ['nivel' => 'alto', 'texto' => count($parados90) . ' moto(s) paradas há mais de 90 dias — considere reavaliar o preço.'];
    }
    $margemBaixa = array_filter($vendidas, fn($m) => (float) $m['valor_venda'] > 0 && moto_margem($m) < 10);
    if (count($margemBaixa) > 0) {
        $alertas[] = ['nivel' => 'medio', 'texto' => count($margemBaixa) . ' venda(s) com margem de lucro abaixo de 10%.'];
    }
    $docPendente = array_filter($disponiveis, fn($m) => (float) ($m['gasto_documentacao'] ?? 0) == 0.0 && (float) ($m['valor_compra'] ?? 0) > 0);
    if (count($docPendente) > 0) {
        $alertas[] = ['nivel' => 'medio', 'texto' => count($docPendente) . ' moto(s) com custo de documentação ainda não lançado.'];
    }
    foreach ($estoquePorCategoria as $cat => $qtd) {
        if ($qtd < 2) {
            $alertas[] = ['nivel' => 'baixo', 'texto' => 'Estoque baixo em "' . $cat . '" (' . $qtd . ' disponível).'];
        }
    }
    if (!$alertas) {
        $alertas[] = ['nivel' => 'ok', 'texto' => 'Nenhum alerta no momento. Estoque e margens dentro do esperado.'];
    }

    return [
        'indicadores' => [
            'total_motos'       => count($motos),
            'disponiveis'       => count($disponiveis),
            'vendidas'          => $qtdVendidas,
            'reservadas'        => count($reservadas),
            'valor_estoque'     => $valorEstoque,
            'receita_dia'       => $receitaDia,
            'receita_semana'    => $receitaSemana,
            'receita_mes'       => $receitaMes,
            'receita_ano'       => $receitaAno,
            'lucro_bruto'       => $lucroBrutoTotal,
            'lucro_liquido'     => $lucroLiquidoTotal,
            'margem_media'      => $margemMedia,
            'ticket_medio'      => $ticketMedio,
            'giro'              => $giro,
            'tempo_medio_venda' => $tempoMedioVenda,
        ],
        'financeiro' => [
            'investido_estoque' => $investidoEstoque,
            'total_vendido'     => $receitaTotal,
            'total_recebido'    => $receitaTotal,
            'lucro_acumulado'   => $lucroLiquidoTotal,
            'custos_totais'     => $custoVendas,
            'rentabilidade'     => $margemMedia,
            'roi'               => $roi,
        ],
        'estoque' => [
            'parados30'        => $parados30,
            'parados60'        => $parados60,
            'parados90'        => $parados90,
            'por_categoria'    => $estoquePorCategoria,
            'por_marca'        => $estoquePorMarca,
            'por_faixa'        => $estoquePorFaixa,
        ],
        'atividades' => [
            'cadastradas' => $ultimasCadastradas,
            'vendidas'    => $ultimasVendidas,
        ],
        'alertas' => $alertas,
        'graficos' => [
            'meses'          => $mesesLabels,
            'vendas_mes'     => $serieVendas,
            'receita_mes'    => $serieReceita,
            'lucro_mes'      => $serieLucro,
            'acum_fat'       => $acumFat,
            'acum_lucro'     => $acumLucro,
            'vendas_marca'   => $vendasPorMarca,
            'vendas_modelo'  => array_slice($vendasPorModelo, 0, 8, true),
            'estoque_categoria' => $estoquePorCategoria,
            'estoque_faixa'  => $estoquePorFaixa,
        ],
    ];
}

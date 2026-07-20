<?php
/**
 * Central de alertas/pendências do painel administrativo.
 * Calcula automaticamente itens que exigem atenção (vendas sem contrato,
 * férias chegando/vencidas, motos paradas no estoque).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// dias parado no estoque a partir do qual a moto vira pendência
const ALERTA_MOTO_PARADA_DIAS = 60;
// janela (dias) para considerar as férias como "chegando"
const ALERTA_FERIAS_JANELA_DIAS = 30;

/**
 * Retorna os grupos de alertas. Cada grupo:
 *   ['chave','titulo','icone','severidade'('danger'|'warning'|'info'),'total','itens'=>[['texto','url']]]
 * Resultado em cache por requisição.
 */
function admin_alertas(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $grupos = [];
    $hoje = date('Y-m-d');

    /* ---- vendas sem contrato gerado ---- */
    $temContratos = _alerta_tabela_existe('contratos');
    if ($temContratos) {
        $rows = db()->query(
            "SELECT m.id, m.marca, m.modelo, m.cliente_nome, m.numero_venda, m.vendido_em
             FROM motos m
             WHERE m.status = 'vendida'
               AND NOT EXISTS (
                 SELECT 1 FROM contratos c
                 WHERE c.moto_id = m.id AND c.status <> 'cancelado'
               )
             ORDER BY m.vendido_em DESC"
        )->fetchAll();
        if ($rows) {
            $itens = [];
            foreach ($rows as $m) {
                $nome = trim($m['cliente_nome']) !== '' ? $m['cliente_nome'] : 'cliente não informado';
                $extra = $m['numero_venda'] ? ' (venda ' . $m['numero_venda'] . ')' : '';
                $itens[] = [
                    'texto' => trim($m['marca'] . ' ' . $m['modelo']) . ' — ' . $nome . $extra,
                    'url'   => 'contrato-gerar.php?moto_id=' . (int) $m['id'],
                ];
            }
            $grupos[] = [
                'chave' => 'vendas_sem_contrato',
                'titulo' => 'Vendas sem contrato gerado',
                'icone' => '📄',
                'severidade' => 'warning',
                'total' => count($itens),
                'itens' => $itens,
            ];
        }
    }

    /* ---- férias ---- */
    if (_alerta_tabela_existe('rh_ferias')) {
        $limite = date('Y-m-d', strtotime('+' . ALERTA_FERIAS_JANELA_DIAS . ' days'));

        // vencidas: já passou a data fim e não foi concluída
        $vencidas = db()->prepare(
            "SELECT fe.id, fe.data_fim, fu.id fid, fu.nome
             FROM rh_ferias fe JOIN rh_funcionarios fu ON fu.id = fe.funcionario_id
             WHERE fe.status <> 'concluida' AND fe.data_fim IS NOT NULL AND fe.data_fim < ?
             ORDER BY fe.data_fim ASC"
        );
        $vencidas->execute([$hoje]);
        $vencidas = $vencidas->fetchAll();
        if ($vencidas) {
            $itens = [];
            foreach ($vencidas as $fe) {
                $itens[] = [
                    'texto' => $fe['nome'] . ' — terminou em ' . data_br($fe['data_fim']) . ' (marcar como concluída)',
                    'url'   => 'rh-perfil.php?id=' . (int) $fe['fid'] . '#ferias',
                ];
            }
            $grupos[] = [
                'chave' => 'ferias_vencidas',
                'titulo' => 'Férias a regularizar',
                'icone' => '🌴',
                'severidade' => 'danger',
                'total' => count($itens),
                'itens' => $itens,
            ];
        }

        // chegando: início entre hoje e a janela, ainda agendada
        $chegando = db()->prepare(
            "SELECT fe.data_inicio, fu.id fid, fu.nome
             FROM rh_ferias fe JOIN rh_funcionarios fu ON fu.id = fe.funcionario_id
             WHERE fe.status = 'agendada' AND fe.data_inicio IS NOT NULL
               AND fe.data_inicio >= ? AND fe.data_inicio <= ?
             ORDER BY fe.data_inicio ASC"
        );
        $chegando->execute([$hoje, $limite]);
        $chegando = $chegando->fetchAll();
        if ($chegando) {
            $itens = [];
            foreach ($chegando as $fe) {
                $dias = (int) ceil((strtotime($fe['data_inicio']) - strtotime($hoje)) / 86400);
                $quando = $dias <= 0 ? 'hoje' : ($dias === 1 ? 'amanhã' : 'em ' . $dias . ' dias');
                $itens[] = [
                    'texto' => $fe['nome'] . ' — inicia ' . data_br($fe['data_inicio']) . ' (' . $quando . ')',
                    'url'   => 'rh-perfil.php?id=' . (int) $fe['fid'] . '#ferias',
                ];
            }
            $grupos[] = [
                'chave' => 'ferias_chegando',
                'titulo' => 'Férias chegando',
                'icone' => '📅',
                'severidade' => 'info',
                'total' => count($itens),
                'itens' => $itens,
            ];
        }
    }

    /* ---- motos paradas no estoque ---- */
    $limiteData = date('Y-m-d H:i:s', strtotime('-' . ALERTA_MOTO_PARADA_DIAS . ' days'));
    $paradas = db()->prepare(
        "SELECT id, marca, modelo, criado_em FROM motos
         WHERE status = 'disponivel' AND criado_em IS NOT NULL AND criado_em < ?
         ORDER BY criado_em ASC"
    );
    $paradas->execute([$limiteData]);
    $paradas = $paradas->fetchAll();
    if ($paradas) {
        $itens = [];
        foreach ($paradas as $m) {
            $dias = (int) floor((strtotime($hoje) - strtotime(substr($m['criado_em'], 0, 10))) / 86400);
            $itens[] = [
                'texto' => trim($m['marca'] . ' ' . $m['modelo']) . ' — parada há ' . $dias . ' dias',
                'url'   => 'moto-form.php?id=' . (int) $m['id'],
            ];
        }
        $grupos[] = [
            'chave' => 'motos_paradas',
            'titulo' => 'Motos paradas no estoque (+' . ALERTA_MOTO_PARADA_DIAS . ' dias)',
            'icone' => '🏍️',
            'severidade' => 'warning',
            'total' => count($itens),
            'itens' => $itens,
        ];
    }

    $cache = $grupos;
    return $cache;
}

/** Total de itens pendentes (para o badge do menu). */
function admin_alertas_total(): int
{
    $t = 0;
    foreach (admin_alertas() as $g) {
        $t += (int) $g['total'];
    }
    return $t;
}

/** Verifica se uma tabela existe (portável SQLite/MySQL). */
function _alerta_tabela_existe(string $tabela): bool
{
    static $cache = [];
    if (isset($cache[$tabela])) {
        return $cache[$tabela];
    }
    try {
        db()->query('SELECT 1 FROM ' . $tabela . ' LIMIT 1');
        return $cache[$tabela] = true;
    } catch (Throwable $e) {
        return $cache[$tabela] = false;
    }
}

<?php
/**
 * Exporta os dados atuais (SQLite local) para um arquivo .sql pronto
 * para importar no MySQL da hospedagem.
 *
 * Uso (no computador local):  php scripts/exportar-mysql.php
 * Gera: sql/dados-producao.sql
 *
 * Este script é de uso local — NÃO faz parte do pacote de deploy.
 */

require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$saida = __DIR__ . '/../sql/dados-producao.sql';

// Tabelas exportadas, na ordem correta de dependência.
// contratos/rh_* ficam de fora de propósito: são criadas vazias em produção
// pelas funções ensure_*() e não devem levar dados de teste local.
$tabelas = ['motos', 'moto_fotos', 'clientes', 'admin_usuarios'];

$linhas = [];
$linhas[] = '-- ============================================================';
$linhas[] = '--  Skinao Motos - dados para producao (gerado automaticamente)';
$linhas[] = '--  Gerado em: ' . date('d/m/Y H:i');
$linhas[] = '--  Importe DEPOIS de sql/schema.mysql.sql';
$linhas[] = '-- ============================================================';
$linhas[] = '';
$linhas[] = 'SET NAMES utf8mb4;';
$linhas[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$linhas[] = '';

$totais = [];

foreach ($tabelas as $tabela) {
    try {
        $rows = $pdo->query("SELECT * FROM {$tabela}")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $linhas[] = "-- tabela {$tabela} nao encontrada, ignorada";
        continue;
    }

    $totais[$tabela] = count($rows);
    $linhas[] = "-- ---------- {$tabela} (" . count($rows) . " registro(s)) ----------";

    if (!$rows) {
        $linhas[] = '';
        continue;
    }

    $colunas = array_keys($rows[0]);
    $listaCols = '`' . implode('`, `', $colunas) . '`';

    foreach ($rows as $r) {
        $vals = [];
        foreach ($colunas as $c) {
            $v = $r[$c];
            if ($v === null) {
                $vals[] = 'NULL';
            } elseif (is_int($v) || is_float($v)) {
                $vals[] = (string) $v;
            } elseif (is_numeric($v) && !preg_match('/^0\d/', (string) $v)) {
                // numérico puro (evita perder zeros à esquerda, ex.: numero_venda "0001")
                $vals[] = (string) $v;
            } else {
                // Escape seguro para MySQL: o quote() do SQLite só duplica aspas simples
                // e deixaria a barra invertida passar, que o MySQL trataria como escape.
                $vals[] = "'" . str_replace(['\\', "'"], ['\\\\', "''"], (string) $v) . "'";
            }
        }
        $linhas[] = "INSERT IGNORE INTO `{$tabela}` ({$listaCols}) VALUES (" . implode(', ', $vals) . ');';
    }
    $linhas[] = '';
}

$linhas[] = 'SET FOREIGN_KEY_CHECKS = 1;';
$linhas[] = '';

file_put_contents($saida, implode("\n", $linhas));

echo "Arquivo gerado: sql/dados-producao.sql\n";
foreach ($totais as $t => $n) {
    echo "  {$t}: {$n} registro(s)\n";
}
echo 'Tamanho: ' . number_format(filesize($saida) / 1024, 1) . " KB\n";

<?php
/**
 * Importa o estoque real da Skinão Motos (extraído de skinaomotos.com.br) para o banco
 * configurado em includes/config.php (SQLite local ou MySQL de produção).
 *
 * Rode apenas via linha de comando: php scripts/import-skinao.php
 * Substitui todas as motos e fotos existentes pelas informações do arquivo
 * data/import/motos-skinao.json (baixa as fotos originais pela internet).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Este script só pode ser executado via linha de comando.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$dados = json_decode(file_get_contents(__DIR__ . '/../data/import/motos-skinao.json'), true);
if (!$dados) {
    die("Não foi possível ler data/import/motos-skinao.json\n");
}

$pdo = db();
$pdo->exec('DELETE FROM moto_fotos');
$pdo->exec('DELETE FROM motos');

foreach (glob(UPLOADS_DIR . '/*', GLOB_ONLYDIR) as $dir) {
    foreach (glob($dir . '/*') as $arquivo) {
        @unlink($arquivo);
    }
    @rmdir($dir);
}

$contexto = stream_context_create([
    'http' => [
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
        'timeout' => 20,
    ],
]);

$stmtMoto = $pdo->prepare(
    'INSERT INTO motos (marca, modelo, ano, preco, categoria, km, cor, condicao, descricao, destaque, ativo) VALUES (?,?,?,?,?,?,?,?,?,?,1)'
);
$stmtFoto = $pdo->prepare('INSERT INTO moto_fotos (moto_id, arquivo, ordem, capa) VALUES (?,?,?,?)');

$totalMotos = 0;
$totalFotos = 0;

foreach ($dados as $moto) {
    $stmtMoto->execute([
        $moto['marca'],
        $moto['modelo'],
        $moto['ano'],
        $moto['preco'],
        $moto['categoria'],
        $moto['km'],
        $moto['cor'],
        $moto['condicao'],
        $moto['descricao'],
        $moto['destaque'] ? 1 : 0,
    ]);
    $motoId = (int) $pdo->lastInsertId();
    $totalMotos++;

    $dir = UPLOADS_DIR . '/' . $motoId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    foreach ($moto['imagens'] as $ordem => $url) {
        $conteudo = @file_get_contents($url, false, $contexto);
        if ($conteudo === false) {
            echo "  aviso: falha ao baixar $url\n";
            continue;
        }
        $nomeArquivo = bin2hex(random_bytes(8)) . '.jpg';
        file_put_contents($dir . '/' . $nomeArquivo, $conteudo);
        $stmtFoto->execute([$motoId, $nomeArquivo, $ordem, $ordem === 0 ? 1 : 0]);
        $totalFotos++;
    }

    echo "OK #{$motoId} {$moto['marca']} {$moto['modelo']} — " . count($moto['imagens']) . " fotos\n";
}

echo "\nImportação concluída: {$totalMotos} motos, {$totalFotos} fotos.\n";

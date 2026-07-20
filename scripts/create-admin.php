<?php
/**
 * Cria (ou redefine a senha de) um usuário administrativo.
 * Rode via linha de comando: php scripts/create-admin.php usuario senha
 * Não mexe nas motos cadastradas — só na tabela admin_usuarios.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Este script só pode ser executado via linha de comando.');
}

require_once __DIR__ . '/../includes/db.php';

$usuario = $argv[1] ?? null;
$senha = $argv[2] ?? null;

if (!$usuario || !$senha) {
    die("Uso: php scripts/create-admin.php <usuario> <senha>\n");
}

$hash = password_hash($senha, PASSWORD_DEFAULT);
$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM admin_usuarios WHERE usuario = ?');
$stmt->execute([$usuario]);

if ($stmt->fetch()) {
    $pdo->prepare('UPDATE admin_usuarios SET senha_hash = ? WHERE usuario = ?')->execute([$hash, $usuario]);
    echo "Senha atualizada para o usuário '{$usuario}'.\n";
} else {
    $pdo->prepare('INSERT INTO admin_usuarios (usuario, senha_hash) VALUES (?, ?)')->execute([$usuario, $hash]);
    echo "Usuário '{$usuario}' criado com sucesso.\n";
}

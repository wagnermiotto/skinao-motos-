<?php
require_once __DIR__ . '/db.php';

function current_admin_id(): ?int
{
    return $_SESSION['admin_id'] ?? null;
}

function require_login(): void
{
    if (!current_admin_id()) {
        header('Location: login.php');
        exit;
    }
}

function attempt_login(string $usuario, string $senha): bool
{
    $stmt = db()->prepare('SELECT id, senha_hash FROM admin_usuarios WHERE usuario = ?');
    $stmt->execute([$usuario]);
    $row = $stmt->fetch();

    if ($row && password_verify($senha, $row['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_usuario'] = $usuario;
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Sessão expirada ou inválida. Volte e tente novamente.');
    }
}

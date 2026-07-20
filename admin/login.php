<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (current_admin_id()) {
    header('Location: dashboard.php');
    exit;
}

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if (attempt_login($usuario, $senha)) {
        header('Location: dashboard.php');
        exit;
    }
    $erro = 'Usuário ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login administrativo — Skinão Motos</title>
<link rel="icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
<link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
<meta name="theme-color" content="#0a0a0a">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/styles.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body admin-body--center">
  <form class="admin-login" method="post" novalidate>
    <img class="brand__logo" src="../assets/img/logo.png" alt="Skinão Motos" style="margin-bottom:12px;">
    <h1>Área administrativa</h1>
    <?php if ($erro): ?><p class="admin-alert"><?= e($erro) ?></p><?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>Usuário
      <input type="text" name="usuario" autocomplete="username" required autofocus>
    </label>
    <label>Senha
      <input type="password" name="senha" autocomplete="current-password" required>
    </label>
    <button class="btn btn--primary" type="submit" style="width:100%; justify-content:center;">Entrar</button>
  </form>
</body>
</html>

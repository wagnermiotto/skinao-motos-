<?php
/** @var string $pageTitle */
require_once __DIR__ . '/alertas.php';
$__alertasTotal = admin_alertas_total();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Painel') ?> — Skinão Motos</title>
<link rel="icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicon-16.png">
<link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
<meta name="theme-color" content="#0a0a0a">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/styles.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/alertas.css">
<script>
(function () {
  document.documentElement.classList.add('reveal-ready');
  setTimeout(function () { if (!window.Skinao) document.documentElement.classList.remove('reveal-ready'); }, 2500);
})();
</script>
</head>
<body class="admin-body">
<header class="admin-topbar">
  <div class="wrap admin-topbar__row">
    <a class="brand" href="dashboard.php">
      <img class="brand__logo" src="../assets/img/logo.png" alt="Skinão Motos">
      <span class="admin-topbar__tag">admin</span>
    </a>
    <nav class="admin-topbar__nav">
      <a href="painel.php">Dashboard<?php if ($__alertasTotal > 0): ?><span class="nav-alerta-badge" title="<?= (int) $__alertasTotal ?> pendência(s)"><?= (int) $__alertasTotal ?></span><?php endif; ?></a>
      <a href="dashboard.php">Motos</a>
      <a href="contratos.php">Contratos</a>
      <a href="rh.php">RH</a>
      <a href="clientes.php">Clientes</a>
      <a href="../index.php" target="_blank" rel="noopener">Ver site ↗</a>
      <a href="logout.php">Sair</a>
    </nav>
  </div>
</header>
<main class="wrap admin-main">

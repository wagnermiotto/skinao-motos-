<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

csrf_check();
$id = (int) ($_POST['id'] ?? 0);

if ($id) {
    db()->prepare('DELETE FROM motos WHERE id = ?')->execute([$id]);
    // moto_fotos é removida em cascata pela FK; apaga os arquivos físicos também.
    $dir = UPLOADS_DIR . '/' . $id;
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') as $arquivo) {
            @unlink($arquivo);
        }
        @rmdir($dir);
    }
}

header('Location: dashboard.php?msg=excluida');
exit;

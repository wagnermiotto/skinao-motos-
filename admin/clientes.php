<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$erros = [];
$mensagem = $_GET['msg'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $stmt = db()->prepare('SELECT arquivo FROM clientes WHERE id = ?');
            $stmt->execute([$id]);
            $foto = $stmt->fetch();
            if ($foto) {
                @unlink(UPLOADS_CLIENTES_DIR . '/' . $foto['arquivo']);
                db()->prepare('DELETE FROM clientes WHERE id = ?')->execute([$id]);
            }
        }
        header('Location: clientes.php?msg=excluida');
        exit;
    }

    if ($acao === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            db()->prepare('UPDATE clientes SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        }
        header('Location: clientes.php?msg=atualizada');
        exit;
    }

    if ($acao === 'upload') {
        $enviadas = 0;
        try {
            if (!empty($_FILES['fotos']['name'][0])) {
                $ordemBase = (int) (db()->query('SELECT COALESCE(MAX(ordem), 0) m FROM clientes')->fetch()['m']);
                $total = count($_FILES['fotos']['name']);
                for ($i = 0; $i < $total; $i++) {
                    $arquivo = [
                        'name' => $_FILES['fotos']['name'][$i],
                        'type' => $_FILES['fotos']['type'][$i],
                        'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                        'error' => $_FILES['fotos']['error'][$i],
                        'size' => $_FILES['fotos']['size'][$i],
                    ];
                    $salvo = salvar_upload_cliente($arquivo);
                    if ($salvo) {
                        db()->prepare('INSERT INTO clientes (arquivo, ordem, ativo) VALUES (?, ?, 1)')
                            ->execute([$salvo, $ordemBase + $i + 1]);
                        $enviadas++;
                    }
                }
            }
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        }
        if ($enviadas > 0 && !$erros) {
            header('Location: clientes.php?msg=enviada');
            exit;
        }
        if (!$enviadas && !$erros) {
            $erros[] = 'Selecione ao menos uma imagem para enviar.';
        }
    }
}

$clientes = clientes_fotos(false);

$pageTitle = 'Clientes satisfeitos';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1>Clientes Satisfeitos</h1>
  <a class="btn btn--link" href="dashboard.php">← Voltar para as motos</a>
</div>

<?php if ($mensagem === 'enviada'): ?><p class="admin-notice">Foto(s) adicionada(s) com sucesso.</p><?php endif; ?>
<?php if ($mensagem === 'excluida'): ?><p class="admin-notice">Foto excluída.</p><?php endif; ?>
<?php if ($mensagem === 'atualizada'): ?><p class="admin-notice">Visibilidade atualizada.</p><?php endif; ?>
<?php foreach ($erros as $erro): ?><p class="admin-alert"><?= e($erro) ?></p><?php endforeach; ?>

<form class="admin-form" method="post" enctype="multipart/form-data" style="margin-bottom:28px;">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="acao" value="upload">
  <label class="admin-form__full">Adicionar fotos de clientes (JPG, PNG ou WEBP, até 6MB cada)
    <input type="file" name="fotos[]" accept="image/png, image/jpeg, image/webp" multiple required>
  </label>
  <p class="admin-form__label" style="margin:0;">Dica: use fotos boas do cliente ao lado da moto. Elas aparecem na seção "Clientes Satisfeitos" do site.</p>
  <div class="admin-form__actions">
    <button class="btn btn--primary" type="submit">Enviar fotos</button>
  </div>
</form>

<?php if ($clientes): ?>
  <div class="admin-photo-grid" data-reveal>
    <?php foreach ($clientes as $c): ?>
      <div class="admin-photo">
        <img src="<?= e(cliente_foto_url($c['arquivo'])) ?>" alt="">
        <div class="admin-photo__bar">
          <?php if ($c['ativo']): ?>
            <span class="admin-pill admin-pill--ativo">visível</span>
          <?php else: ?>
            <span class="admin-pill admin-pill--inativo">oculta</span>
          <?php endif; ?>
          <div class="admin-photo__actions">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button type="submit" class="admin-photo__link"><?= $c['ativo'] ? 'Ocultar' : 'Mostrar' ?></button>
            </form>
            <form method="post" onsubmit="return confirm('Excluir esta foto? Essa ação não pode ser desfeita.');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button type="submit" class="admin-photo__link admin-photo__link--danger">Excluir</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <p class="admin-table__empty" style="border:1px solid var(--line); border-radius:var(--radius);">Nenhuma foto de cliente cadastrada ainda.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

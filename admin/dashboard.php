<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$mensagem = $_GET['msg'] ?? null;
$motos = db()->query("SELECT * FROM motos ORDER BY criado_em DESC")->fetchAll();

$pageTitle = 'Motos cadastradas';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1>Motos cadastradas</h1>
  <a class="btn btn--primary" href="moto-form.php">+ Cadastrar moto</a>
</div>

<?php if ($mensagem === 'criada'): ?><p class="admin-notice">Moto cadastrada com sucesso.</p><?php endif; ?>
<?php if ($mensagem === 'atualizada'): ?><p class="admin-notice">Moto atualizada com sucesso.</p><?php endif; ?>
<?php if ($mensagem === 'excluida'): ?><p class="admin-notice">Moto excluída.</p><?php endif; ?>

<div class="admin-table-wrap" data-reveal>
<table class="admin-table">
  <thead>
    <tr>
      <th>Foto</th>
      <th>Moto</th>
      <th>Categoria</th>
      <th>Ano</th>
      <th>Km</th>
      <th>Preço</th>
      <th>Status</th>
      <th>Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$motos): ?>
      <tr><td colspan="8" class="admin-table__empty">Nenhuma moto cadastrada ainda.</td></tr>
    <?php endif; ?>
    <?php foreach ($motos as $m): $capa = moto_foto_capa((int) $m['id']); ?>
      <tr>
        <td>
          <?php if ($capa): ?>
            <img class="admin-table__thumb" src="<?= e(moto_foto_url((int) $m['id'], $capa)) ?>" alt="">
          <?php else: ?>
            <span class="admin-table__thumb admin-table__thumb--empty"></span>
          <?php endif; ?>
        </td>
        <td><?= e($m['marca'] . ' ' . $m['modelo']) ?><?php if ($m['destaque']): ?> <span class="admin-pill admin-pill--destaque">destaque</span><?php endif; ?></td>
        <td><?= e(categoria_label($m['categoria'])) ?></td>
        <td><?= e((string) $m['ano']) ?></td>
        <td><?= $m['km'] !== null ? formatar_km((int) $m['km']) : '—' ?></td>
        <td><?= formatar_preco((float) $m['preco']) ?></td>
        <td>
          <?php if ($m['ativo']): ?>
            <span class="admin-pill admin-pill--ativo">visível</span>
          <?php else: ?>
            <span class="admin-pill admin-pill--inativo">oculta</span>
          <?php endif; ?>
          <?php if ($m['condicao'] === 'novo'): ?><span class="admin-pill admin-pill--novo">novo</span><?php endif; ?>
        </td>
        <td class="admin-table__actions">
          <a href="moto-form.php?id=<?= (int) $m['id'] ?>">Editar</a>
          <form method="post" action="delete-moto.php" onsubmit="return confirm('Excluir esta moto e todas as fotos dela? Essa ação não pode ser desfeita.');">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button type="submit" class="admin-table__delete">Excluir</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

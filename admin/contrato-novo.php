<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/contratos.php';
require_login();

$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT * FROM motos WHERE status = 'vendida'";
$params = [];
if ($busca !== '') {
    $sql .= ' AND (cliente_nome LIKE ? OR marca LIKE ? OR modelo LIKE ? OR placa LIKE ? OR numero_venda LIKE ?)';
    $t = '%' . $busca . '%';
    array_push($params, $t, $t, $t, $t, $t);
}
$sql .= ' ORDER BY vendido_em DESC, id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll();

// motos que já têm contrato (não cancelado)
$comContrato = [];
foreach (db()->query("SELECT DISTINCT moto_id FROM contratos WHERE status <> 'cancelado' AND moto_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $mid) {
    $comContrato[(int) $mid] = true;
}

$pageTitle = 'Novo Contrato';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1>Novo Contrato — selecione a venda</h1>
  <a class="btn btn--link" href="contratos.php">← Voltar aos contratos</a>
</div>

<p class="dash-hint">Escolha a venda realizada. O sistema preenche automaticamente todos os dados disponíveis (comprador, moto, vendedor, valor, pagamento) e pede só o que faltar.</p>

<form class="admin-form ct-filtros" method="get" action="contrato-novo.php">
  <div class="admin-form__grid">
    <label>Buscar venda
      <input type="text" name="busca" value="<?= e($busca) ?>" placeholder="Cliente, moto, placa, nº da venda…">
    </label>
  </div>
  <div class="admin-form__actions">
    <button class="btn btn--primary" type="submit">Buscar</button>
    <a class="btn btn--link" href="contrato-novo.php">Limpar</a>
    <a class="btn btn--link" href="contrato-gerar.php?moto_id=0">Criar contrato em branco</a>
  </div>
</form>

<?php if (!$vendas): ?>
  <p class="dash-hint">
    Nenhuma venda encontrada. Marque uma moto como <strong>Vendida</strong> na aba
    <a href="dashboard.php">Motos</a> (preenchendo os dados da venda) para gerar o contrato automaticamente.
  </p>
<?php else: ?>
  <div class="ct-venda-grid">
    <?php foreach ($vendas as $m): ?>
      <div class="ct-venda-card">
        <h3><?= e($m['marca'] . ' ' . $m['modelo']) ?></h3>
        <div class="ct-venda-meta">
          <?= e($m['cliente_nome'] ?: 'Cliente não informado') ?><br>
          Placa: <?= e($m['placa'] ?: '—') ?> · Ano: <?= e((string) $m['ano']) ?><br>
          Venda: <?= e(data_br($m['vendido_em'])) ?>
          <?php if (!empty($m['numero_venda'])): ?> · Nº <?= e($m['numero_venda']) ?><?php endif; ?>
        </div>
        <div class="ct-venda-valor"><?= e(formatar_reais((float) $m['valor_venda'])) ?></div>
        <?php if (!empty($comContrato[(int) $m['id']])): ?>
          <div class="ct-tem-contrato">✓ Já possui contrato</div>
        <?php endif; ?>
        <a class="btn btn--primary" href="contrato-gerar.php?moto_id=<?= (int) $m['id'] ?>">
          Gerar Contrato
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<link rel="stylesheet" href="../assets/css/contrato-admin.css">

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

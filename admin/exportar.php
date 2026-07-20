<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/xlsx.php';
require_login();

// ---------------- geração da planilha ----------------
if (isset($_GET['gerar'])) {
    $where = [];
    $params = [];

    $status = $_GET['status'] ?? 'vendida';
    if ($status !== 'todas' && array_key_exists($status, STATUS_MOTO)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $periodo = $_GET['periodo'] ?? 'mes';
    if ($periodo === 'mes') {
        $where[] = 'vendido_em >= ? AND vendido_em < ?';
        $params[] = date('Y-m-01 00:00:00');
        $params[] = date('Y-m-01 00:00:00', strtotime('first day of next month'));
    } elseif ($periodo === 'personalizado') {
        $de = trim($_GET['de'] ?? '');
        $ate = trim($_GET['ate'] ?? '');
        if ($de !== '') { $where[] = 'vendido_em >= ?'; $params[] = $de . ' 00:00:00'; }
        if ($ate !== '') { $where[] = 'vendido_em <= ?'; $params[] = $ate . ' 23:59:59'; }
    }

    if (!empty($_GET['vendedor'])) { $where[] = 'vendedor = ?'; $params[] = $_GET['vendedor']; }
    if (!empty($_GET['forma'])) { $where[] = 'forma_pagamento = ?'; $params[] = $_GET['forma']; }

    $sql = 'SELECT * FROM motos';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY vendido_em DESC, criado_em DESC, id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $motos = $stmt->fetchAll();

    $colunas = [
        ['t' => 'Nº venda',          'w' => 10, 'tipo' => 'texto'],
        ['t' => 'Data da venda',     'w' => 13, 'tipo' => 'texto'],
        ['t' => 'Data cadastro',     'w' => 13, 'tipo' => 'texto'],
        ['t' => 'Cliente',           'w' => 24, 'tipo' => 'texto'],
        ['t' => 'CPF/CNPJ',          'w' => 18, 'tipo' => 'texto'],
        ['t' => 'Telefone',          'w' => 16, 'tipo' => 'texto'],
        ['t' => 'Cidade',            'w' => 16, 'tipo' => 'texto'],
        ['t' => 'UF',                'w' => 6,  'tipo' => 'texto'],
        ['t' => 'Vendedor',          'w' => 16, 'tipo' => 'texto'],
        ['t' => 'Marca',             'w' => 14, 'tipo' => 'texto'],
        ['t' => 'Modelo',            'w' => 22, 'tipo' => 'texto'],
        ['t' => 'Ano',               'w' => 8,  'tipo' => 'numero'],
        ['t' => 'Cor',               'w' => 12, 'tipo' => 'texto'],
        ['t' => 'Placa',             'w' => 10, 'tipo' => 'texto'],
        ['t' => 'Chassi',            'w' => 20, 'tipo' => 'texto'],
        ['t' => 'Valor de compra',   'w' => 16, 'tipo' => 'dinheiro'],
        ['t' => 'Valor de venda',    'w' => 16, 'tipo' => 'dinheiro'],
        ['t' => 'Lucro bruto',       'w' => 16, 'tipo' => 'dinheiro'],
        ['t' => 'Forma de pagamento','w' => 18, 'tipo' => 'texto'],
        ['t' => 'Status',            'w' => 12, 'tipo' => 'texto'],
        ['t' => 'Observações',       'w' => 32, 'tipo' => 'texto'],
    ];

    $linhas = [];
    $totVenda = 0.0; $totCompra = 0.0; $totLucro = 0.0; $qtd = 0;
    foreach ($motos as $m) {
        $lucro = moto_lucro_bruto($m);
        $linhas[] = [
            $m['numero_venda'],
            data_br($m['vendido_em']),
            data_br($m['criado_em']),
            $m['cliente_nome'],
            $m['cliente_documento'],
            $m['cliente_telefone'],
            $m['cliente_cidade'],
            $m['cliente_estado'],
            $m['vendedor'],
            $m['marca'],
            $m['modelo'],
            (int) $m['ano'],
            $m['cor'],
            $m['placa'],
            $m['chassi'],
            (float) $m['valor_compra'],
            (float) $m['valor_venda'],
            $lucro,
            $m['forma_pagamento'],
            status_label($m['status']),
            $m['venda_obs'] ?? '',
        ];
        $totVenda += (float) $m['valor_venda'];
        $totCompra += (float) $m['valor_compra'];
        $totLucro += $lucro;
        $qtd++;
    }

    $ticket = $qtd ? $totVenda / $qtd : 0.0;
    $totais = [
        ['label' => 'Total vendido',    'valor' => $totVenda,  'tipo' => 'dinheiro'],
        ['label' => 'Total de compras', 'valor' => $totCompra, 'tipo' => 'dinheiro'],
        ['label' => 'Lucro bruto',      'valor' => $totLucro,  'tipo' => 'dinheiro'],
        ['label' => 'Motos vendidas',   'valor' => $qtd,       'tipo' => 'numero'],
        ['label' => 'Ticket médio',     'valor' => $ticket,    'tipo' => 'dinheiro'],
        ['label' => 'Exportado em',     'valor' => date('d/m/Y H:i'), 'tipo' => 'texto'],
    ];

    $bin = xlsx_gerar($colunas, $linhas, $totais, 'Vendas');
    $nome = 'skinao-vendas-' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    echo $bin;
    exit;
}

// ---------------- formulário de filtros ----------------
$vendedores = db()->query("SELECT DISTINCT vendedor FROM motos WHERE vendedor <> '' ORDER BY vendedor")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Exportar para Excel';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1>Exportar para Excel</h1>
  <a class="btn btn--link" href="painel.php">← Voltar ao dashboard</a>
</div>

<p class="dash-hint">Gera uma planilha <strong>.xlsx</strong> pronta para o fechamento mensal, com cabeçalhos destacados, valores em R$ e datas no padrão brasileiro. Escolha os filtros e clique em gerar.</p>

<form class="admin-form" method="get" action="exportar.php" data-reveal>
  <input type="hidden" name="gerar" value="1">
  <div class="admin-form__grid">
    <label>Período
      <select name="periodo" id="expPeriodo">
        <option value="mes">Mês atual</option>
        <option value="personalizado">Período personalizado</option>
        <option value="tudo">Todas as vendas</option>
      </select>
    </label>
    <label>Status
      <select name="status">
        <option value="vendida">Vendidas</option>
        <option value="todas">Todas as motos</option>
        <option value="disponivel">Disponíveis</option>
        <option value="reservada">Reservadas</option>
      </select>
    </label>
    <label id="expDe" style="display:none;">De
      <input type="date" name="de">
    </label>
    <label id="expAte" style="display:none;">Até
      <input type="date" name="ate">
    </label>
    <label>Vendedor
      <select name="vendedor">
        <option value="">Todos</option>
        <?php foreach ($vendedores as $v): ?>
          <option value="<?= e($v) ?>"><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Forma de pagamento
      <select name="forma">
        <option value="">Todas</option>
        <?php foreach (FORMAS_PAGAMENTO as $forma): ?>
          <option value="<?= e($forma) ?>"><?= e($forma) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="admin-form__actions">
    <button class="btn btn--primary" type="submit">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M12 3v12M8 11l4 4 4-4M4 21h16"/></svg>
      Gerar planilha
    </button>
  </div>
</form>

<script>
(function () {
  var periodo = document.getElementById('expPeriodo');
  var de = document.getElementById('expDe');
  var ate = document.getElementById('expAte');
  function sync() {
    var mostra = periodo.value === 'personalizado';
    de.style.display = mostra ? '' : 'none';
    ate.style.display = mostra ? '' : 'none';
  }
  periodo.addEventListener('change', sync);
  sync();
})();
</script>

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

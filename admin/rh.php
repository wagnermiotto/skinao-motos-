<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/rh.php';
require_login();

$aba = $_GET['aba'] ?? 'dashboard';
if (!in_array($aba, ['dashboard', 'funcionarios'], true)) {
    $aba = 'dashboard';
}
$mensagem = $_GET['msg'] ?? null;

/* ---------------- exclusão de funcionário ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['acao'] ?? '') === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            // remove documentos físicos
            $dir = UPLOADS_RH_DIR . '/' . $id;
            if (is_dir($dir)) {
                foreach (glob($dir . '/*') as $f) { @unlink($f); }
                @rmdir($dir);
            }
            db()->prepare('DELETE FROM rh_documentos WHERE funcionario_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM rh_ferias WHERE funcionario_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM rh_salario_historico WHERE funcionario_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM rh_funcionarios WHERE id = ?')->execute([$id]);
        }
        header('Location: rh.php?aba=funcionarios&msg=excluido');
        exit;
    }
}

/* ---------------- indicadores (dashboard) ---------------- */
$totFunc = (int) db()->query('SELECT COUNT(*) FROM rh_funcionarios')->fetchColumn();
$porStatus = [];
foreach (db()->query("SELECT status, COUNT(*) c FROM rh_funcionarios GROUP BY status")->fetchAll() as $r) {
    $porStatus[$r['status']] = (int) $r['c'];
}
$ativos = $porStatus['ativo'] ?? 0;
$folhaAtiva = (float) db()->query("SELECT COALESCE(SUM(salario),0) FROM rh_funcionarios WHERE status <> 'desligado'")->fetchColumn();
$mediaSalario = $ativos > 0 ? ((float) db()->query("SELECT COALESCE(SUM(salario),0) FROM rh_funcionarios WHERE status='ativo'")->fetchColumn()) / $ativos : 0.0;

$porDepto = db()->query("SELECT CASE WHEN departamento='' THEN 'Não definido' ELSE departamento END dep, COUNT(*) c FROM rh_funcionarios GROUP BY dep ORDER BY c DESC")->fetchAll();
$porCargo = db()->query("SELECT CASE WHEN cargo='' THEN 'Não definido' ELSE cargo END cg, COUNT(*) c FROM rh_funcionarios GROUP BY cg ORDER BY c DESC")->fetchAll();

// próximas férias (agendadas/em andamento)
$proxFerias = db()->query(
    "SELECT fe.*, fu.nome FROM rh_ferias fe
     JOIN rh_funcionarios fu ON fu.id = fe.funcionario_id
     WHERE fe.status <> 'concluida'
     ORDER BY fe.data_inicio ASC LIMIT 6"
)->fetchAll();

/* ---------------- listagem de funcionários ---------------- */
$busca = trim($_GET['busca'] ?? '');
$fCargo = trim($_GET['cargo'] ?? '');
$fDepto = trim($_GET['departamento'] ?? '');
$fStatus = trim($_GET['status'] ?? '');

$where = [];
$params = [];
if ($busca !== '') {
    $where[] = '(nome LIKE ? OR cpf LIKE ? OR email LIKE ? OR telefone LIKE ?)';
    $t = '%' . $busca . '%';
    array_push($params, $t, $t, $t, $t);
}
if ($fCargo !== '') { $where[] = 'cargo = ?'; $params[] = $fCargo; }
if ($fDepto !== '') { $where[] = 'departamento = ?'; $params[] = $fDepto; }
if ($fStatus !== '' && isset(RH_STATUS[$fStatus])) { $where[] = 'status = ?'; $params[] = $fStatus; }

$sql = 'SELECT * FROM rh_funcionarios';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= " ORDER BY CASE status WHEN 'ativo' THEN 0 WHEN 'ferias' THEN 1 WHEN 'afastado' THEN 2 ELSE 3 END, nome";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll();

$cargos = db()->query("SELECT DISTINCT cargo FROM rh_funcionarios WHERE cargo <> '' ORDER BY cargo")->fetchAll(PDO::FETCH_COLUMN);
$deptos = db()->query("SELECT DISTINCT departamento FROM rh_funcionarios WHERE departamento <> '' ORDER BY departamento")->fetchAll(PDO::FETCH_COLUMN);

$msgTexto = [
    'criado'    => 'Funcionário cadastrado com sucesso!',
    'atualizado' => 'Dados atualizados.',
    'excluido'  => 'Funcionário excluído.',
];

$pageTitle = 'RH';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1>👥 Recursos Humanos</h1>
  <a class="btn btn--primary" href="rh-funcionario.php">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg>
    Novo funcionário
  </a>
</div>

<?php if ($mensagem && isset($msgTexto[$mensagem])): ?>
  <div class="admin-notice"><?= e($msgTexto[$mensagem]) ?></div>
<?php endif; ?>

<nav class="ct-tabs">
  <a href="rh.php?aba=dashboard" class="<?= $aba === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
  <a href="rh.php?aba=funcionarios" class="<?= $aba === 'funcionarios' ? 'is-active' : '' ?>">Funcionários <span class="ct-badge"><?= $totFunc ?></span></a>
</nav>

<?php if ($aba === 'dashboard'): ?>
  <?php if ($totFunc === 0): ?>
    <p class="dash-hint">Nenhum funcionário cadastrado ainda. Clique em <strong>Novo funcionário</strong> para começar.</p>
  <?php else: ?>
    <div class="rh-kpis">
      <div class="rh-kpi"><span class="rh-kpi__label">Funcionários</span><span class="rh-kpi__value"><?= $totFunc ?></span></div>
      <div class="rh-kpi"><span class="rh-kpi__label">Ativos</span><span class="rh-kpi__value rh-kpi__value--green"><?= $ativos ?></span></div>
      <div class="rh-kpi"><span class="rh-kpi__label">Em férias</span><span class="rh-kpi__value"><?= $porStatus['ferias'] ?? 0 ?></span></div>
      <div class="rh-kpi"><span class="rh-kpi__label">Folha (mensal)</span><span class="rh-kpi__value"><?= e(formatar_reais($folhaAtiva)) ?></span></div>
      <div class="rh-kpi"><span class="rh-kpi__label">Salário médio</span><span class="rh-kpi__value"><?= e(formatar_reais($mediaSalario)) ?></span></div>
    </div>

    <div class="rh-charts">
      <div class="rh-chart-card">
        <h3>Por departamento</h3>
        <canvas id="chartDepto"></canvas>
      </div>
      <div class="rh-chart-card">
        <h3>Por status</h3>
        <canvas id="chartStatus"></canvas>
      </div>
      <div class="rh-chart-card">
        <h3>Por cargo</h3>
        <canvas id="chartCargo"></canvas>
      </div>
    </div>

    <div class="rh-chart-card">
      <h3>Próximas férias</h3>
      <?php if (!$proxFerias): ?>
        <p class="dash-hint">Nenhuma férias agendada.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>Funcionário</th><th>Início</th><th>Fim</th><th>Dias</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($proxFerias as $f): ?>
              <tr>
                <td><?= e($f['nome']) ?></td>
                <td><?= e(data_br($f['data_inicio'])) ?></td>
                <td><?= e(data_br($f['data_fim'])) ?></td>
                <td><?= (int) $f['dias'] ?></td>
                <td><span class="admin-pill admin-pill--novo"><?= e(rh_ferias_status_label($f['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
      (function () {
        if (!window.Chart) return;
        Chart.defaults.color = '#a4a4a4';
        Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
        var red = '#dc2626', reds = ['#dc2626','#f83b3b','#ff6b6b','#b91c1c','#7f1d1d','#fca5a5','#ef4444'];

        new Chart(document.getElementById('chartDepto'), {
          type: 'bar',
          data: {
            labels: <?= json_encode(array_column($porDepto, 'dep'), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{ label: 'Funcionários', data: <?= json_encode(array_map('intval', array_column($porDepto, 'c'))) ?>, backgroundColor: red, borderRadius: 6 }]
          },
          options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255,255,255,.06)' } }, x: { grid: { display: false } } } }
        });

        new Chart(document.getElementById('chartStatus'), {
          type: 'doughnut',
          data: {
            labels: <?= json_encode(array_map(fn($k) => RH_STATUS[$k] ?? $k, array_keys($porStatus)), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{ data: <?= json_encode(array_values($porStatus)) ?>, backgroundColor: reds, borderColor: '#111', borderWidth: 2 }]
          },
          options: { plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
        });

        new Chart(document.getElementById('chartCargo'), {
          type: 'bar',
          data: {
            labels: <?= json_encode(array_column($porCargo, 'cg'), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{ label: 'Funcionários', data: <?= json_encode(array_map('intval', array_column($porCargo, 'c'))) ?>, backgroundColor: '#f83b3b', borderRadius: 6 }]
          },
          options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255,255,255,.06)' } }, y: { grid: { display: false } } } }
        });
      })();
    </script>
  <?php endif; ?>

<?php else: /* ---------------- funcionários ---------------- */ ?>
  <form class="admin-form ct-filtros" method="get" action="rh.php">
    <input type="hidden" name="aba" value="funcionarios">
    <div class="admin-form__grid">
      <label>Buscar <input type="text" name="busca" value="<?= e($busca) ?>" placeholder="Nome, CPF, e-mail, telefone…"></label>
      <label>Cargo
        <select name="cargo"><option value="">Todos</option>
          <?php foreach ($cargos as $c): ?><option value="<?= e($c) ?>" <?= $fCargo === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Departamento
        <select name="departamento"><option value="">Todos</option>
          <?php foreach ($deptos as $d): ?><option value="<?= e($d) ?>" <?= $fDepto === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Status
        <select name="status"><option value="">Todos</option>
          <?php foreach (RH_STATUS as $k => $v): ?><option value="<?= e($k) ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="admin-form__actions">
      <button class="btn btn--primary" type="submit">Filtrar</button>
      <a class="btn btn--link" href="rh.php?aba=funcionarios">Limpar</a>
    </div>
  </form>

  <?php if (!$funcionarios): ?>
    <p class="dash-hint">Nenhum funcionário encontrado.</p>
  <?php else: ?>
    <div class="rh-grid">
      <?php foreach ($funcionarios as $f): ?>
        <div class="rh-card">
          <a class="rh-card__link" href="rh-perfil.php?id=<?= (int) $f['id'] ?>">
            <div class="rh-avatar">
              <?php if (!empty($f['foto'])): ?>
                <img src="<?= e(rh_foto_url($f['foto'])) ?>" alt="<?= e($f['nome']) ?>">
              <?php else: ?>
                <span><?= e(rh_iniciais($f['nome'])) ?></span>
              <?php endif; ?>
            </div>
            <div class="rh-card__info">
              <h3><?= e($f['nome']) ?></h3>
              <p class="rh-card__cargo"><?= e($f['cargo'] ?: 'Cargo não definido') ?><?= $f['departamento'] ? ' · ' . e($f['departamento']) : '' ?></p>
              <span class="rh-status rh-status--<?= e($f['status']) ?>"><?= e(rh_status_label($f['status'])) ?></span>
            </div>
          </a>
          <div class="rh-card__actions">
            <a class="ct-icon-btn" href="rh-perfil.php?id=<?= (int) $f['id'] ?>" title="Ver perfil">👁️</a>
            <a class="ct-icon-btn" href="rh-funcionario.php?id=<?= (int) $f['id'] ?>" title="Editar">✏️</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Excluir <?= e($f['nome']) ?> e todos os seus registros (documentos, férias, salários)?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acao" value="excluir">
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
              <button class="ct-icon-btn ct-icon-btn--danger" type="submit" title="Excluir">🗑️</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<link rel="stylesheet" href="../assets/css/contrato-admin.css">
<link rel="stylesheet" href="../assets/css/rh.css">

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

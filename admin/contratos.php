<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/contratos.php';
require_login();

$aba = $_GET['aba'] ?? 'historico';
$abasValidas = ['historico', 'cancelados', 'modelos', 'config'];
if (!in_array($aba, $abasValidas, true)) {
    $aba = 'historico';
}
$erros = [];
$mensagem = $_GET['msg'] ?? null;

/* ---------------- ações ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cancelar') {
        $id = (int) ($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        if ($id) {
            db()->prepare("UPDATE contratos SET status='cancelado', cancelado_em=?, cancelado_motivo=? WHERE id=?")
                ->execute([date('Y-m-d H:i:s'), $motivo, $id]);
        }
        header('Location: contratos.php?msg=cancelado');
        exit;
    }

    if ($acao === 'reativar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            db()->prepare("UPDATE contratos SET status='emitido', cancelado_em=NULL, cancelado_motivo=NULL WHERE id=?")
                ->execute([$id]);
        }
        header('Location: contratos.php?msg=reativado');
        exit;
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            db()->prepare('DELETE FROM contratos WHERE id = ?')->execute([$id]);
        }
        header('Location: contratos.php?aba=' . ($_POST['aba'] ?? 'historico') . '&msg=excluido');
        exit;
    }

    if ($acao === 'duplicar') {
        $id = (int) ($_POST['id'] ?? 0);
        $orig = null;
        if ($id) {
            $stmt = db()->prepare('SELECT * FROM contratos WHERE id = ?');
            $stmt->execute([$id]);
            $orig = $stmt->fetch();
        }
        if ($orig) {
            $numero = contrato_proximo_numero();
            db()->prepare(
                'INSERT INTO contratos (numero, modelo_id, moto_id, status, dados, corpo_html, cliente_nome, moto_desc, placa, valor, vendedor, forma_pagamento, data_contrato)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $numero, $orig['modelo_id'], $orig['moto_id'], 'emitido', $orig['dados'], $orig['corpo_html'],
                $orig['cliente_nome'], $orig['moto_desc'], $orig['placa'], $orig['valor'],
                $orig['vendedor'], $orig['forma_pagamento'], date('Y-m-d'),
            ]);
            $novoId = (int) db()->lastInsertId();
            header('Location: contrato-gerar.php?id=' . $novoId . '&msg=duplicado');
            exit;
        }
        header('Location: contratos.php');
        exit;
    }

    if ($acao === 'salvar_config') {
        $campos = [
            'empresa_razao', 'empresa_fantasia', 'empresa_cnpj', 'empresa_ie',
            'empresa_endereco', 'empresa_numero', 'empresa_bairro', 'empresa_cidade',
            'empresa_uf', 'empresa_cep', 'empresa_telefones', 'empresa_email',
            'testemunha1_nome', 'testemunha1_cpf', 'testemunha2_nome', 'testemunha2_cpf',
        ];
        foreach ($campos as $c) {
            config_set($c, trim($_POST[$c] ?? ''));
        }
        header('Location: contratos.php?aba=config&msg=config_salva');
        exit;
    }
}

/* ---------------- dados para a listagem ---------------- */
$busca = trim($_GET['busca'] ?? '');
$de = trim($_GET['de'] ?? '');
$ate = trim($_GET['ate'] ?? '');
$fVendedor = trim($_GET['vendedor'] ?? '');

$where = [];
$params = [];
$where[] = $aba === 'cancelados' ? "status = 'cancelado'" : "status <> 'cancelado'";
if ($busca !== '') {
    $where[] = '(cliente_nome LIKE ? OR numero LIKE ? OR placa LIKE ? OR moto_desc LIKE ?)';
    $t = '%' . $busca . '%';
    array_push($params, $t, $t, $t, $t);
}
if ($de !== '') { $where[] = 'data_contrato >= ?'; $params[] = $de; }
if ($ate !== '') { $where[] = 'data_contrato <= ?'; $params[] = $ate; }
if ($fVendedor !== '') { $where[] = 'vendedor = ?'; $params[] = $fVendedor; }

$contratos = [];
if ($aba === 'historico' || $aba === 'cancelados') {
    $sql = 'SELECT * FROM contratos WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $contratos = $stmt->fetchAll();
}
$vendedores = db()->query("SELECT DISTINCT vendedor FROM contratos WHERE vendedor <> '' ORDER BY vendedor")->fetchAll(PDO::FETCH_COLUMN);
$totalEmitidos = (int) db()->query("SELECT COUNT(*) FROM contratos WHERE status <> 'cancelado'")->fetchColumn();
$totalCancelados = (int) db()->query("SELECT COUNT(*) FROM contratos WHERE status = 'cancelado'")->fetchColumn();

$modelos = db()->query('SELECT * FROM contrato_modelos ORDER BY id')->fetchAll();
$cfg = config_all();

$msgTexto = [
    'cancelado' => 'Contrato cancelado.',
    'reativado' => 'Contrato reativado.',
    'excluido'  => 'Contrato excluído.',
    'config_salva' => 'Configurações salvas com sucesso.',
    'gerado'    => 'Contrato gerado com sucesso!',
    'atualizado' => 'Contrato atualizado.',
];

$pageTitle = 'Contratos';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1>📄 Contratos</h1>
  <a class="btn btn--primary" href="contrato-novo.php">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg>
    Novo Contrato
  </a>
</div>

<?php if ($mensagem && isset($msgTexto[$mensagem])): ?>
  <div class="admin-notice"><?= e($msgTexto[$mensagem]) ?></div>
<?php endif; ?>

<nav class="ct-tabs">
  <a href="contratos.php?aba=historico" class="<?= $aba === 'historico' ? 'is-active' : '' ?>">Histórico <span class="ct-badge"><?= $totalEmitidos ?></span></a>
  <a href="contrato-novo.php">Novo Contrato</a>
  <a href="contratos.php?aba=modelos" class="<?= $aba === 'modelos' ? 'is-active' : '' ?>">Modelos</a>
  <a href="contratos.php?aba=cancelados" class="<?= $aba === 'cancelados' ? 'is-active' : '' ?>">Cancelados <span class="ct-badge"><?= $totalCancelados ?></span></a>
  <a href="contratos.php?aba=config" class="<?= $aba === 'config' ? 'is-active' : '' ?>">Configurações</a>
</nav>

<?php if ($aba === 'historico' || $aba === 'cancelados'): ?>
  <form class="admin-form ct-filtros" method="get" action="contratos.php">
    <input type="hidden" name="aba" value="<?= e($aba) ?>">
    <div class="admin-form__grid">
      <label>Buscar
        <input type="text" name="busca" value="<?= e($busca) ?>" placeholder="Cliente, nº, placa, moto…">
      </label>
      <label>De <input type="date" name="de" value="<?= e($de) ?>"></label>
      <label>Até <input type="date" name="ate" value="<?= e($ate) ?>"></label>
      <label>Vendedor
        <select name="vendedor">
          <option value="">Todos</option>
          <?php foreach ($vendedores as $v): ?>
            <option value="<?= e($v) ?>" <?= $fVendedor === $v ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="admin-form__actions">
      <button class="btn btn--primary" type="submit">Filtrar</button>
      <a class="btn btn--link" href="contratos.php?aba=<?= e($aba) ?>">Limpar</a>
    </div>
  </form>

  <?php if (!$contratos): ?>
    <p class="dash-hint">Nenhum contrato <?= $aba === 'cancelados' ? 'cancelado' : '' ?> encontrado. Clique em <strong>Novo Contrato</strong> para gerar o primeiro.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table ct-tabela">
        <thead>
          <tr>
            <th>Número</th><th>Cliente</th><th>Moto</th><th>Placa</th>
            <th>Data</th><th>Valor</th><th>Vendedor</th><th>Pagamento</th><th>Status</th><th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contratos as $c): ?>
            <tr>
              <td class="ct-mono"><?= e($c['numero']) ?></td>
              <td><?= e($c['cliente_nome']) ?></td>
              <td><?= e($c['moto_desc']) ?></td>
              <td class="ct-mono"><?= e($c['placa']) ?></td>
              <td><?= e(data_br($c['data_contrato'])) ?></td>
              <td><?= e(formatar_reais((float) $c['valor'])) ?></td>
              <td><?= e($c['vendedor']) ?></td>
              <td><?= e($c['forma_pagamento']) ?></td>
              <td>
                <span class="admin-pill admin-pill--<?= $c['status'] === 'cancelado' ? 'inativo' : 'ativo' ?>">
                  <?= e(CONTRATO_STATUS[$c['status']] ?? $c['status']) ?>
                </span>
              </td>
              <td class="ct-acoes">
                <a class="ct-icon-btn" href="contrato-ver.php?id=<?= (int) $c['id'] ?>" title="Visualizar / Imprimir / PDF" target="_blank">👁️</a>
                <a class="ct-icon-btn" href="contrato-ver.php?id=<?= (int) $c['id'] ?>&fmt=word" title="Baixar Word">📝</a>
                <a class="ct-icon-btn" href="contrato-gerar.php?id=<?= (int) $c['id'] ?>" title="Editar">✏️</a>
                <form method="post" style="display:inline" onsubmit="return true;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="duplicar">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="ct-icon-btn" type="submit" title="Duplicar">📑</button>
                </form>
                <?php if ($c['status'] === 'cancelado'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="reativar">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button class="ct-icon-btn" type="submit" title="Reativar">♻️</button>
                  </form>
                <?php else: ?>
                  <button class="ct-icon-btn" type="button" title="Cancelar" onclick="ctCancelar(<?= (int) $c['id'] ?>)">🚫</button>
                <?php endif; ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Excluir definitivamente o contrato <?= e($c['numero']) ?>?');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="aba" value="<?= e($aba) ?>">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="ct-icon-btn ct-icon-btn--danger" type="submit" title="Excluir">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form id="ctCancelForm" method="post" style="display:none">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acao" value="cancelar">
      <input type="hidden" name="id" id="ctCancelId">
      <input type="hidden" name="motivo" id="ctCancelMotivo">
    </form>
    <script>
      function ctCancelar(id) {
        var motivo = prompt('Motivo do cancelamento (opcional):', '');
        if (motivo === null) return;
        document.getElementById('ctCancelId').value = id;
        document.getElementById('ctCancelMotivo').value = motivo;
        document.getElementById('ctCancelForm').submit();
      }
    </script>
  <?php endif; ?>

<?php elseif ($aba === 'modelos'): ?>
  <p class="dash-hint">Biblioteca de modelos de contrato. O <strong>Modelo Padrão</strong> é usado automaticamente ao gerar um contrato a partir de uma venda. Novos modelos (consignação, garantia, recibo, procuração…) poderão ser adicionados aqui.</p>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Modelo</th><th>Identificador</th><th>Status</th><th>Criado em</th></tr></thead>
      <tbody>
        <?php foreach ($modelos as $m): ?>
          <tr>
            <td><strong><?= e($m['nome']) ?></strong></td>
            <td class="ct-mono"><?= e($m['slug']) ?></td>
            <td><span class="admin-pill admin-pill--<?= $m['ativo'] ? 'ativo' : 'inativo' ?>"><?= $m['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td><?= e(data_br($m['criado_em'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($aba === 'config'): ?>
  <p class="dash-hint">Dados da empresa (VENDEDORA) e testemunhas padrão usados automaticamente em todos os contratos.</p>
  <form class="admin-form" method="post" action="contratos.php?aba=config">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="acao" value="salvar_config">
    <fieldset class="admin-fin admin-form__full">
      <legend>Empresa (Vendedora)</legend>
      <div class="admin-form__grid">
        <label>Razão social <input type="text" name="empresa_razao" value="<?= e($cfg['empresa_razao'] ?? '') ?>"></label>
        <label>Nome fantasia <input type="text" name="empresa_fantasia" value="<?= e($cfg['empresa_fantasia'] ?? '') ?>"></label>
        <label>CNPJ <input type="text" name="empresa_cnpj" value="<?= e($cfg['empresa_cnpj'] ?? '') ?>"></label>
        <label>Inscrição Estadual <input type="text" name="empresa_ie" value="<?= e($cfg['empresa_ie'] ?? '') ?>"></label>
        <label>Endereço <input type="text" name="empresa_endereco" value="<?= e($cfg['empresa_endereco'] ?? '') ?>"></label>
        <label>Número <input type="text" name="empresa_numero" value="<?= e($cfg['empresa_numero'] ?? '') ?>"></label>
        <label>Bairro <input type="text" name="empresa_bairro" value="<?= e($cfg['empresa_bairro'] ?? '') ?>"></label>
        <label>Cidade <input type="text" name="empresa_cidade" value="<?= e($cfg['empresa_cidade'] ?? '') ?>"></label>
        <label>UF <input type="text" name="empresa_uf" maxlength="2" value="<?= e($cfg['empresa_uf'] ?? '') ?>"></label>
        <label>CEP <input type="text" name="empresa_cep" value="<?= e($cfg['empresa_cep'] ?? '') ?>"></label>
        <label>Telefones <input type="text" name="empresa_telefones" value="<?= e($cfg['empresa_telefones'] ?? '') ?>"></label>
        <label>E-mail <input type="email" name="empresa_email" value="<?= e($cfg['empresa_email'] ?? '') ?>"></label>
      </div>
    </fieldset>
    <fieldset class="admin-fin admin-form__full">
      <legend>Testemunhas padrão</legend>
      <div class="admin-form__grid">
        <label>Testemunha 1 — Nome <input type="text" name="testemunha1_nome" value="<?= e($cfg['testemunha1_nome'] ?? '') ?>"></label>
        <label>Testemunha 1 — CPF <input type="text" name="testemunha1_cpf" value="<?= e($cfg['testemunha1_cpf'] ?? '') ?>"></label>
        <label>Testemunha 2 — Nome <input type="text" name="testemunha2_nome" value="<?= e($cfg['testemunha2_nome'] ?? '') ?>"></label>
        <label>Testemunha 2 — CPF <input type="text" name="testemunha2_cpf" value="<?= e($cfg['testemunha2_cpf'] ?? '') ?>"></label>
      </div>
    </fieldset>
    <div class="admin-form__actions">
      <button class="btn btn--primary" type="submit">Salvar configurações</button>
    </div>
  </form>
<?php endif; ?>

<link rel="stylesheet" href="../assets/css/contrato-admin.css">

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

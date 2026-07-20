<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/rh.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$f = $id ? rh_funcionario($id) : null;
if (!$f) {
    header('Location: rh.php?aba=funcionarios');
    exit;
}
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add_ferias') {
        $ini = trim($_POST['data_inicio'] ?? '');
        $fim = trim($_POST['data_fim'] ?? '');
        $dias = (int) ($_POST['dias'] ?? 0);
        if ($dias === 0 && $ini && $fim) {
            $di = date_create($ini); $df = date_create($fim);
            if ($di && $df) { $dias = (int) $di->diff($df)->days + 1; }
        }
        $st = isset(RH_FERIAS_STATUS[$_POST['status'] ?? '']) ? $_POST['status'] : 'agendada';
        db()->prepare('INSERT INTO rh_ferias (funcionario_id, data_inicio, data_fim, dias, status, observacoes) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$id, $ini ?: null, $fim ?: null, $dias, $st, trim($_POST['observacoes'] ?? '')]);
        header('Location: rh-perfil.php?id=' . $id . '#ferias'); exit;
    }
    if ($acao === 'del_ferias') {
        db()->prepare('DELETE FROM rh_ferias WHERE id = ? AND funcionario_id = ?')->execute([(int) $_POST['item_id'], $id]);
        header('Location: rh-perfil.php?id=' . $id . '#ferias'); exit;
    }

    if ($acao === 'add_salario') {
        $tipo = isset(RH_SALARIO_TIPOS[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : 'aumento';
        $valor = parse_dinheiro($_POST['valor'] ?? '0');
        db()->prepare('INSERT INTO rh_salario_historico (funcionario_id, tipo, valor, data_referencia, descricao) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, $tipo, $valor, trim($_POST['data_referencia'] ?? '') ?: date('Y-m-d'), trim($_POST['descricao'] ?? '')]);
        // aumento/salario_base atualiza o salário atual do funcionário
        if (in_array($tipo, ['aumento', 'salario_base'], true) && $valor > 0) {
            db()->prepare('UPDATE rh_funcionarios SET salario = ? WHERE id = ?')->execute([$valor, $id]);
        }
        header('Location: rh-perfil.php?id=' . $id . '#salario'); exit;
    }
    if ($acao === 'del_salario') {
        db()->prepare('DELETE FROM rh_salario_historico WHERE id = ? AND funcionario_id = ?')->execute([(int) $_POST['item_id'], $id]);
        header('Location: rh-perfil.php?id=' . $id . '#salario'); exit;
    }

    if ($acao === 'add_documento') {
        try {
            if (!empty($_FILES['arquivo']['name'])) {
                $arq = salvar_upload_rh_doc($_FILES['arquivo'], $id);
                if ($arq) {
                    $tipo = in_array($_POST['tipo'] ?? '', RH_DOC_TIPOS, true) ? $_POST['tipo'] : 'Outro';
                    db()->prepare('INSERT INTO rh_documentos (funcionario_id, tipo, titulo, arquivo) VALUES (?, ?, ?, ?)')
                        ->execute([$id, $tipo, trim($_POST['titulo'] ?? ''), $arq]);
                }
            } else {
                $erros[] = 'Selecione um arquivo para enviar.';
            }
        } catch (RuntimeException $ex) {
            $erros[] = $ex->getMessage();
        }
        if (!$erros) { header('Location: rh-perfil.php?id=' . $id . '#documentos'); exit; }
    }
    if ($acao === 'del_documento') {
        $docId = (int) $_POST['item_id'];
        $stmt = db()->prepare('SELECT arquivo FROM rh_documentos WHERE id = ? AND funcionario_id = ?');
        $stmt->execute([$docId, $id]);
        if ($arq = $stmt->fetchColumn()) {
            @unlink(UPLOADS_RH_DIR . '/' . $id . '/' . $arq);
            db()->prepare('DELETE FROM rh_documentos WHERE id = ?')->execute([$docId]);
        }
        header('Location: rh-perfil.php?id=' . $id . '#documentos'); exit;
    }
}

$ferias = db()->prepare('SELECT * FROM rh_ferias WHERE funcionario_id = ? ORDER BY data_inicio DESC');
$ferias->execute([$id]); $ferias = $ferias->fetchAll();
$docs = db()->prepare('SELECT * FROM rh_documentos WHERE funcionario_id = ? ORDER BY criado_em DESC');
$docs->execute([$id]); $docs = $docs->fetchAll();
$salarios = db()->prepare('SELECT * FROM rh_salario_historico WHERE funcionario_id = ? ORDER BY data_referencia DESC, id DESC');
$salarios->execute([$id]); $salarios = $salarios->fetchAll();

$msg = $_GET['msg'] ?? null;
$msgTexto = ['criado' => 'Funcionário cadastrado!', 'atualizado' => 'Dados atualizados.'];

$pageTitle = $f['nome'];
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1>Perfil do funcionário</h1>
  <div style="display:flex;gap:10px">
    <a class="btn btn--primary" href="rh-funcionario.php?id=<?= $id ?>">✏️ Editar</a>
    <a class="btn btn--link" href="rh.php?aba=funcionarios">← Funcionários</a>
  </div>
</div>

<?php if ($msg && isset($msgTexto[$msg])): ?><div class="admin-notice"><?= e($msgTexto[$msg]) ?></div><?php endif; ?>
<?php if (!empty($_GET['fotoerro'])): ?><div class="admin-alert"><div>Funcionário salvo, mas a foto não pôde ser enviada (formato ou tamanho inválido).</div></div><?php endif; ?>
<?php if ($erros): ?><div class="admin-alert"><?php foreach ($erros as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="rh-perfil-head">
  <div class="rh-avatar rh-avatar--lg">
    <?php if (!empty($f['foto'])): ?>
      <img src="<?= e(rh_foto_url($f['foto'])) ?>" alt="<?= e($f['nome']) ?>">
    <?php else: ?>
      <span><?= e(rh_iniciais($f['nome'])) ?></span>
    <?php endif; ?>
  </div>
  <div class="rh-perfil-info">
    <h2><?= e($f['nome']) ?> <span class="rh-status rh-status--<?= e($f['status']) ?>"><?= e(rh_status_label($f['status'])) ?></span></h2>
    <p class="rh-perfil-cargo"><?= e($f['cargo'] ?: 'Cargo não definido') ?><?= $f['departamento'] ? ' · ' . e($f['departamento']) : '' ?></p>
    <div class="rh-perfil-meta">
      <?php if ($f['telefone']): ?><span>📞 <?= e($f['telefone']) ?></span><?php endif; ?>
      <?php if ($f['email']): ?><span>✉️ <?= e($f['email']) ?></span><?php endif; ?>
      <?php if (rh_idade($f['data_nascimento']) !== null): ?><span>🎂 <?= rh_idade($f['data_nascimento']) ?> anos</span><?php endif; ?>
      <?php if ($f['data_admissao']): ?><span>📅 <?= e(rh_tempo_casa($f['data_admissao'])) ?> de casa</span><?php endif; ?>
    </div>
  </div>
  <div class="rh-perfil-salario">
    <span class="rh-kpi__label">Salário atual</span>
    <span class="rh-kpi__value"><?= e(formatar_reais((float) $f['salario'])) ?></span>
    <?php if ((float) $f['comissao_percent'] > 0): ?><span class="rh-perfil-comissao">+ <?= e(formatar_percent((float) $f['comissao_percent'])) ?> comissão</span><?php endif; ?>
  </div>
</div>

<div class="rh-cols">
  <!-- coluna dados -->
  <section class="rh-panel">
    <h3>Dados cadastrais</h3>
    <dl class="rh-dl">
      <?php
      $linhas = [
        'CPF' => $f['cpf'], 'RG' => $f['rg'], 'PIS' => $f['pis'],
        'Nascimento' => data_br($f['data_nascimento']), 'Gênero' => $f['genero'], 'Estado civil' => $f['estado_civil'],
        'Admissão' => data_br($f['data_admissao']), 'Demissão' => data_br($f['data_demissao']),
        'Endereço' => trim($f['endereco'] . ($f['numero'] ? ', ' . $f['numero'] : '')),
        'Bairro' => $f['bairro'], 'Cidade/UF' => trim($f['cidade'] . ($f['estado'] ? '/' . $f['estado'] : '')), 'CEP' => $f['cep'],
        'Banco' => $f['banco'], 'Agência' => $f['agencia'], 'Conta' => $f['conta'], 'Chave PIX' => $f['chave_pix'],
      ];
      foreach ($linhas as $rot => $val): if (trim((string) $val) === '') continue; ?>
        <dt><?= e($rot) ?></dt><dd><?= e($val) ?></dd>
      <?php endforeach; ?>
    </dl>
    <?php if (trim((string) $f['observacoes']) !== ''): ?>
      <h3 style="margin-top:18px">Observações</h3>
      <p class="rh-obs"><?= nl2br(e($f['observacoes'])) ?></p>
    <?php endif; ?>
  </section>

  <!-- coluna registros -->
  <div class="rh-registros">
    <!-- Férias -->
    <section class="rh-panel" id="ferias">
      <h3>Férias</h3>
      <?php if ($ferias): ?>
        <table class="admin-table">
          <thead><tr><th>Início</th><th>Fim</th><th>Dias</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($ferias as $fe): ?>
              <tr>
                <td><?= e(data_br($fe['data_inicio'])) ?></td>
                <td><?= e(data_br($fe['data_fim'])) ?></td>
                <td><?= (int) $fe['dias'] ?></td>
                <td><span class="admin-pill admin-pill--novo"><?= e(rh_ferias_status_label($fe['status'])) ?></span></td>
                <td>
                  <form method="post" onsubmit="return confirm('Remover este período de férias?');" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="del_ferias"><input type="hidden" name="item_id" value="<?= (int) $fe['id'] ?>">
                    <button class="ct-icon-btn ct-icon-btn--danger" type="submit" title="Remover">🗑️</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p class="dash-hint">Nenhum período de férias registrado.</p><?php endif; ?>

      <details class="rh-add">
        <summary>+ Registrar férias</summary>
        <form method="post" class="admin-form">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="add_ferias">
          <div class="admin-form__grid">
            <label>Início <input type="date" name="data_inicio" required></label>
            <label>Fim <input type="date" name="data_fim"></label>
            <label>Dias (auto se vazio) <input type="number" name="dias" min="0"></label>
            <label>Status
              <select name="status"><?php foreach (RH_FERIAS_STATUS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
            </label>
            <label class="admin-form__full">Observações <input type="text" name="observacoes"></label>
          </div>
          <div class="admin-form__actions"><button class="btn btn--primary" type="submit">Adicionar</button></div>
        </form>
      </details>
    </section>

    <!-- Documentos -->
    <section class="rh-panel" id="documentos">
      <h3>Documentos</h3>
      <?php if ($docs): ?>
        <table class="admin-table">
          <thead><tr><th>Tipo</th><th>Título</th><th>Enviado em</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($docs as $d): ?>
              <tr>
                <td><?= e($d['tipo']) ?></td>
                <td><a href="<?= e(rh_doc_url($id, $d['arquivo'])) ?>" target="_blank" rel="noopener"><?= e($d['titulo'] ?: $d['arquivo']) ?></a></td>
                <td><?= e(data_br($d['criado_em'])) ?></td>
                <td>
                  <a class="ct-icon-btn" href="<?= e(rh_doc_url($id, $d['arquivo'])) ?>" target="_blank" title="Abrir">👁️</a>
                  <form method="post" onsubmit="return confirm('Excluir este documento?');" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="del_documento"><input type="hidden" name="item_id" value="<?= (int) $d['id'] ?>">
                    <button class="ct-icon-btn ct-icon-btn--danger" type="submit" title="Excluir">🗑️</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p class="dash-hint">Nenhum documento enviado.</p><?php endif; ?>

      <details class="rh-add">
        <summary>+ Enviar documento</summary>
        <form method="post" class="admin-form" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="add_documento">
          <div class="admin-form__grid">
            <label>Tipo
              <select name="tipo"><?php foreach (RH_DOC_TIPOS as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
            </label>
            <label>Título <input type="text" name="titulo" placeholder="Ex.: RG frente e verso"></label>
            <label class="admin-form__full">Arquivo (PDF, JPG, PNG) <input type="file" name="arquivo" accept=".pdf,image/*" required></label>
          </div>
          <div class="admin-form__actions"><button class="btn btn--primary" type="submit">Enviar</button></div>
        </form>
      </details>
    </section>

    <!-- Histórico salarial -->
    <section class="rh-panel" id="salario">
      <h3>Histórico salarial / financeiro</h3>
      <?php if ($salarios): ?>
        <table class="admin-table">
          <thead><tr><th>Data</th><th>Tipo</th><th>Valor</th><th>Descrição</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($salarios as $s): ?>
              <tr>
                <td><?= e(data_br($s['data_referencia'])) ?></td>
                <td><span class="rh-tag rh-tag--<?= e($s['tipo']) ?>"><?= e(rh_salario_tipo_label($s['tipo'])) ?></span></td>
                <td class="<?= $s['tipo'] === 'desconto' ? 'rh-neg' : '' ?>"><?= ($s['tipo'] === 'desconto' ? '- ' : '') . e(formatar_reais((float) $s['valor'])) ?></td>
                <td><?= e($s['descricao']) ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('Excluir este lançamento?');" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="del_salario"><input type="hidden" name="item_id" value="<?= (int) $s['id'] ?>">
                    <button class="ct-icon-btn ct-icon-btn--danger" type="submit" title="Excluir">🗑️</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?><p class="dash-hint">Nenhum lançamento salarial.</p><?php endif; ?>

      <details class="rh-add">
        <summary>+ Novo lançamento</summary>
        <form method="post" class="admin-form">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="add_salario">
          <div class="admin-form__grid">
            <label>Tipo
              <select name="tipo"><?php foreach (RH_SALARIO_TIPOS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
            </label>
            <label>Valor (R$) <input type="text" name="valor" inputmode="decimal" placeholder="0,00" required></label>
            <label>Data de referência <input type="date" name="data_referencia" value="<?= e(date('Y-m-d')) ?>"></label>
            <label class="admin-form__full">Descrição <input type="text" name="descricao" placeholder="Ex.: Aumento anual, bônus de metas…"></label>
          </div>
          <div class="admin-form__actions"><button class="btn btn--primary" type="submit">Adicionar</button></div>
          <p class="dash-hint" style="margin:6px 0 0">Lançamentos de <strong>aumento</strong> ou <strong>salário base</strong> atualizam o salário atual automaticamente.</p>
        </form>
      </details>
    </section>
  </div>
</div>

<link rel="stylesheet" href="../assets/css/contrato-admin.css">
<link rel="stylesheet" href="../assets/css/rh.css">

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

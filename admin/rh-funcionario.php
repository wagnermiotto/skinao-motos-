<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/rh.php';
require_login();

$erros = [];
$id = (int) ($_GET['id'] ?? 0);

$f = [
    'nome' => '', 'cargo' => '', 'departamento' => '', 'cpf' => '', 'rg' => '', 'pis' => '',
    'data_nascimento' => '', 'genero' => '', 'estado_civil' => '', 'data_admissao' => '', 'data_demissao' => '',
    'salario' => 0, 'comissao_percent' => 0, 'telefone' => '', 'email' => '',
    'endereco' => '', 'numero' => '', 'bairro' => '', 'cidade' => '', 'estado' => '', 'cep' => '',
    'banco' => '', 'agencia' => '', 'conta' => '', 'chave_pix' => '',
    'status' => 'ativo', 'observacoes' => '', 'foto' => '',
];

if ($id > 0) {
    $existente = rh_funcionario($id);
    if (!$existente) {
        header('Location: rh.php?aba=funcionarios');
        exit;
    }
    $f = array_merge($f, $existente);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $txt = fn($k, $up = false) => ($v = trim((string) ($_POST[$k] ?? ''))) === '' ? '' : ($up ? mb_strtoupper($v) : $v);
    $f['nome'] = $txt('nome');
    $f['cargo'] = $txt('cargo');
    $f['departamento'] = $txt('departamento');
    $f['cpf'] = $txt('cpf');
    $f['rg'] = $txt('rg');
    $f['pis'] = $txt('pis');
    $f['data_nascimento'] = $txt('data_nascimento');
    $f['genero'] = $txt('genero');
    $f['estado_civil'] = $txt('estado_civil');
    $f['data_admissao'] = $txt('data_admissao');
    $f['data_demissao'] = $txt('data_demissao');
    $f['salario'] = max(0, parse_dinheiro($_POST['salario'] ?? '0'));
    $f['comissao_percent'] = max(0, (float) str_replace(',', '.', $_POST['comissao_percent'] ?? '0'));
    $f['telefone'] = $txt('telefone');
    $f['email'] = $txt('email');
    $f['endereco'] = $txt('endereco');
    $f['numero'] = $txt('numero');
    $f['bairro'] = $txt('bairro');
    $f['cidade'] = $txt('cidade');
    $f['estado'] = mb_strtoupper($txt('estado'));
    $f['cep'] = $txt('cep');
    $f['banco'] = $txt('banco');
    $f['agencia'] = $txt('agencia');
    $f['conta'] = $txt('conta');
    $f['chave_pix'] = $txt('chave_pix');
    $f['status'] = isset(RH_STATUS[$_POST['status'] ?? '']) ? $_POST['status'] : 'ativo';
    $f['observacoes'] = trim((string) ($_POST['observacoes'] ?? ''));

    if ($f['nome'] === '') { $erros[] = 'Informe o nome do funcionário.'; }

    if (!$erros) {
        $campos = ['nome', 'cargo', 'departamento', 'cpf', 'rg', 'pis', 'genero', 'estado_civil',
            'salario', 'comissao_percent', 'telefone', 'email', 'endereco', 'numero', 'bairro',
            'cidade', 'estado', 'cep', 'banco', 'agencia', 'conta', 'chave_pix', 'status', 'observacoes'];
        // datas podem ser NULL
        $datas = ['data_nascimento', 'data_admissao', 'data_demissao'];

        if ($id > 0) {
            $set = [];
            $vals = [];
            foreach ($campos as $c) { $set[] = "$c = ?"; $vals[] = $f[$c]; }
            foreach ($datas as $c) { $set[] = "$c = ?"; $vals[] = $f[$c] !== '' ? $f[$c] : null; }
            $vals[] = $id;
            db()->prepare('UPDATE rh_funcionarios SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
            $funcId = $id;
        } else {
            $cols = array_merge($campos, $datas);
            $ph = implode(', ', array_fill(0, count($cols), '?'));
            $vals = [];
            foreach ($campos as $c) { $vals[] = $f[$c]; }
            foreach ($datas as $c) { $vals[] = $f[$c] !== '' ? $f[$c] : null; }
            db()->prepare('INSERT INTO rh_funcionarios (' . implode(', ', $cols) . ') VALUES (' . $ph . ')')->execute($vals);
            $funcId = (int) db()->lastInsertId();

            // registra o salário base no histórico
            if ($f['salario'] > 0) {
                db()->prepare('INSERT INTO rh_salario_historico (funcionario_id, tipo, valor, data_referencia, descricao) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$funcId, 'salario_base', $f['salario'], $f['data_admissao'] ?: date('Y-m-d'), 'Salário base inicial']);
            }
        }

        // upload de foto (opcional)
        try {
            if (!empty($_FILES['foto']['name'])) {
                $foto = salvar_upload_rh_foto($_FILES['foto'], $funcId);
                if ($foto) {
                    // remove foto antiga
                    if (!empty($f['foto']) && $f['foto'] !== $foto) {
                        @unlink(UPLOADS_RH_DIR . '/' . $f['foto']);
                    }
                    db()->prepare('UPDATE rh_funcionarios SET foto = ? WHERE id = ?')->execute([$foto, $funcId]);
                }
            }
        } catch (RuntimeException $ex) {
            // salvou o funcionário mas a foto falhou — segue com aviso na próxima tela
            header('Location: rh-perfil.php?id=' . $funcId . '&fotoerro=1');
            exit;
        }

        header('Location: rh-perfil.php?id=' . $funcId . '&msg=' . ($id > 0 ? 'atualizado' : 'criado'));
        exit;
    }
}

$pageTitle = $id > 0 ? 'Editar funcionário' : 'Novo funcionário';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1><?= $id > 0 ? 'Editar funcionário' : 'Novo funcionário' ?></h1>
  <a class="btn btn--link" href="<?= $id > 0 ? 'rh-perfil.php?id=' . $id : 'rh.php?aba=funcionarios' ?>">← Voltar</a>
</div>

<?php if ($erros): ?>
  <div class="admin-alert"><?php foreach ($erros as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" action="rh-funcionario.php<?= $id > 0 ? '?id=' . $id : '' ?>" class="admin-form" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <fieldset class="admin-fin admin-form__full">
    <legend>Dados pessoais</legend>
    <div class="admin-form__grid">
      <label class="admin-form__full">Nome completo * <input type="text" name="nome" value="<?= e($f['nome']) ?>" required></label>
      <label>CPF <input type="text" name="cpf" value="<?= e($f['cpf']) ?>"></label>
      <label>RG <input type="text" name="rg" value="<?= e($f['rg']) ?>"></label>
      <label>PIS <input type="text" name="pis" value="<?= e($f['pis']) ?>"></label>
      <label>Data de nascimento <input type="date" name="data_nascimento" value="<?= e($f['data_nascimento']) ?>"></label>
      <label>Gênero
        <select name="genero"><option value="">—</option>
          <?php foreach (RH_GENEROS as $g): ?><option value="<?= e($g) ?>" <?= $f['genero'] === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Estado civil
        <select name="estado_civil"><option value="">—</option>
          <?php foreach (RH_ESTADOS_CIVIS as $ec): ?><option value="<?= e($ec) ?>" <?= $f['estado_civil'] === $ec ? 'selected' : '' ?>><?= e($ec) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Foto <input type="file" name="foto" accept="image/*"></label>
    </div>
  </fieldset>

  <fieldset class="admin-fin admin-form__full">
    <legend>Cargo e contrato</legend>
    <div class="admin-form__grid">
      <label>Cargo <input type="text" name="cargo" value="<?= e($f['cargo']) ?>" placeholder="Ex.: Vendedor"></label>
      <label>Departamento <input type="text" name="departamento" value="<?= e($f['departamento']) ?>" placeholder="Ex.: Vendas"></label>
      <label>Status
        <select name="status">
          <?php foreach (RH_STATUS as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Data de admissão <input type="date" name="data_admissao" value="<?= e($f['data_admissao']) ?>"></label>
      <label>Data de demissão <input type="date" name="data_demissao" value="<?= e($f['data_demissao']) ?>"></label>
      <label>Salário (R$) <input type="text" name="salario" inputmode="decimal" value="<?= $f['salario'] > 0 ? e(valor_input((float) $f['salario'])) : '' ?>" placeholder="0,00"></label>
      <label>Comissão (%) <input type="text" name="comissao_percent" inputmode="decimal" value="<?= $f['comissao_percent'] > 0 ? e(rtrim(rtrim(number_format((float) $f['comissao_percent'], 2, ',', ''), '0'), ',')) : '' ?>" placeholder="0"></label>
    </div>
  </fieldset>

  <fieldset class="admin-fin admin-form__full">
    <legend>Contato e endereço</legend>
    <div class="admin-form__grid">
      <label>Telefone <input type="text" name="telefone" value="<?= e($f['telefone']) ?>"></label>
      <label>E-mail <input type="email" name="email" value="<?= e($f['email']) ?>"></label>
      <label>Endereço <input type="text" name="endereco" value="<?= e($f['endereco']) ?>"></label>
      <label>Número <input type="text" name="numero" value="<?= e($f['numero']) ?>"></label>
      <label>Bairro <input type="text" name="bairro" value="<?= e($f['bairro']) ?>"></label>
      <label>Cidade <input type="text" name="cidade" value="<?= e($f['cidade']) ?>"></label>
      <label>UF <input type="text" name="estado" maxlength="2" value="<?= e($f['estado']) ?>"></label>
      <label>CEP <input type="text" name="cep" value="<?= e($f['cep']) ?>"></label>
    </div>
  </fieldset>

  <fieldset class="admin-fin admin-form__full">
    <legend>Dados bancários</legend>
    <div class="admin-form__grid">
      <label>Banco <input type="text" name="banco" value="<?= e($f['banco']) ?>"></label>
      <label>Agência <input type="text" name="agencia" value="<?= e($f['agencia']) ?>"></label>
      <label>Conta <input type="text" name="conta" value="<?= e($f['conta']) ?>"></label>
      <label>Chave PIX <input type="text" name="chave_pix" value="<?= e($f['chave_pix']) ?>"></label>
    </div>
  </fieldset>

  <fieldset class="admin-fin admin-form__full">
    <legend>Observações</legend>
    <div class="admin-form__grid">
      <label class="admin-form__full">Observações <textarea name="observacoes" rows="3"><?= e($f['observacoes']) ?></textarea></label>
    </div>
  </fieldset>

  <div class="admin-form__actions">
    <button class="btn btn--primary" type="submit"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar funcionário' ?></button>
    <a class="btn btn--link" href="rh.php?aba=funcionarios">Cancelar</a>
  </div>
</form>

<link rel="stylesheet" href="../assets/css/rh.css">

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

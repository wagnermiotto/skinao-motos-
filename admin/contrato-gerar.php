<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/contratos.php';
require_once __DIR__ . '/../includes/rh.php';
require_login();

$rhVendedores = rh_vendedores();

$erros = [];
$contratoId = (int) ($_GET['id'] ?? 0);
$motoId = isset($_GET['moto_id']) ? (int) $_GET['moto_id'] : null;
$modo = 'form'; // form | preview

// modelo ativo (padrão)
$modelo = db()->query("SELECT * FROM contrato_modelos WHERE slug='compra-venda-padrao' LIMIT 1")->fetch();
if (!$modelo) {
    die('Modelo padrão não encontrado.');
}

/* ---------------- carrega dados iniciais ---------------- */
$dados = array_fill_keys(contrato_todas_chaves(), '');
$registro = null; // contrato existente

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? 'preview';
    $contratoId = (int) ($_POST['contrato_id'] ?? 0);
    $motoId = ($_POST['moto_id'] ?? '') === '' ? null : (int) $_POST['moto_id'];

    foreach (contrato_todas_chaves() as $chave) {
        $dados[$chave] = trim((string) ($_POST[$chave] ?? ''));
    }

    if ($acao === 'salvar') {
        // validação mínima
        if ($dados['comprador_nome'] === '') { $erros[] = 'Informe o nome do comprador.'; }
        if ($dados['moto_marca'] === '' || $dados['moto_modelo'] === '') { $erros[] = 'Informe marca e modelo da moto.'; }

        if (!$erros) {
            $corpo = contrato_render($modelo['corpo_html'], $dados);
            $valor = parse_dinheiro($dados['valor_venda'] ?? '0');
            $motoDesc = trim($dados['moto_marca'] . ' ' . $dados['moto_modelo']);
            $dataContrato = !empty($dados['data_venda']) ? substr($dados['data_venda'], 0, 10) : date('Y-m-d');
            $forma = contrato_forma_pagamento($dados);
            $jsonDados = json_encode($dados, JSON_UNESCAPED_UNICODE);

            if ($contratoId > 0) {
                db()->prepare(
                    'UPDATE contratos SET dados=?, corpo_html=?, cliente_nome=?, moto_desc=?, placa=?, valor=?, vendedor=?, forma_pagamento=?, data_contrato=?, status=? WHERE id=?'
                )->execute([
                    $jsonDados, $corpo, $dados['comprador_nome'], $motoDesc, $dados['moto_placa'], $valor,
                    $dados['vendedor_nome'], $forma, $dataContrato, 'emitido', $contratoId,
                ]);
                header('Location: contrato-ver.php?id=' . $contratoId . '&novo=1');
                exit;
            }

            $numero = contrato_proximo_numero();
            db()->prepare(
                'INSERT INTO contratos (numero, modelo_id, moto_id, status, dados, corpo_html, cliente_nome, moto_desc, placa, valor, vendedor, forma_pagamento, data_contrato)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $numero, (int) $modelo['id'], $motoId ?: null, 'emitido', $jsonDados, $corpo,
                $dados['comprador_nome'], $motoDesc, $dados['moto_placa'], $valor,
                $dados['vendedor_nome'], $forma, $dataContrato,
            ]);
            $novoId = (int) db()->lastInsertId();
            header('Location: contrato-ver.php?id=' . $novoId . '&novo=1');
            exit;
        }
    }

    if ($acao === 'preview' && !$erros) {
        $modo = 'preview';
    }
} else {
    // GET: edição de contrato existente
    if ($contratoId > 0) {
        $stmt = db()->prepare('SELECT * FROM contratos WHERE id = ?');
        $stmt->execute([$contratoId]);
        $registro = $stmt->fetch();
        if ($registro && $registro['dados']) {
            $salvos = json_decode($registro['dados'], true) ?: [];
            foreach ($dados as $k => $_) {
                if (isset($salvos[$k])) { $dados[$k] = $salvos[$k]; }
            }
            $motoId = $registro['moto_id'] !== null ? (int) $registro['moto_id'] : null;
        }
    } elseif ($motoId) {
        // novo a partir da venda
        $stmt = db()->prepare('SELECT * FROM motos WHERE id = ?');
        $stmt->execute([$motoId]);
        $moto = $stmt->fetch();
        if ($moto) {
            $dados = contrato_dados_da_venda($moto);
        }
    }

    // vínculo automático com o RH: se o vendedor da venda for um funcionário
    // cadastrado, puxa CPF e comissão que ainda estejam em branco.
    if (trim($dados['vendedor_nome']) !== '') {
        $func = rh_funcionario_por_nome($dados['vendedor_nome']);
        if ($func) {
            if (trim($dados['vendedor_cpf']) === '') { $dados['vendedor_cpf'] = $func['cpf']; }
            if (trim($dados['vendedor_comissao']) === '' && (float) $func['comissao_percent'] > 0) {
                $dados['vendedor_comissao'] = rtrim(rtrim(number_format((float) $func['comissao_percent'], 2, ',', ''), '0'), ',');
            }
        }
    }
}

$pageTitle = ($contratoId > 0 ? 'Editar contrato' : 'Gerar contrato');
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1><?= $contratoId > 0 ? 'Editar contrato' : 'Gerar contrato' ?></h1>
  <a class="btn btn--link" href="contratos.php">← Voltar aos contratos</a>
</div>

<?php if ($erros): ?>
  <div class="admin-alert"><?php foreach ($erros as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($modo === 'preview'): ?>
  <?php $corpo = contrato_render($modelo['corpo_html'], $dados); ?>
  <p class="dash-hint">Confira o contrato abaixo. Se estiver tudo certo, clique em <strong>Confirmar e gerar</strong>. Para ajustar, volte a editar.</p>
  <div class="ct-preview-wrap">
    <div class="ct-paper"><?= $corpo ?></div>
  </div>
  <form method="post" action="contrato-gerar.php" class="ct-gen-actions">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="contrato_id" value="<?= (int) $contratoId ?>">
    <input type="hidden" name="moto_id" value="<?= $motoId === null ? '' : (int) $motoId ?>">
    <?php foreach (contrato_todas_chaves() as $chave): ?>
      <input type="hidden" name="<?= e($chave) ?>" value="<?= e($dados[$chave]) ?>">
    <?php endforeach; ?>
    <button class="btn btn--primary" type="submit" name="acao" value="salvar">✓ Confirmar e gerar</button>
    <button class="btn btn--link" type="submit" name="acao" value="editar" formnovalidate>← Voltar a editar</button>
  </form>
  <link rel="stylesheet" href="../assets/css/contrato.css">
  <link rel="stylesheet" href="../assets/css/contrato-admin.css">
  <?php require __DIR__ . '/../includes/admin_layout_bottom.php'; exit; ?>
<?php endif; ?>

<p class="dash-hint">Campos destacados em <span class="ct-help-vazio">amarelo</span> estão vazios — preencha o que for necessário. O restante já veio da venda.</p>

<form method="post" action="contrato-gerar.php" class="admin-form ct-gen-layout" id="ctForm">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="contrato_id" value="<?= (int) $contratoId ?>">
  <input type="hidden" name="moto_id" value="<?= $motoId === null ? '' : (int) $motoId ?>">

  <?php foreach (contrato_campos_definicao() as $grupoKey => $grupo): ?>
    <fieldset class="admin-fin admin-form__full ct-gen-grupo">
      <legend><?= e($grupo['titulo']) ?></legend>

      <?php if ($grupoKey === 'vendedor'): ?>
        <div class="admin-form__grid">
          <label class="admin-form__full">Selecionar do RH
            <?php if ($rhVendedores): ?>
              <select id="rhVendedorSelect">
                <option value="">— escolher funcionário / digitar manualmente —</option>
                <?php foreach ($rhVendedores as $rv): ?>
                  <option
                    value="<?= (int) $rv['id'] ?>"
                    data-nome="<?= e($rv['nome']) ?>"
                    data-cpf="<?= e($rv['cpf']) ?>"
                    data-comissao="<?= e(rtrim(rtrim(number_format((float) $rv['comissao_percent'], 2, ',', ''), '0'), ',')) ?>"
                    <?= mb_strtolower(trim($dados['vendedor_nome'])) === mb_strtolower(trim($rv['nome'])) ? 'selected' : '' ?>
                  ><?= e($rv['nome']) ?><?= $rv['cargo'] ? ' — ' . e($rv['cargo']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <span class="ct-help-vazio" style="display:block;margin-top:4px">Nenhum funcionário no RH. <a href="rh-funcionario.php" style="color:inherit;text-decoration:underline">Cadastrar</a> ou preencha manualmente abaixo.</span>
            <?php endif; ?>
          </label>
        </div>
      <?php endif; ?>

      <div class="admin-form__grid">
        <?php foreach ($grupo['campos'] as $chave => $def):
            $tipo = $def['tipo'] ?? 'text';
            $val = $dados[$chave] ?? '';
            $vazio = trim((string) $val) === '';
            $req = !empty($def['req']);
            $classes = [];
            if ($vazio) { $classes[] = 'ct-campo-vazio'; }
            if ($req) { $classes[] = 'ct-campo-req'; }
            $cls = $classes ? ' class="' . implode(' ', $classes) . '"' : '';
        ?>
          <?php if ($tipo === 'textarea'): ?>
            <label class="admin-form__full<?= $vazio ? ' ct-campo-vazio' : '' ?><?= $req ? ' ct-campo-req' : '' ?>"><?= e($def['label']) ?>
              <textarea name="<?= e($chave) ?>" rows="3"><?= e($val) ?></textarea>
            </label>
          <?php else: ?>
            <label<?= $cls ?>><?= e($def['label']) ?>
              <input
                type="<?= $tipo === 'date' ? 'date' : 'text' ?>"
                name="<?= e($chave) ?>"
                value="<?= e($val) ?>"
                <?= $tipo === 'dinheiro' ? 'inputmode="decimal" placeholder="0,00"' : '' ?>
                <?= $tipo === 'numero' ? 'inputmode="numeric"' : '' ?>
                <?= $chave === 'comprador_uf' ? 'maxlength="2"' : '' ?>
              >
            </label>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <div class="ct-gen-actions">
    <button class="btn btn--primary" type="submit" name="acao" value="preview">
      👁️ Pré-visualizar
    </button>
    <button class="btn" type="submit" name="acao" value="salvar">
      ✓ Gerar direto
    </button>
    <a class="btn btn--link" href="contratos.php">Cancelar</a>
  </div>
</form>

<script>
(function () {
  var sel = document.getElementById('rhVendedorSelect');
  if (!sel) return;
  var form = document.getElementById('ctForm');
  function setVal(name, value) {
    var el = form.querySelector('[name="' + name + '"]');
    if (!el) return;
    el.value = value;
    var label = el.closest('label');
    if (label) { label.classList.toggle('ct-campo-vazio', value.trim() === ''); }
  }
  sel.addEventListener('change', function () {
    var opt = sel.options[sel.selectedIndex];
    if (!opt.value) return; // "digitar manualmente"
    setVal('vendedor_nome', opt.dataset.nome || '');
    setVal('vendedor_cpf', opt.dataset.cpf || '');
    setVal('vendedor_comissao', opt.dataset.comissao || '');
  });
})();
</script>

<link rel="stylesheet" href="../assets/css/contrato-admin.css">

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

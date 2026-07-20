<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : null);
$erros = [];
$moto = [
    'marca' => '', 'modelo' => '', 'ano' => date('Y'), 'preco' => '', 'categoria' => 'street',
    'km' => 0, 'cor' => '', 'condicao' => 'seminovo', 'descricao' => '', 'destaque' => 0, 'ativo' => 1,
    'valor_compra' => 0, 'valor_venda' => 0, 'gasto_manutencao' => 0, 'gasto_documentacao' => 0,
    'gasto_transporte' => 0, 'outros_custos' => 0, 'status' => 'disponivel', 'vendido_em' => null,
    'cliente_nome' => '', 'cliente_documento' => '', 'cliente_telefone' => '', 'cliente_cidade' => '',
    'cliente_estado' => '', 'vendedor' => '', 'placa' => '', 'chassi' => '', 'forma_pagamento' => '',
    'venda_obs' => '', 'numero_venda' => '',
];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM motos WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        header('Location: dashboard.php');
        exit;
    }
    $moto = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $moto['marca'] = trim($_POST['marca'] ?? '');
    $moto['modelo'] = trim($_POST['modelo'] ?? '');
    $moto['ano'] = (int) ($_POST['ano'] ?? 0);
    $moto['preco'] = parse_dinheiro($_POST['preco'] ?? '0');
    $moto['categoria'] = $_POST['categoria'] ?? 'outra';
    $moto['km'] = (int) ($_POST['km'] ?? 0);
    $moto['cor'] = trim($_POST['cor'] ?? '');
    $moto['condicao'] = ($_POST['condicao'] ?? 'seminovo') === 'novo' ? 'novo' : 'seminovo';
    $moto['descricao'] = trim($_POST['descricao'] ?? '');
    $moto['destaque'] = isset($_POST['destaque']) ? 1 : 0;
    $moto['ativo'] = isset($_POST['ativo']) ? 1 : 0;

    // ----- financeiro / situação -----
    $dinheiro = fn($campo) => max(0, parse_dinheiro($_POST[$campo] ?? '0'));
    $moto['valor_compra'] = $dinheiro('valor_compra');
    $moto['valor_venda'] = $dinheiro('valor_venda');
    $moto['gasto_manutencao'] = $dinheiro('gasto_manutencao');
    $moto['gasto_documentacao'] = $dinheiro('gasto_documentacao');
    $moto['gasto_transporte'] = $dinheiro('gasto_transporte');
    $moto['outros_custos'] = $dinheiro('outros_custos');

    $statusRecebido = $_POST['status'] ?? 'disponivel';
    $moto['status'] = array_key_exists($statusRecebido, STATUS_MOTO) ? $statusRecebido : 'disponivel';

    // Data da venda: só faz sentido quando "vendida". Preenche automaticamente se faltar.
    $dataVenda = trim($_POST['data_venda'] ?? '');
    if ($moto['status'] === 'vendida') {
        if ($dataVenda !== '') {
            $moto['vendido_em'] = $dataVenda . ' 12:00:00';
        } elseif (!empty($existing['vendido_em'])) {
            $moto['vendido_em'] = $existing['vendido_em'];
        } else {
            $moto['vendido_em'] = date('Y-m-d H:i:s');
        }
    } else {
        $moto['vendido_em'] = null;
    }

    // ----- dados da venda -----
    $moto['cliente_nome'] = trim($_POST['cliente_nome'] ?? '');
    $moto['cliente_documento'] = trim($_POST['cliente_documento'] ?? '');
    $moto['cliente_telefone'] = trim($_POST['cliente_telefone'] ?? '');
    $moto['cliente_cidade'] = trim($_POST['cliente_cidade'] ?? '');
    $moto['cliente_estado'] = strtoupper(trim($_POST['cliente_estado'] ?? ''));
    $moto['vendedor'] = trim($_POST['vendedor'] ?? '');
    $moto['placa'] = strtoupper(trim($_POST['placa'] ?? ''));
    $moto['chassi'] = strtoupper(trim($_POST['chassi'] ?? ''));
    $formaRecebida = $_POST['forma_pagamento'] ?? '';
    $moto['forma_pagamento'] = in_array($formaRecebida, FORMAS_PAGAMENTO, true) ? $formaRecebida : '';
    $moto['venda_obs'] = trim($_POST['venda_obs'] ?? '');

    // Número da venda: gerado automaticamente na primeira vez que a moto é marcada como vendida.
    $moto['numero_venda'] = trim($existing['numero_venda'] ?? '');
    if ($moto['status'] === 'vendida' && $moto['numero_venda'] === '') {
        $proximo = (int) db()->query('SELECT COALESCE(MAX(CAST(numero_venda AS INTEGER)), 0) m FROM motos')->fetch()['m'] + 1;
        $moto['numero_venda'] = str_pad((string) $proximo, 4, '0', STR_PAD_LEFT);
    }

    if ($moto['marca'] === '') $erros[] = 'Informe a marca.';
    if ($moto['modelo'] === '') $erros[] = 'Informe o modelo.';
    if ($moto['preco'] <= 0) $erros[] = 'Informe um preço válido.';
    if (!array_key_exists($moto['categoria'], CATEGORIAS)) $erros[] = 'Categoria inválida.';

    if (!$erros) {
        $campos_venda = [
            $moto['cliente_nome'], $moto['cliente_documento'], $moto['cliente_telefone'], $moto['cliente_cidade'],
            $moto['cliente_estado'], $moto['vendedor'], $moto['placa'], $moto['chassi'], $moto['forma_pagamento'],
            $moto['venda_obs'], $moto['numero_venda'],
        ];
        if ($id) {
            $stmt = db()->prepare(
                'UPDATE motos SET marca=?, modelo=?, ano=?, preco=?, categoria=?, km=?, cor=?, condicao=?, descricao=?, destaque=?, ativo=?,
                    valor_compra=?, valor_venda=?, gasto_manutencao=?, gasto_documentacao=?, gasto_transporte=?, outros_custos=?, status=?, vendido_em=?,
                    cliente_nome=?, cliente_documento=?, cliente_telefone=?, cliente_cidade=?, cliente_estado=?, vendedor=?, placa=?, chassi=?, forma_pagamento=?, venda_obs=?, numero_venda=? WHERE id=?'
            );
            $stmt->execute(array_merge([
                $moto['marca'], $moto['modelo'], $moto['ano'], $moto['preco'], $moto['categoria'],
                $moto['km'], $moto['cor'], $moto['condicao'], $moto['descricao'], $moto['destaque'], $moto['ativo'],
                $moto['valor_compra'], $moto['valor_venda'], $moto['gasto_manutencao'], $moto['gasto_documentacao'],
                $moto['gasto_transporte'], $moto['outros_custos'], $moto['status'], $moto['vendido_em'],
            ], $campos_venda, [$id]));
        } else {
            $stmt = db()->prepare(
                'INSERT INTO motos (marca, modelo, ano, preco, categoria, km, cor, condicao, descricao, destaque, ativo,
                    valor_compra, valor_venda, gasto_manutencao, gasto_documentacao, gasto_transporte, outros_custos, status, vendido_em,
                    cliente_nome, cliente_documento, cliente_telefone, cliente_cidade, cliente_estado, vendedor, placa, chassi, forma_pagamento, venda_obs, numero_venda)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute(array_merge([
                $moto['marca'], $moto['modelo'], $moto['ano'], $moto['preco'], $moto['categoria'],
                $moto['km'], $moto['cor'], $moto['condicao'], $moto['descricao'], $moto['destaque'], $moto['ativo'],
                $moto['valor_compra'], $moto['valor_venda'], $moto['gasto_manutencao'], $moto['gasto_documentacao'],
                $moto['gasto_transporte'], $moto['outros_custos'], $moto['status'], $moto['vendido_em'],
            ], $campos_venda));
            $id = (int) db()->lastInsertId();
        }

        // remover fotos marcadas
        if (!empty($_POST['remover_foto']) && is_array($_POST['remover_foto'])) {
            foreach ($_POST['remover_foto'] as $fotoId) {
                $fotoId = (int) $fotoId;
                $stmt = db()->prepare('SELECT arquivo FROM moto_fotos WHERE id=? AND moto_id=?');
                $stmt->execute([$fotoId, $id]);
                $foto = $stmt->fetch();
                if ($foto) {
                    @unlink(UPLOADS_DIR . '/' . $id . '/' . $foto['arquivo']);
                    db()->prepare('DELETE FROM moto_fotos WHERE id=?')->execute([$fotoId]);
                }
            }
        }

        // definir capa entre as fotos existentes
        if (!empty($_POST['capa_foto'])) {
            db()->prepare('UPDATE moto_fotos SET capa=0 WHERE moto_id=?')->execute([$id]);
            db()->prepare('UPDATE moto_fotos SET capa=1 WHERE id=? AND moto_id=?')->execute([(int) $_POST['capa_foto'], $id]);
        }

        // novos uploads
        try {
            $jaTemFotos = (int) db()->query('SELECT COUNT(*) c FROM moto_fotos WHERE moto_id=' . (int) $id)->fetch()['c'] > 0;
            if (!empty($_FILES['fotos']['name'][0])) {
                $totalArquivos = count($_FILES['fotos']['name']);
                for ($i = 0; $i < $totalArquivos; $i++) {
                    $arquivo = [
                        'name' => $_FILES['fotos']['name'][$i],
                        'type' => $_FILES['fotos']['type'][$i],
                        'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                        'error' => $_FILES['fotos']['error'][$i],
                        'size' => $_FILES['fotos']['size'][$i],
                    ];
                    $salvo = salvar_upload_foto($arquivo, $id);
                    if ($salvo) {
                        $capa = (!$jaTemFotos && $i === 0) ? 1 : 0;
                        db()->prepare('INSERT INTO moto_fotos (moto_id, arquivo, ordem, capa) VALUES (?,?,?,?)')
                            ->execute([$id, $salvo, $i, $capa]);
                    }
                }
            }
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        }

        if (!$erros) {
            header('Location: dashboard.php?msg=' . (isset($existing) ? 'atualizada' : 'criada'));
            exit;
        }
    }
}

$fotosExistentes = $id ? moto_fotos($id) : [];
$pageTitle = $id ? 'Editar moto' : 'Cadastrar moto';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1><?= $id ? 'Editar moto' : 'Cadastrar moto' ?></h1>
  <a class="btn btn--link" href="dashboard.php">← Voltar para a lista</a>
</div>

<?php foreach ($erros as $erro): ?><p class="admin-alert"><?= e($erro) ?></p><?php endforeach; ?>

<form class="admin-form" method="post" enctype="multipart/form-data" data-reveal>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <?php if ($id): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

  <div class="admin-form__grid">
    <label>Marca
      <input type="text" name="marca" value="<?= e($moto['marca']) ?>" required>
    </label>
    <label>Modelo
      <input type="text" name="modelo" value="<?= e($moto['modelo']) ?>" required>
    </label>
    <label>Ano
      <input type="number" name="ano" value="<?= e((string) $moto['ano']) ?>" min="1980" max="<?= date('Y') + 1 ?>" required>
    </label>
    <label>Preço (R$)
      <input type="text" inputmode="decimal" name="preco" value="<?= (float) $moto['preco'] > 0 ? e(valor_input((float) $moto['preco'])) : '' ?>" placeholder="Ex: 15.900,00" required>
    </label>
    <label>Categoria
      <select name="categoria">
        <?php foreach (CATEGORIAS as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $moto['categoria'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Quilometragem
      <input type="number" name="km" value="<?= e((string) $moto['km']) ?>" min="0">
    </label>
    <label>Cor
      <input type="text" name="cor" value="<?= e($moto['cor']) ?>">
    </label>
    <label>Condição
      <select name="condicao">
        <option value="seminovo" <?= $moto['condicao'] === 'seminovo' ? 'selected' : '' ?>>Seminovo</option>
        <option value="novo" <?= $moto['condicao'] === 'novo' ? 'selected' : '' ?>>Novo</option>
      </select>
    </label>
  </div>

  <label class="admin-form__full">Descrição
    <textarea name="descricao" rows="4"><?= e($moto['descricao']) ?></textarea>
  </label>

  <div class="admin-form__checks">
    <label><input type="checkbox" name="destaque" <?= $moto['destaque'] ? 'checked' : '' ?>> Mostrar como destaque</label>
    <label><input type="checkbox" name="ativo" <?= $moto['ativo'] ? 'checked' : '' ?>> Visível no site</label>
  </div>

  <?php $dataVendaValor = !empty($moto['vendido_em']) ? substr($moto['vendido_em'], 0, 10) : ''; ?>
  <fieldset class="admin-fin admin-form__full">
    <legend class="admin-fin__legend">Financeiro</legend>
    <div class="admin-form__grid">
      <label>Situação
        <select name="status" id="finStatus">
          <?php foreach (STATUS_MOTO as $chave => $rotulo): ?>
            <option value="<?= e($chave) ?>" <?= $moto['status'] === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Data da venda
        <input type="date" name="data_venda" id="finDataVenda" value="<?= e($dataVendaValor) ?>">
      </label>
      <label>Valor de compra (R$)
        <input type="text" inputmode="decimal" class="fin-money" name="valor_compra" value="<?= (float) $moto['valor_compra'] > 0 ? e(valor_input((float) $moto['valor_compra'])) : '' ?>" placeholder="0">
      </label>
      <label>Valor de venda (R$)
        <input type="text" inputmode="decimal" class="fin-money" name="valor_venda" value="<?= (float) $moto['valor_venda'] > 0 ? e(valor_input((float) $moto['valor_venda'])) : '' ?>" placeholder="0">
      </label>
      <label>Gastos com manutenção (R$)
        <input type="text" inputmode="decimal" class="fin-money" name="gasto_manutencao" value="<?= (float) $moto['gasto_manutencao'] > 0 ? e(valor_input((float) $moto['gasto_manutencao'])) : '' ?>" placeholder="0">
      </label>
      <label>Gastos com documentação (R$)
        <input type="text" inputmode="decimal" class="fin-money" name="gasto_documentacao" value="<?= (float) $moto['gasto_documentacao'] > 0 ? e(valor_input((float) $moto['gasto_documentacao'])) : '' ?>" placeholder="0">
      </label>
      <label>Gastos com transporte (R$)
        <input type="text" inputmode="decimal" class="fin-money" name="gasto_transporte" value="<?= (float) $moto['gasto_transporte'] > 0 ? e(valor_input((float) $moto['gasto_transporte'])) : '' ?>" placeholder="0">
      </label>
      <label>Outros custos (R$)
        <input type="text" inputmode="decimal" class="fin-money" name="outros_custos" value="<?= (float) $moto['outros_custos'] > 0 ? e(valor_input((float) $moto['outros_custos'])) : '' ?>" placeholder="0">
      </label>
    </div>

    <p class="admin-fin__hint">Os indicadores abaixo são calculados automaticamente — não é possível digitá-los.</p>
    <div class="admin-fin__resumo">
      <div class="admin-fin__kpi"><span>Custo total</span><strong id="finCusto">—</strong></div>
      <div class="admin-fin__kpi"><span>Lucro bruto</span><strong id="finBruto">—</strong></div>
      <div class="admin-fin__kpi"><span>Lucro líquido</span><strong id="finLiquido">—</strong></div>
      <div class="admin-fin__kpi"><span>Margem de lucro</span><strong id="finMargem">—</strong></div>
    </div>
  </fieldset>

  <fieldset class="admin-fin admin-form__full">
    <legend class="admin-fin__legend">Dados da venda</legend>
    <p class="admin-fin__hint" style="margin-top:0;">Preencha ao marcar a moto como <strong>Vendida</strong>. Esses dados alimentam a planilha de fechamento (Exportar para Excel).</p>
    <div class="admin-form__grid">
      <label>Nome do cliente
        <input type="text" name="cliente_nome" value="<?= e($moto['cliente_nome']) ?>">
      </label>
      <label>CPF / CNPJ
        <input type="text" name="cliente_documento" value="<?= e($moto['cliente_documento']) ?>">
      </label>
      <label>Telefone
        <input type="text" name="cliente_telefone" value="<?= e($moto['cliente_telefone']) ?>">
      </label>
      <label>Cidade
        <input type="text" name="cliente_cidade" value="<?= e($moto['cliente_cidade']) ?>">
      </label>
      <label>Estado (UF)
        <select name="cliente_estado">
          <option value="">—</option>
          <?php foreach (UFS as $uf): ?>
            <option value="<?= $uf ?>" <?= $moto['cliente_estado'] === $uf ? 'selected' : '' ?>><?= $uf ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Vendedor responsável
        <input type="text" name="vendedor" value="<?= e($moto['vendedor']) ?>">
      </label>
      <label>Placa
        <input type="text" name="placa" value="<?= e($moto['placa']) ?>" maxlength="10" style="text-transform:uppercase;">
      </label>
      <label>Chassi
        <input type="text" name="chassi" value="<?= e($moto['chassi']) ?>" maxlength="30" style="text-transform:uppercase;">
      </label>
      <label>Forma de pagamento
        <select name="forma_pagamento">
          <option value="">—</option>
          <?php foreach (FORMAS_PAGAMENTO as $forma): ?>
            <option value="<?= e($forma) ?>" <?= $moto['forma_pagamento'] === $forma ? 'selected' : '' ?>><?= e($forma) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Nº da venda
        <input type="text" name="numero_venda_display" value="<?= e($moto['numero_venda'] !== '' ? $moto['numero_venda'] : '(gerado ao vender)') ?>" readonly>
      </label>
    </div>
    <label class="admin-form__full">Observações da venda
      <textarea name="venda_obs" rows="3"><?= e($moto['venda_obs']) ?></textarea>
    </label>
  </fieldset>

  <?php if ($fotosExistentes): ?>
    <div class="admin-form__full">
      <p class="admin-form__label">Fotos atuais</p>
      <div class="admin-photo-grid">
        <?php foreach ($fotosExistentes as $foto): ?>
          <div class="admin-photo">
            <img src="<?= e(moto_foto_url($id, $foto['arquivo'])) ?>" alt="">
            <label class="admin-photo__cover">
              <input type="radio" name="capa_foto" value="<?= (int) $foto['id'] ?>" <?= $foto['capa'] ? 'checked' : '' ?>> Capa
            </label>
            <label class="admin-photo__remove">
              <input type="checkbox" name="remover_foto[]" value="<?= (int) $foto['id'] ?>"> Remover
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <label class="admin-form__full">Adicionar fotos (JPG, PNG ou WEBP, até 6MB cada)
    <input type="file" name="fotos[]" accept="image/png, image/jpeg, image/webp" multiple>
  </label>

  <div class="admin-form__actions">
    <button class="btn btn--primary" type="submit"><?= $id ? 'Salvar alterações' : 'Cadastrar moto' ?></button>
  </div>
</form>

<script>
(function () {
  var moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  function num(name) {
    var el = document.querySelector('[name="' + name + '"]');
    if (!el) return 0;
    var v = parseFloat(String(el.value).replace(/\./g, '').replace(',', '.'));
    return isNaN(v) ? 0 : v;
  }
  function recalcular() {
    var compra = num('valor_compra');
    var venda = num('valor_venda');
    var custoTotal = compra + num('gasto_manutencao') + num('gasto_documentacao') + num('gasto_transporte') + num('outros_custos');
    var bruto = venda - compra;
    var liquido = venda - custoTotal;
    var margem = venda > 0 ? (liquido / venda) * 100 : 0;
    document.getElementById('finCusto').textContent = moeda.format(custoTotal);
    document.getElementById('finBruto').textContent = moeda.format(bruto);
    document.getElementById('finLiquido').textContent = moeda.format(liquido);
    document.getElementById('finMargem').textContent = margem.toFixed(1).replace('.', ',') + '%';
  }
  document.querySelectorAll('.fin-money').forEach(function (el) {
    el.addEventListener('input', recalcular);
  });

  var status = document.getElementById('finStatus');
  var dataVenda = document.getElementById('finDataVenda');
  function sincronizarData() {
    var vendida = status.value === 'vendida';
    dataVenda.closest('label').style.opacity = vendida ? '1' : '0.5';
    if (vendida && !dataVenda.value) {
      dataVenda.value = new Date().toISOString().slice(0, 10);
    }
  }
  if (status && dataVenda) {
    status.addEventListener('change', sincronizarData);
    sincronizarData();
  }

  recalcular();
})();
</script>

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

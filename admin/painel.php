<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/metrics.php';
require_once __DIR__ . '/../includes/alertas.php';
require_login();

$d = dashboard_metrics();
$ind = $d['indicadores'];
$fin = $d['financeiro'];
$est = $d['estoque'];
$alertas = admin_alertas();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin_layout_top.php';
?>

<div class="admin-header">
  <h1>Dashboard</h1>
  <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
    <a class="btn btn--primary" href="exportar.php">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M12 3v12M8 11l4 4 4-4M4 21h16"/></svg>
      Exportar para Excel
    </a>
    <a class="btn btn--link" href="dashboard.php">Ver lista de motos →</a>
  </div>
</div>

<p class="dash-hint">Todos os números são calculados automaticamente a partir do cadastro de motos. Ao cadastrar, editar, vender, reservar ou remover uma moto, o painel se atualiza sozinho.</p>

<!-- ---------------- Pendências / Alertas ---------------- -->
<section class="dash-section">
  <h2 class="dash-section__title">
    Pendências
    <?php $totAl = array_sum(array_column($alertas, 'total')); ?>
    <?php if ($totAl > 0): ?><span class="alerta-badge alerta-badge--danger"><?= $totAl ?></span><?php endif; ?>
  </h2>
  <?php if (!$alertas): ?>
    <div class="alerta-vazio">✅ Tudo em dia! Nenhuma pendência no momento.</div>
  <?php else: ?>
    <div class="alerta-grid">
      <?php foreach ($alertas as $g): ?>
        <div class="alerta-card alerta-card--<?= e($g['severidade']) ?>" data-reveal>
          <div class="alerta-card__head">
            <span class="alerta-card__icon"><?= $g['icone'] ?></span>
            <h3><?= e($g['titulo']) ?></h3>
            <span class="alerta-badge alerta-badge--<?= e($g['severidade']) ?>"><?= (int) $g['total'] ?></span>
          </div>
          <ul class="alerta-lista">
            <?php foreach (array_slice($g['itens'], 0, 6) as $it): ?>
              <li><a href="<?= e($it['url']) ?>"><?= e($it['texto']) ?></a></li>
            <?php endforeach; ?>
            <?php if (count($g['itens']) > 6): ?>
              <li class="alerta-mais">e mais <?= count($g['itens']) - 6 ?>…</li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- ---------------- Indicadores ---------------- -->
<section class="dash-section">
  <h2 class="dash-section__title">Indicadores</h2>
  <div class="dash-kpis">
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Motos cadastradas</span><strong class="dash-kpi__value" data-count="<?= (int) $ind['total_motos'] ?>" data-count-format="int"><?= (int) $ind['total_motos'] ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Disponíveis</span><strong class="dash-kpi__value" data-count="<?= (int) $ind['disponiveis'] ?>" data-count-format="int"><?= (int) $ind['disponiveis'] ?></strong></div>
    <div class="dash-kpi dash-kpi--ok" data-reveal><span class="dash-kpi__label">Vendidas</span><strong class="dash-kpi__value" data-count="<?= (int) $ind['vendidas'] ?>" data-count-format="int"><?= (int) $ind['vendidas'] ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Reservadas</span><strong class="dash-kpi__value" data-count="<?= (int) $ind['reservadas'] ?>" data-count-format="int"><?= (int) $ind['reservadas'] ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Valor do estoque</span><strong class="dash-kpi__value" data-count="<?= $ind['valor_estoque'] ?>" data-count-format="brl"><?= formatar_reais($ind['valor_estoque']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Receita do dia</span><strong class="dash-kpi__value" data-count="<?= $ind['receita_dia'] ?>" data-count-format="brl"><?= formatar_reais($ind['receita_dia']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Receita da semana</span><strong class="dash-kpi__value" data-count="<?= $ind['receita_semana'] ?>" data-count-format="brl"><?= formatar_reais($ind['receita_semana']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Receita do mês</span><strong class="dash-kpi__value" data-count="<?= $ind['receita_mes'] ?>" data-count-format="brl"><?= formatar_reais($ind['receita_mes']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Receita do ano</span><strong class="dash-kpi__value" data-count="<?= $ind['receita_ano'] ?>" data-count-format="brl"><?= formatar_reais($ind['receita_ano']) ?></strong></div>
    <div class="dash-kpi dash-kpi--ok" data-reveal><span class="dash-kpi__label">Lucro bruto</span><strong class="dash-kpi__value" data-count="<?= $ind['lucro_bruto'] ?>" data-count-format="brl"><?= formatar_reais($ind['lucro_bruto']) ?></strong></div>
    <div class="dash-kpi dash-kpi--ok" data-reveal><span class="dash-kpi__label">Lucro líquido</span><strong class="dash-kpi__value" data-count="<?= $ind['lucro_liquido'] ?>" data-count-format="brl"><?= formatar_reais($ind['lucro_liquido']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Margem média</span><strong class="dash-kpi__value" data-count="<?= $ind['margem_media'] ?>" data-count-format="pct"><?= formatar_percent($ind['margem_media']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Ticket médio</span><strong class="dash-kpi__value" data-count="<?= $ind['ticket_medio'] ?>" data-count-format="brl"><?= formatar_reais($ind['ticket_medio']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Giro de estoque</span><strong class="dash-kpi__value" data-count="<?= $ind['giro'] ?>" data-count-format="dec"><?= number_format($ind['giro'], 2, ',', '.') ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Tempo médio de venda</span><strong class="dash-kpi__value" data-count="<?= $ind['tempo_medio_venda'] ?>" data-count-format="dias"><?= number_format($ind['tempo_medio_venda'], 0, ',', '.') ?> dias</strong></div>
  </div>
</section>

<!-- ---------------- Gráficos ---------------- -->
<section class="dash-section">
  <h2 class="dash-section__title">Gráficos</h2>
  <div class="dash-charts">
    <div class="dash-chart" data-reveal><h3>Receita por mês</h3><canvas id="chReceita"></canvas></div>
    <div class="dash-chart" data-reveal><h3>Lucro por mês</h3><canvas id="chLucro"></canvas></div>
    <div class="dash-chart" data-reveal><h3>Vendas por mês</h3><canvas id="chVendas"></canvas></div>
    <div class="dash-chart" data-reveal><h3>Evolução do faturamento</h3><canvas id="chEvolFat"></canvas></div>
    <div class="dash-chart" data-reveal><h3>Evolução do lucro</h3><canvas id="chEvolLucro"></canvas></div>
    <div class="dash-chart" data-reveal><h3>Vendas por marca</h3><canvas id="chMarca"></canvas></div>
    <div class="dash-chart" data-reveal><h3>Vendas por modelo</h3><canvas id="chModelo"></canvas></div>
    <div class="dash-chart" data-reveal><h3>Estoque por categoria</h3><canvas id="chEstCat"></canvas></div>
    <div class="dash-chart" data-reveal><h3>Estoque por faixa de preço</h3><canvas id="chEstFaixa"></canvas></div>
  </div>
</section>

<!-- ---------------- Financeiro ---------------- -->
<section class="dash-section">
  <h2 class="dash-section__title">Financeiro</h2>
  <div class="dash-kpis">
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Total investido em estoque</span><strong class="dash-kpi__value" data-count="<?= $fin['investido_estoque'] ?>" data-count-format="brl"><?= formatar_reais($fin['investido_estoque']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Total vendido</span><strong class="dash-kpi__value" data-count="<?= $fin['total_vendido'] ?>" data-count-format="brl"><?= formatar_reais($fin['total_vendido']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Total recebido</span><strong class="dash-kpi__value" data-count="<?= $fin['total_recebido'] ?>" data-count-format="brl"><?= formatar_reais($fin['total_recebido']) ?></strong></div>
    <div class="dash-kpi dash-kpi--ok" data-reveal><span class="dash-kpi__label">Lucro acumulado</span><strong class="dash-kpi__value" data-count="<?= $fin['lucro_acumulado'] ?>" data-count-format="brl"><?= formatar_reais($fin['lucro_acumulado']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Custos totais</span><strong class="dash-kpi__value" data-count="<?= $fin['custos_totais'] ?>" data-count-format="brl"><?= formatar_reais($fin['custos_totais']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">Rentabilidade</span><strong class="dash-kpi__value" data-count="<?= $fin['rentabilidade'] ?>" data-count-format="pct"><?= formatar_percent($fin['rentabilidade']) ?></strong></div>
    <div class="dash-kpi" data-reveal><span class="dash-kpi__label">ROI das vendas</span><strong class="dash-kpi__value" data-count="<?= $fin['roi'] ?>" data-count-format="pct"><?= formatar_percent($fin['roi']) ?></strong></div>
  </div>
</section>

<!-- ---------------- Estoque ---------------- -->
<section class="dash-section">
  <h2 class="dash-section__title">Estoque</h2>
  <div class="dash-cols">
    <div class="dash-card" data-reveal>
      <h3>Motos paradas</h3>
      <div class="dash-aging">
        <div class="dash-aging__item"><span>+ de 30 dias</span><strong><?= count($est['parados30']) ?></strong></div>
        <div class="dash-aging__item dash-aging__item--warn"><span>+ de 60 dias</span><strong><?= count($est['parados60']) ?></strong></div>
        <div class="dash-aging__item dash-aging__item--danger"><span>+ de 90 dias</span><strong><?= count($est['parados90']) ?></strong></div>
      </div>
      <?php $paradosLista = array_merge($est['parados90'], $est['parados60']); ?>
      <?php if ($paradosLista): ?>
        <ul class="dash-list">
          <?php foreach (array_slice($paradosLista, 0, 6) as $p): ?>
            <li><span><?= e($p['moto']['marca'] . ' ' . $p['moto']['modelo']) ?></span><span class="dash-list__meta"><?= (int) $p['dias'] ?> dias</span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="dash-card" data-reveal>
      <h3>Estoque por categoria</h3>
      <ul class="dash-list">
        <?php foreach ($est['por_categoria'] as $cat => $qtd): ?>
          <li><span><?= e($cat) ?></span><span class="dash-list__meta"><?= (int) $qtd ?></span></li>
        <?php endforeach; ?>
        <?php if (!$est['por_categoria']): ?><li class="dash-list__empty">Sem motos disponíveis.</li><?php endif; ?>
      </ul>
    </div>
    <div class="dash-card" data-reveal>
      <h3>Estoque por marca</h3>
      <ul class="dash-list">
        <?php foreach (array_slice($est['por_marca'], 0, 8, true) as $marca => $qtd): ?>
          <li><span><?= e($marca) ?></span><span class="dash-list__meta"><?= (int) $qtd ?></span></li>
        <?php endforeach; ?>
        <?php if (!$est['por_marca']): ?><li class="dash-list__empty">Sem motos disponíveis.</li><?php endif; ?>
      </ul>
    </div>
    <div class="dash-card" data-reveal>
      <h3>Estoque por faixa de preço</h3>
      <ul class="dash-list">
        <?php foreach ($est['por_faixa'] as $faixa => $qtd): ?>
          <li><span><?= e($faixa) ?></span><span class="dash-list__meta"><?= (int) $qtd ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<!-- ---------------- Últimas atividades ---------------- -->
<section class="dash-section">
  <h2 class="dash-section__title">Últimas atividades</h2>
  <div class="dash-cols">
    <div class="dash-card" data-reveal>
      <h3>Últimas motos cadastradas</h3>
      <ul class="dash-list">
        <?php foreach ($d['atividades']['cadastradas'] as $m): ?>
          <li>
            <span><?= e($m['marca'] . ' ' . $m['modelo']) ?></span>
            <span class="dash-list__meta"><?= e(substr($m['criado_em'] ?? '', 0, 10)) ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$d['atividades']['cadastradas']): ?><li class="dash-list__empty">Nenhuma moto cadastrada.</li><?php endif; ?>
      </ul>
    </div>
    <div class="dash-card" data-reveal>
      <h3>Últimas motos vendidas</h3>
      <ul class="dash-list">
        <?php foreach ($d['atividades']['vendidas'] as $m): ?>
          <li>
            <span><?= e($m['marca'] . ' ' . $m['modelo']) ?></span>
            <span class="dash-list__meta"><?= formatar_reais((float) $m['valor_venda']) ?> · <?= e(substr($m['vendido_em'] ?? '', 0, 10)) ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$d['atividades']['vendidas']): ?><li class="dash-list__empty">Nenhuma venda registrada ainda.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</section>

<!-- ---------------- Alertas ---------------- -->
<section class="dash-section">
  <h2 class="dash-section__title">Alertas inteligentes</h2>
  <div class="dash-alertas">
    <?php foreach ($d['alertas'] as $a): ?>
      <div data-reveal class="dash-alerta dash-alerta--<?= e($a['nivel']) ?>"><?= e($a['texto']) ?></div>
    <?php endforeach; ?>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
window.DASH = <?= json_encode($d['graficos'], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="../assets/js/dashboard.js"></script>

<?php require __DIR__ . '/../includes/admin_layout_bottom.php'; ?>

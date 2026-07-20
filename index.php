<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$motos = db()->query("SELECT * FROM motos WHERE ativo = 1 ORDER BY destaque DESC, criado_em DESC")->fetchAll();

$motosParaJs = array_map(function ($m) {
    $capa = moto_foto_capa((int) $m['id']);
    $fotos = array_map(
        fn($f) => moto_foto_url((int) $m['id'], $f['arquivo']),
        moto_fotos((int) $m['id'])
    );
    return [
        'id' => (int) $m['id'],
        'marca' => $m['marca'],
        'modelo' => $m['modelo'],
        'ano' => (int) $m['ano'],
        'preco' => (float) $m['preco'],
        'categoria' => $m['categoria'],
        'categoriaLabel' => categoria_label($m['categoria']),
        'km' => $m['km'] !== null ? (int) $m['km'] : null,
        'cor' => $m['cor'],
        'descricao' => $m['descricao'],
        'condicao' => $m['condicao'],
        'destaque' => (bool) $m['destaque'],
        'foto' => $capa ? moto_foto_url((int) $m['id'], $capa) : null,
        'fotos' => $fotos,
    ];
}, $motos);

$totalMotos = count($motos);
$totalNovas = count(array_filter($motos, fn($m) => $m['condicao'] === 'novo'));
$heroSlides = motos_destaque_com_foto($motos, 5);
$temDestaque = count(array_filter($motos, fn($m) => (bool) $m['destaque'])) > 0;
$clientes = clientes_fotos();

$whatsappGenerico = 'https://wa.me/' . WHATSAPP_NUMBER
    . '?text=' . rawurlencode('Olá! Acessei o site da Skinão Motos e tenho interesse em uma moto. Pode me ajudar?');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Skinão Motos — Catálogo</title>
<meta name="description" content="Catálogo de motos novas e seminovas da Skinão Motos. Compra, venda, troca e financiamento.">
<link rel="icon" href="favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16.png">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<meta name="theme-color" content="#0a0a0a">
<script>
(function () {
  var saved = localStorage.getItem('skinao-theme');
  var theme = saved || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
  // ativa o reveal (sem flash) e, por segurança, mostra tudo se o JS não iniciar
  document.documentElement.classList.add('reveal-ready');
  setTimeout(function () { if (!window.Skinao) document.documentElement.classList.remove('reveal-ready'); }, 2500);
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

<header class="site-header led-bottom">
  <div class="wrap site-header__row">
    <a class="brand" href="#top" aria-label="Skinão Motos">
      <span class="brand__logobox" id="brandLogo">
        <video class="brand__logo brand__logo--anim" src="assets/video/logo-animado.mp4"
               autoplay muted playsinline preload="auto"></video>
        <img class="brand__logo brand__logo--rest" src="assets/img/logo-header.png" alt="Skinão Motos">
      </span>
    </a>
    <nav class="site-nav" id="siteNav" aria-label="Navegação principal">
      <a href="#estoque">Estoque</a>
      <a href="#como-funciona">Como funciona</a>
      <a href="#contato">Contato</a>
    </nav>
    <a class="btn btn--whatsapp site-nav__cta" target="_blank" rel="noopener"
       href="<?= e($whatsappGenerico) ?>">Falar no WhatsApp</a>
    <button class="theme-toggle" id="themeToggle" type="button" aria-label="Alternar tema claro/escuro">
      <svg class="theme-toggle__icon theme-toggle__icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      <svg class="theme-toggle__icon theme-toggle__icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
    </button>
    <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="siteNav">
      <span></span><span></span><span></span>
      <span class="sr-only">Abrir menu</span>
    </button>
  </div>
</header>

<main id="top">

  <?php if ($heroSlides): ?>
  <section class="hero-carousel" id="heroCarousel">
    <?php foreach ($heroSlides as $i => $m): ?>
      <div class="hero-carousel__slide<?= $i === 0 ? ' is-active' : '' ?>">
        <img src="<?= e(moto_foto_url((int) $m['id'], moto_foto_capa((int) $m['id']))) ?>" alt="<?= e($m['marca'] . ' ' . $m['modelo']) ?>">
      </div>
    <?php endforeach; ?>
    <div class="hero-carousel__overlay"></div>
    <div class="hero-carousel__content">
      <h1 class="hero-carousel__title" data-reveal data-reveal-delay="0">Skinão<span class="hero-carousel__title-accent"> Motos</span></h1>
      <p class="hero-carousel__subtitle" data-reveal data-reveal-delay="90">Seminovas e novas com qualidade garantida</p>
      <a href="#estoque" class="btn btn--primary btn--lg hero-carousel__cta" data-reveal data-reveal-delay="180">Ver estoque</a>
    </div>
    <?php if (count($heroSlides) > 1): ?>
    <div class="hero-carousel__dots">
      <?php foreach ($heroSlides as $i => $m): ?>
        <button class="hero-carousel__dot<?= $i === 0 ? ' is-active' : '' ?>" data-index="<?= $i ?>" aria-label="Ir para slide <?= $i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php else: ?>
  <section class="hero-carousel hero-carousel--empty">
    <div class="hero-carousel__overlay"></div>
    <div class="hero-carousel__content">
      <h1 class="hero-carousel__title" data-reveal data-reveal-delay="0">Skinão<span class="hero-carousel__title-accent"> Motos</span></h1>
      <p class="hero-carousel__subtitle" data-reveal data-reveal-delay="90">Seminovas e novas com qualidade garantida</p>
      <a href="#estoque" class="btn btn--primary btn--lg hero-carousel__cta" data-reveal data-reveal-delay="180">Ver estoque</a>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($temDestaque): ?>
  <section class="destaque led-top">
    <div class="wrap">
      <div class="section-heading" data-reveal><span class="section-heading__bar"></span><h2>Em Destaque</h2></div>
      <div class="destaque__grid" id="destaqueGrid" aria-live="polite"></div>
    </div>
  </section>
  <?php endif; ?>

  <section class="estoque led-top" id="estoque">
    <div class="wrap">
      <div class="section-heading" data-reveal><span class="section-heading__bar"></span><h2>Estoque</h2></div>

      <div class="toolbar" data-reveal data-reveal-delay="80">
        <div class="toolbar__sort" role="group" aria-label="Ordenar por preço">
          <button class="toolbar__sort-btn is-active" data-sort="relevance" type="button">Relevância</button>
          <button class="toolbar__sort-btn" data-sort="price-asc" type="button">Menor preço</button>
          <button class="toolbar__sort-btn" data-sort="price-desc" type="button">Maior preço</button>
        </div>
        <div class="toolbar__right">
          <p class="toolbar__count" id="resultCount"></p>
          <button class="toolbar__filters-btn" id="filtersToggle" type="button" aria-expanded="false" aria-controls="filterPanel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h10M17 6h3M4 12h3M9 12h11M4 18h13M20 18h0"/><circle cx="14" cy="6" r="2"/><circle cx="6" cy="12" r="2"/><circle cx="16" cy="18" r="2"/></svg>
            <span>Filtros</span>
            <span class="toolbar__filters-badge" id="filtersBadge" hidden>0</span>
          </button>
        </div>
      </div>

      <div class="filter-panel" id="filterPanel">
        <div class="filter-panel__grid">
          <label>Marca
            <select id="filterMarca">
              <option value="">Todas</option>
            </select>
          </label>
          <label>Ano mínimo
            <input type="number" id="filterAnoMin" placeholder="Qualquer">
          </label>
          <label>Km máximo
            <input type="number" id="filterKmMax" placeholder="Qualquer">
          </label>
          <label>Preço máx. (R$)
            <input type="number" id="filterPrecoMax" placeholder="Qualquer">
          </label>
        </div>
        <button class="filter-panel__clear" id="filterClear" type="button" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
          Limpar filtros
        </button>
      </div>

      <div class="chips-row">
        <div class="chips" role="group" aria-label="Categoria">
          <button class="chip is-active" data-filter="all" type="button">Todas</button>
          <?php foreach (CATEGORIAS as $key => $label): ?>
            <button class="chip" data-filter="<?= e($key) ?>" type="button"><?= e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <input type="search" id="searchInput" class="filters__search" placeholder="Buscar marca ou modelo…" aria-label="Buscar marca ou modelo">
      </div>

      <div class="catalog__grid" id="catalogGrid" aria-live="polite"></div>
    </div>
  </section>

  <section class="how led-top" id="como-funciona">
    <div class="wrap how__row">
      <div class="how__step" data-reveal data-reveal-delay="0">
        <h3>1. Escolha no catálogo</h3>
        <p>Filtre por categoria e veja ano, km e preço de cada moto antes de ir até a loja.</p>
      </div>
      <div class="how__step" data-reveal data-reveal-delay="90">
        <h3>2. Fale com a gente</h3>
        <p>Chame no WhatsApp direto pela moto que te interessou e agende uma visita.</p>
      </div>
      <div class="how__step" data-reveal data-reveal-delay="180">
        <h3>3. Feche o negócio</h3>
        <p>Simulamos financiamento na hora, com troca ou entrada facilitada.</p>
      </div>
    </div>
  </section>

  <?php if ($clientes): ?>
  <section class="clientes led-top" id="clientes">
    <div class="wrap">
      <div class="clientes__head" data-reveal>
        <h2 class="clientes__title">Clientes Satisfeitos</h2>
        <p class="clientes__subtitle">Centenas de clientes já realizaram o sonho da sua moto com a Skinão Motos</p>
      </div>
      <div class="clientes__grid">
        <?php foreach ($clientes as $i => $cliente): ?>
          <figure class="cliente-card" data-reveal data-reveal-delay="<?= min($i, 8) * 50 ?>">
            <img src="<?= e(cliente_foto_url($cliente['arquivo'])) ?>" alt="Cliente satisfeito da Skinão Motos" loading="lazy">
          </figure>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

</main>

<footer class="site-footer led-top" id="contato">
  <div class="wrap site-footer__grid">
    <div class="site-footer__col" data-reveal data-reveal-delay="0">
      <img class="brand__logo brand__logo--footer" src="assets/img/logo-header.png" alt="Skinão Motos">
      <p class="site-footer__about">Especialistas em motos seminovas e novas com qualidade e confiança. Sua próxima moto está aqui.</p>
      <div class="footer__social">
        <a class="footer__social-btn footer__social-btn--whatsapp" target="_blank" rel="noopener" href="<?= e($whatsappGenerico) ?>" aria-label="WhatsApp">
          <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2C6.5 2 2 6.5 2 12c0 1.8.5 3.5 1.4 5L2 22l5.2-1.4c1.4.8 3.1 1.2 4.8 1.2 5.5 0 10-4.5 10-10S17.5 2 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3.2.8.9-3.1-.2-.3C4.1 14.8 3.7 13.4 3.7 12c0-4.6 3.7-8.3 8.3-8.3s8.3 3.7 8.3 8.3-3.7 8.2-8.3 8.2zm4.6-6.2c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.7 1-.1.2-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.7-.1-.2 0-.4.1-.5.1-.1.2-.3.4-.4.1-.1.2-.2.2-.4.1-.1 0-.3 0-.4-.1-.1-.6-1.3-.8-1.8-.2-.4-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.7-.8 1.8s.8 2.1 1 2.2c.1.2 1.6 2.4 3.8 3.4.5.2.9.4 1.3.5.5.2 1 .1 1.4.1.4-.1 1.3-.5 1.5-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.2-.4-.3z"/></svg>
        </a>
        <?php if (INSTAGRAM_URL !== ''): ?>
        <a class="footer__social-btn footer__social-btn--instagram" target="_blank" rel="noopener" href="<?= e(INSTAGRAM_URL) ?>" aria-label="Instagram">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="site-footer__col" data-reveal data-reveal-delay="80">
      <h4>Navegação</h4>
      <a href="#top">Início</a>
      <a href="#estoque">Estoque</a>
      <a href="#contato">Contato</a>
    </div>
    <div class="site-footer__col" data-reveal data-reveal-delay="160">
      <h4>Contato</h4>
      <a class="footer__contact-row" target="_blank" rel="noopener" href="<?= e($whatsappGenerico) ?>">
        <span class="footer__contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1C10 20 4 14 3 5a1 1 0 0 1 1-1Z"/></svg></span>
        <span>WhatsApp</span>
      </a>
      <div class="footer__contact-row">
        <span class="footer__contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1C10 20 4 14 3 5a1 1 0 0 1 1-1Z"/></svg></span>
        <span>Tel: <?= e(CONTACT_PHONE) ?></span>
      </div>
      <div class="footer__contact-row">
        <span class="footer__contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1C10 20 4 14 3 5a1 1 0 0 1 1-1Z"/></svg></span>
        <span><?= e(CONTACT_HELIO) ?></span>
      </div>
      <div class="footer__contact-row">
        <span class="footer__contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1C10 20 4 14 3 5a1 1 0 0 1 1-1Z"/></svg></span>
        <span><?= e(CONTACT_LEILANE) ?></span>
      </div>
      <div class="footer__contact-row">
        <span class="footer__contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg></span>
        <span class="site-footer__addr"><?= e(CONTACT_ADDRESS) ?></span>
      </div>
    </div>
    <div class="site-footer__col" data-reveal data-reveal-delay="240">
      <h4>Horário de Funcionamento</h4>
      <div class="footer__contact-row">
        <span class="footer__contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg></span>
        <span>
          <p class="footer__hours-day"><?= e(CONTACT_HOURS_WEEKDAY) ?></p>
          <p class="footer__hours-time"><?= e(CONTACT_HOURS_WEEKEND) ?></p>
        </span>
      </div>
    </div>
  </div>
  <div class="site-footer__bottom">
    <div class="wrap">
      <p>© <?= date('Y') ?> Skinão Motos. Todos os direitos reservados.</p>
    </div>
  </div>
</footer>

<a class="whatsapp-float" target="_blank" rel="noopener" href="<?= e($whatsappGenerico) ?>" aria-label="Falar no WhatsApp">
  <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C9.4 3 4 8.4 4 15c0 2.4.7 4.6 1.9 6.5L4 29l7.7-1.9c1.8 1 3.9 1.5 6.3 1.5 6.6 0 12-5.4 12-12S22.6 3 16 3zm0 21.8c-2.1 0-4-.6-5.7-1.6l-.4-.2-4.6 1.2 1.2-4.4-.3-.5C5.2 17.8 4.6 16 4.6 15c0-6.3 5.1-11.4 11.4-11.4S27.4 8.7 27.4 15 22.3 24.8 16 24.8zm6.3-8.5c-.3-.2-2-1-2.3-1.1-.3-.1-.5-.2-.7.2-.2.3-.8 1.1-1 1.3-.2.2-.4.3-.7.1-.3-.2-1.4-.5-2.6-1.6-1-.9-1.6-2-1.8-2.3-.2-.3 0-.5.1-.7.1-.1.3-.4.5-.5.2-.2.2-.3.3-.5.1-.2 0-.4 0-.6-.1-.2-.7-1.7-1-2.3-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1.1 2.8 1.2 3c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 2-.8 2.2-1.6.3-.8.3-1.4.2-1.6-.1-.1-.3-.2-.6-.3z"/></svg>
</a>

<div class="modal-overlay" id="motoModal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <button class="modal__close" id="modalClose" aria-label="Fechar">&times;</button>
    <div class="modal__media" id="modalMedia"></div>
    <div class="modal__body" id="modalBody"></div>
  </div>
</div>

<script>window.__MOTOS__ = <?= json_encode($motosParaJs, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/js/reveal.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>

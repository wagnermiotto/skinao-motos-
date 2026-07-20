(function () {
  "use strict";

  var motos = window.__MOTOS__ || [];
  var grid = document.getElementById("catalogGrid");
  var destaqueGrid = document.getElementById("destaqueGrid");
  var resultCount = document.getElementById("resultCount");
  var searchInput = document.getElementById("searchInput");

  var currency = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 });
  var kmFormat = new Intl.NumberFormat("pt-BR");
  var prefersReducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var state = { filter: "all", sort: "relevance", search: "" };
  var advFilters = { marca: "", anoMin: "", kmMax: "", precoMax: "" };

  var ICONS = {
    calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
    gauge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 14 15 9"/><circle cx="12" cy="14" r="1"/><path d="M4 14a8 8 0 0 1 16 0"/></svg>',
    palette: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 5.5 0 1 0 0 11c1 0 1.5-.5 1.5-1.3 0-.5-.3-.9-.3-1.4 0-.7.6-1.3 1.3-1.3H16a5 4 0 0 0 5-4C21 4 17 3 12 3Z"/><circle cx="7.5" cy="10.5" r="1"/><circle cx="10.5" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/></svg>',
    camera: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><circle cx="12" cy="12.5" r="3.5"/><path d="M9 6l1-2h4l1 2"/></svg>',
    chevronLeft: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>',
    chevronRight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>'
  };

  function cardMarkup(m, i) {
    var media = m.foto
      ? '<img src="' + m.foto + '" alt="' + escapeHtml(m.marca + " " + m.modelo) + '" loading="lazy">'
      : '<div class="card__media-empty">' + ICONS.camera + '<span>Sem foto</span></div>';

    var ribbon = m.destaque ? '<span class="card__ribbon">Em Destaque</span>' : "";
    var novoBadge = m.condicao === "novo" ? '<span class="card__badge-novo">Novo</span>' : "";

    var metaParts = ['<span>' + ICONS.calendar + m.ano + '</span>'];
    if (m.km !== null && m.km !== undefined) {
      metaParts.push('<span>' + ICONS.gauge + kmFormat.format(m.km) + ' km</span>');
    }
    if (m.cor) {
      metaParts.push('<span>' + ICONS.palette + escapeHtml(m.cor) + '</span>');
    }

    var delay = Math.min(typeof i === "number" ? i : 0, 7) * 45;

    return (
      '<article class="card" data-category="' + m.categoria + '" data-id="' + m.id + '" data-reveal data-reveal-delay="' + delay + '">' +
      '<div class="card__media">' + ribbon + novoBadge + media + '</div>' +
      '<div class="card__body">' +
      '<p class="card__category">' + escapeHtml(m.categoriaLabel) + "</p>" +
      '<h3 class="card__name">' + escapeHtml(m.marca + " " + m.modelo) + "</h3>" +
      '<div class="card__meta">' + metaParts.join("") + "</div>" +
      '<div class="card__foot">' +
      '<span class="card__price">' + currency.format(m.preco) + "</span>" +
      '<a class="btn btn--primary btn--sm" target="_blank" rel="noopener" data-whatsapp-btn href="' + whatsappMotoUrl(m) + '">WhatsApp</a>' +
      "</div>" +
      "</div>" +
      "</article>"
    );
  }

  function whatsappMotoUrl(m) {
    var msg = "Olá! Vi no site da Skinão Motos e tenho interesse na " + m.marca + " " + m.modelo + " " + m.ano +
      " (" + currency.format(m.preco) + "). Ela ainda está disponível?";
    return "https://wa.me/" + WHATSAPP_NUMBER_JS() + "?text=" + encodeURIComponent(msg);
  }

  function WHATSAPP_NUMBER_JS() {
    var link = document.querySelector(".whatsapp-float");
    if (!link) return "";
    var match = link.getAttribute("href").match(/wa\.me\/(\d+)/);
    return match ? match[1] : "";
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function hasActiveAdvFilters() {
    return !!(advFilters.marca || advFilters.anoMin || advFilters.kmMax || advFilters.precoMax);
  }

  function render() {
    var list = motos.filter(function (m) {
      var matchesCategory = state.filter === "all" || m.categoria === state.filter;
      var term = state.search.trim().toLowerCase();
      var matchesSearch = !term || (m.marca + " " + m.modelo).toLowerCase().indexOf(term) !== -1;
      var matchesMarca = !advFilters.marca || m.marca === advFilters.marca;
      var matchesAnoMin = !advFilters.anoMin || m.ano >= Number(advFilters.anoMin);
      var matchesKmMax = !advFilters.kmMax || m.km === null || m.km === undefined || m.km <= Number(advFilters.kmMax);
      var matchesPrecoMax = !advFilters.precoMax || m.preco <= Number(advFilters.precoMax);
      return matchesCategory && matchesSearch && matchesMarca && matchesAnoMin && matchesKmMax && matchesPrecoMax;
    });

    if (state.sort === "price-asc") list.sort(function (a, b) { return a.preco - b.preco; });
    if (state.sort === "price-desc") list.sort(function (a, b) { return b.preco - a.preco; });

    grid.innerHTML = list.length
      ? list.map(cardMarkup).join("")
      : '<p class="catalog__empty">Nenhuma moto encontrada. Tente outro filtro ou termo de busca.</p>';

    resultCount.innerHTML = "<strong>" + list.length + "</strong> " + (list.length === 1 ? "moto" : "motos");

    updateFiltersBadge();
    if (window.Skinao) window.Skinao.revealScan(grid);
  }

  function renderDestaque() {
    if (!destaqueGrid) return;
    var list = motos.filter(function (m) { return m.destaque; }).slice(0, 3);
    destaqueGrid.innerHTML = list.map(cardMarkup).join("");
    if (window.Skinao) window.Skinao.revealScan(destaqueGrid);
  }

  function updateFiltersBadge() {
    var badge = document.getElementById("filtersBadge");
    var clearBtn = document.getElementById("filterClear");
    var count = [advFilters.marca, advFilters.anoMin, advFilters.kmMax, advFilters.precoMax].filter(Boolean).length;
    if (badge) {
      badge.textContent = String(count);
      badge.hidden = count === 0;
    }
    if (clearBtn) {
      clearBtn.hidden = !hasActiveAdvFilters();
    }
  }

  document.querySelectorAll(".chip").forEach(function (chip) {
    chip.addEventListener("click", function () {
      document.querySelectorAll(".chip").forEach(function (c) { c.classList.remove("is-active"); });
      chip.classList.add("is-active");
      state.filter = chip.dataset.filter;
      render();
    });
  });

  document.querySelectorAll(".toolbar__sort-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      document.querySelectorAll(".toolbar__sort-btn").forEach(function (b) { b.classList.remove("is-active"); });
      btn.classList.add("is-active");
      state.sort = btn.dataset.sort;
      render();
    });
  });

  if (searchInput) {
    searchInput.addEventListener("input", function () {
      state.search = searchInput.value;
      render();
    });
  }

  // ---- filter panel ----
  var filtersToggle = document.getElementById("filtersToggle");
  var filterPanel = document.getElementById("filterPanel");
  var filterMarca = document.getElementById("filterMarca");
  var filterAnoMin = document.getElementById("filterAnoMin");
  var filterKmMax = document.getElementById("filterKmMax");
  var filterPrecoMax = document.getElementById("filterPrecoMax");
  var filterClear = document.getElementById("filterClear");

  if (filterMarca) {
    var marcas = Array.from(new Set(motos.map(function (m) { return m.marca; }))).sort();
    marcas.forEach(function (marca) {
      var opt = document.createElement("option");
      opt.value = marca;
      opt.textContent = marca;
      filterMarca.appendChild(opt);
    });
  }

  if (filtersToggle && filterPanel) {
    filtersToggle.addEventListener("click", function () {
      var isOpen = filterPanel.classList.toggle("is-open");
      filtersToggle.classList.toggle("is-open", isOpen);
      filtersToggle.setAttribute("aria-expanded", String(isOpen));
    });
  }

  [
    [filterMarca, "marca"],
    [filterAnoMin, "anoMin"],
    [filterKmMax, "kmMax"],
    [filterPrecoMax, "precoMax"]
  ].forEach(function (pair) {
    var el = pair[0], key = pair[1];
    if (!el) return;
    el.addEventListener("input", function () {
      advFilters[key] = el.value;
      render();
    });
    el.addEventListener("change", function () {
      advFilters[key] = el.value;
      render();
    });
  });

  if (filterClear) {
    filterClear.addEventListener("click", function () {
      advFilters = { marca: "", anoMin: "", kmMax: "", precoMax: "" };
      if (filterMarca) filterMarca.value = "";
      if (filterAnoMin) filterAnoMin.value = "";
      if (filterKmMax) filterKmMax.value = "";
      if (filterPrecoMax) filterPrecoMax.value = "";
      render();
    });
  }

  // ---- logo animada do header: ao terminar o reveal, mostra a logo estática (sem fumaça) ----
  (function initLogoReveal() {
    var box = document.getElementById("brandLogo");
    if (!box) return;
    var video = box.querySelector(".brand__logo--anim");
    if (!video) return;
    function revelar() { box.classList.add("is-revealed"); }
    video.addEventListener("ended", revelar);
    // fallback: se o autoplay for bloqueado, mostra a logo estática mesmo assim
    setTimeout(function () {
      if (video.paused && video.currentTime === 0) revelar();
    }, 1600);
  })();

  var navToggle = document.getElementById("navToggle");
  var siteNav = document.getElementById("siteNav");
  if (navToggle && siteNav) {
    navToggle.addEventListener("click", function () {
      var isOpen = siteNav.classList.toggle("is-open");
      navToggle.setAttribute("aria-expanded", String(isOpen));
    });
    siteNav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        siteNav.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  // ---- theme toggle (light/dark) ----
  var themeToggle = document.getElementById("themeToggle");
  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      var isLight = document.documentElement.getAttribute("data-theme") === "light";
      if (isLight) {
        document.documentElement.removeAttribute("data-theme");
        localStorage.setItem("skinao-theme", "dark");
      } else {
        document.documentElement.setAttribute("data-theme", "light");
        localStorage.setItem("skinao-theme", "light");
      }
    });
  }

  // ---- hero carousel ----
  (function initHeroCarousel() {
    var carousel = document.getElementById("heroCarousel");
    if (!carousel || carousel.classList.contains("hero-carousel--empty")) return;
    var slides = carousel.querySelectorAll(".hero-carousel__slide");
    var dots = carousel.querySelectorAll(".hero-carousel__dot");
    if (slides.length < 2) return;
    var current = 0;
    var timer = null;

    function goTo(index) {
      slides[current].classList.remove("is-active");
      dots[current] && dots[current].classList.remove("is-active");
      current = (index + slides.length) % slides.length;
      slides[current].classList.add("is-active");
      dots[current] && dots[current].classList.add("is-active");
    }

    function start() {
      if (prefersReducedMotion) return;
      stop();
      timer = setInterval(function () { goTo(current + 1); }, 5000);
    }
    function stop() {
      if (timer) clearInterval(timer);
      timer = null;
    }

    dots.forEach(function (dot, i) {
      dot.addEventListener("click", function () {
        goTo(i);
        start();
      });
    });

    carousel.addEventListener("mouseenter", stop);
    carousel.addEventListener("mouseleave", start);
    carousel.addEventListener("focusin", stop);
    carousel.addEventListener("focusout", start);

    start();
  })();

  // ---- detail modal ----
  (function initModal() {
    var overlay = document.getElementById("motoModal");
    var closeBtn = document.getElementById("modalClose");
    var mediaEl = document.getElementById("modalMedia");
    var bodyEl = document.getElementById("modalBody");
    if (!overlay) return;

    var currentMoto = null;
    var currentPhoto = 0;
    var lastFocused = null;

    function renderMedia() {
      var fotos = (currentMoto.fotos && currentMoto.fotos.length) ? currentMoto.fotos : (currentMoto.foto ? [currentMoto.foto] : []);
      if (!fotos.length) {
        mediaEl.innerHTML = '<div class="modal__media-empty">' + ICONS.camera + '</div>';
        return;
      }
      var html = '<img src="' + fotos[currentPhoto] + '" alt="">';
      if (fotos.length > 1) {
        html += '<button class="modal__arrow modal__arrow--prev" id="modalPrev" aria-label="Imagem anterior" ' + (currentPhoto === 0 ? "disabled" : "") + '>' + ICONS.chevronLeft + '</button>';
        html += '<button class="modal__arrow modal__arrow--next" id="modalNext" aria-label="Próxima imagem" ' + (currentPhoto === fotos.length - 1 ? "disabled" : "") + '>' + ICONS.chevronRight + '</button>';
        html += '<div class="modal__dots">' + fotos.map(function (_, i) {
          return '<span class="' + (i === currentPhoto ? "is-active" : "") + '"></span>';
        }).join("") + '</div>';
      }
      mediaEl.innerHTML = html;

      var prevBtn = document.getElementById("modalPrev");
      var nextBtn = document.getElementById("modalNext");
      if (prevBtn) prevBtn.addEventListener("click", function () { movePhoto(-1); });
      if (nextBtn) nextBtn.addEventListener("click", function () { movePhoto(1); });
    }

    function movePhoto(delta) {
      var fotos = (currentMoto.fotos && currentMoto.fotos.length) ? currentMoto.fotos : (currentMoto.foto ? [currentMoto.foto] : []);
      var next = currentPhoto + delta;
      if (next < 0 || next > fotos.length - 1) return;
      currentPhoto = next;
      renderMedia();
    }

    function renderBody() {
      var m = currentMoto;
      var specs = [
        { icon: ICONS.calendar, label: "Ano", value: String(m.ano) }
      ];
      if (m.km !== null && m.km !== undefined) {
        specs.push({ icon: ICONS.gauge, label: "Quilometragem", value: kmFormat.format(m.km) + " km" });
      }
      if (m.cor) specs.push({ icon: ICONS.palette, label: "Cor", value: m.cor });

      var specsHtml = specs.map(function (s) {
        return '<div class="modal__spec"><span class="modal__spec-label">' + s.icon + s.label + '</span><span class="modal__spec-value">' + escapeHtml(s.value) + '</span></div>';
      }).join("");

      bodyEl.innerHTML =
        '<h3 class="modal__title" id="modalTitle">' + escapeHtml(m.marca + " " + m.modelo) + '</h3>' +
        '<div class="modal__specs">' + specsHtml + '</div>' +
        (m.descricao ? '<p class="modal__desc">' + escapeHtml(m.descricao) + '</p>' : '') +
        '<p class="modal__price">' + currency.format(m.preco) + '</p>' +
        '<a class="btn btn--primary btn--block" target="_blank" rel="noopener" href="' + whatsappMotoUrl(m) + '">Falar no WhatsApp</a>';
    }

    function openModal(m) {
      currentMoto = m;
      currentPhoto = 0;
      lastFocused = document.activeElement;
      renderMedia();
      renderBody();
      overlay.classList.add("is-open");
      overlay.setAttribute("aria-hidden", "false");
      document.documentElement.classList.add("no-scroll");
      closeBtn.focus();
    }

    function closeModal() {
      overlay.classList.remove("is-open");
      overlay.setAttribute("aria-hidden", "true");
      document.documentElement.classList.remove("no-scroll");
      currentMoto = null;
      if (lastFocused && lastFocused.focus) lastFocused.focus();
    }

    function findMotoCard(el) {
      var card = el.closest(".card");
      if (!card) return null;
      var id = Number(card.dataset.id);
      return motos.find(function (m) { return m.id === id; }) || null;
    }

    document.addEventListener("click", function (e) {
      if (e.target.closest("[data-whatsapp-btn]")) return;
      var card = e.target.closest(".card");
      if (!card) return;
      var m = findMotoCard(e.target);
      if (m) openModal(m);
    });

    closeBtn.addEventListener("click", closeModal);
    overlay.addEventListener("click", function (e) {
      if (e.target === overlay) closeModal();
    });
    document.addEventListener("keydown", function (e) {
      if (!overlay.classList.contains("is-open")) return;
      if (e.key === "Escape") closeModal();
      if (e.key === "ArrowLeft") movePhoto(-1);
      if (e.key === "ArrowRight") movePhoto(1);
    });
  })();

  render();
  renderDestaque();
})();

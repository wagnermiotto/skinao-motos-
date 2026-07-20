/**
 * Skinão Motos — camada de UX premium (reveal ao rolar + count-up).
 * Leve, sem dependências, acelerado por GPU (transform/opacity).
 * Usa detecção por posição (getBoundingClientRect) com listener de scroll passivo
 * + rAF — robusto e previsível. Exposto em window.Skinao para conteúdo dinâmico.
 */
(function () {
  "use strict";

  var reduz = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var Skinao = window.Skinao || {};
  window.Skinao = Skinao;

  var pendReveal = [];
  var pendCount = [];
  var agendado = false;
  var ligado = false;

  function emVista(el, margem) {
    var r = el.getBoundingClientRect();
    var h = window.innerHeight || document.documentElement.clientHeight;
    if (r.width === 0 && r.height === 0) return false;
    return r.top < h * (margem || 0.92) && r.bottom > 0;
  }

  /* ---------------- reveal ---------------- */
  function revela(el) {
    var d = el.getAttribute("data-reveal-delay");
    if (d) el.style.transitionDelay = d + "ms";
    el.classList.add("is-visible");
  }

  /* ---------------- count-up ---------------- */
  function formata(v, t) {
    var sinal = v < 0 ? "-" : "";
    var abs = Math.abs(v);
    if (t === "brl") return sinal + "R$ " + abs.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (t === "pct") return v.toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "%";
    if (t === "dec") return v.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (t === "dias") return Math.round(v).toLocaleString("pt-BR") + " dias";
    return Math.round(v).toLocaleString("pt-BR");
  }
  function conta(el) {
    var alvo = parseFloat(el.getAttribute("data-count"));
    if (isNaN(alvo)) return;
    var tipo = el.getAttribute("data-count-format") || "int";
    if (reduz) { el.textContent = formata(alvo, tipo); return; }
    var dur = 1100, ini = null;
    function passo(ts) {
      if (ini === null) ini = ts;
      var p = Math.min((ts - ini) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = formata(alvo * eased, tipo);
      if (p < 1) requestAnimationFrame(passo);
      else el.textContent = formata(alvo, tipo);
    }
    requestAnimationFrame(passo);
  }

  /* ---------------- varredura ---------------- */
  function varrer() {
    agendado = false;
    var i;
    for (i = pendReveal.length - 1; i >= 0; i--) {
      if (emVista(pendReveal[i])) { revela(pendReveal[i]); pendReveal.splice(i, 1); }
    }
    for (i = pendCount.length - 1; i >= 0; i--) {
      if (emVista(pendCount[i], 0.85)) {
        pendCount[i].setAttribute("data-counted", "1");
        conta(pendCount[i]);
        pendCount.splice(i, 1);
      }
    }
    if (!pendReveal.length && !pendCount.length) desliga();
  }
  function agenda() {
    if (agendado) return;
    agendado = true;
    if (window.requestAnimationFrame) requestAnimationFrame(varrer);
    else setTimeout(varrer, 16);
  }
  function liga() {
    if (ligado) return;
    ligado = true;
    window.addEventListener("scroll", agenda, { passive: true });
    window.addEventListener("resize", agenda, { passive: true });
  }
  function desliga() {
    if (!ligado) return;
    ligado = false;
    window.removeEventListener("scroll", agenda);
    window.removeEventListener("resize", agenda);
  }

  Skinao.revealScan = function (raiz) {
    var els = (raiz || document).querySelectorAll("[data-reveal]:not(.is-visible)");
    if (reduz) { [].forEach.call(els, revela); return; }
    [].forEach.call(els, function (el) { if (pendReveal.indexOf(el) === -1) pendReveal.push(el); });
    liga();
    varrer(); // varredura imediata (síncrona) para os elementos já em vista
  };

  Skinao.countUpScan = function (raiz) {
    var els = (raiz || document).querySelectorAll("[data-count]:not([data-counted])");
    if (reduz) { [].forEach.call(els, function (el) { el.setAttribute("data-counted", "1"); conta(el); }); return; }
    [].forEach.call(els, function (el) { if (pendCount.indexOf(el) === -1) pendCount.push(el); });
    liga();
    varrer();
  };

  function init() {
    Skinao.revealScan(document);
    Skinao.countUpScan(document);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();

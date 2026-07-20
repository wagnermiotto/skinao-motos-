(function () {
  "use strict";
  if (typeof Chart === "undefined" || !window.DASH) return;

  var D = window.DASH;

  var CORES = {
    red: "#dc2626",
    redSoft: "rgba(220, 38, 38, 0.55)",
    blue: "#2f6fb5",
    blueSoft: "rgba(47, 111, 181, 0.55)",
    green: "#22c55e",
    greenSoft: "rgba(34, 197, 94, 0.55)",
    grid: "rgba(255, 255, 255, 0.06)",
    tick: "#a4a4a4",
  };

  var paleta = ["#dc2626", "#2f6fb5", "#22c55e", "#f59e0b", "#a855f7", "#06b6d4", "#ef4444", "#84cc16"];

  Chart.defaults.color = CORES.tick;
  Chart.defaults.font.family = "Inter, sans-serif";
  Chart.defaults.plugins.legend.labels.boxWidth = 12;
  Chart.defaults.aspectRatio = 1.7;
  Chart.defaults.maintainAspectRatio = true;

  var moeda = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 });

  function eixoMoeda() {
    return {
      x: { grid: { color: CORES.grid }, ticks: { color: CORES.tick } },
      y: { grid: { color: CORES.grid }, ticks: { color: CORES.tick, callback: function (v) { return moeda.format(v); } } },
    };
  }
  function eixoNum() {
    return {
      x: { grid: { color: CORES.grid }, ticks: { color: CORES.tick } },
      y: { grid: { color: CORES.grid }, ticks: { color: CORES.tick, precision: 0 }, beginAtZero: true },
    };
  }

  function criar(id, config) {
    var el = document.getElementById(id);
    if (el) new Chart(el, config);
  }

  var semLegenda = { legend: { display: false } };

  criar("chReceita", {
    type: "bar",
    data: { labels: D.meses, datasets: [{ data: D.receita_mes, backgroundColor: CORES.redSoft, borderColor: CORES.red, borderWidth: 1, borderRadius: 4 }] },
    options: { plugins: semLegenda, scales: eixoMoeda() },
  });

  criar("chLucro", {
    type: "bar",
    data: { labels: D.meses, datasets: [{ data: D.lucro_mes, backgroundColor: CORES.greenSoft, borderColor: CORES.green, borderWidth: 1, borderRadius: 4 }] },
    options: { plugins: semLegenda, scales: eixoMoeda() },
  });

  criar("chVendas", {
    type: "bar",
    data: { labels: D.meses, datasets: [{ data: D.vendas_mes, backgroundColor: CORES.blueSoft, borderColor: CORES.blue, borderWidth: 1, borderRadius: 4 }] },
    options: { plugins: semLegenda, scales: eixoNum() },
  });

  criar("chEvolFat", {
    type: "line",
    data: { labels: D.meses, datasets: [{ data: D.acum_fat, borderColor: CORES.red, backgroundColor: CORES.redSoft, fill: true, tension: 0.3, pointRadius: 2 }] },
    options: { plugins: semLegenda, scales: eixoMoeda() },
  });

  criar("chEvolLucro", {
    type: "line",
    data: { labels: D.meses, datasets: [{ data: D.acum_lucro, borderColor: CORES.green, backgroundColor: CORES.greenSoft, fill: true, tension: 0.3, pointRadius: 2 }] },
    options: { plugins: semLegenda, scales: eixoMoeda() },
  });

  function entradas(obj) {
    return { labels: Object.keys(obj), valores: Object.values(obj) };
  }

  var marca = entradas(D.vendas_marca);
  criar("chMarca", {
    type: "doughnut",
    data: { labels: marca.labels, datasets: [{ data: marca.valores, backgroundColor: paleta, borderWidth: 0 }] },
    options: { plugins: { legend: { position: "bottom" } } },
  });

  var modelo = entradas(D.vendas_modelo);
  criar("chModelo", {
    type: "bar",
    data: { labels: modelo.labels, datasets: [{ data: modelo.valores, backgroundColor: CORES.blueSoft, borderColor: CORES.blue, borderWidth: 1, borderRadius: 4 }] },
    options: { indexAxis: "y", plugins: semLegenda, scales: eixoNum() },
  });

  var estCat = entradas(D.estoque_categoria);
  criar("chEstCat", {
    type: "doughnut",
    data: { labels: estCat.labels, datasets: [{ data: estCat.valores, backgroundColor: paleta, borderWidth: 0 }] },
    options: { plugins: { legend: { position: "bottom" } } },
  });

  var estFaixa = entradas(D.estoque_faixa);
  criar("chEstFaixa", {
    type: "bar",
    data: { labels: estFaixa.labels, datasets: [{ data: estFaixa.valores, backgroundColor: CORES.redSoft, borderColor: CORES.red, borderWidth: 1, borderRadius: 4 }] },
    options: { plugins: semLegenda, scales: eixoNum() },
  });
})();

/* Galería: filtros por categoría de los trabajos de muestra y el visor ampliado
   (dialog) que clona el SVG de la pieza tocada para mostrarla más grande. */
window.TH = window.TH || {};

(function () {
  const { $, $$, waLink } = TH.utils;

  function init() {
    const filtros = $$(".filtro");
    filtros.forEach(b => b.addEventListener("click", () => {
      filtros.forEach(x => x.setAttribute("aria-pressed", String(x === b)));
      $$("#galeria > li").forEach(li => { li.hidden = !(b.dataset.f === "todo" || li.dataset.cat === b.dataset.f); });
    }));

    const visor = $("#visor");
    $$(".work").forEach(btn => btn.addEventListener("click", () => {
      const svg = $("svg", btn).cloneNode(true);
      $("#visor-art").replaceChildren(svg);
      $("#visor-art").style.background = getComputedStyle($(".work-art", btn)).backgroundColor;
      $("#visor-t").textContent = btn.dataset.title;
      $("#visor-s").textContent = btn.dataset.spec;
      $("#visor-d").textContent = btn.dataset.desc;
      $("#visor-cta").href = waLink("Hola, vi «" + btn.dataset.title + "» en la web y quiero algo parecido.");
      visor.showModal();
    }));
    $("#visor-x").addEventListener("click", () => visor.close());
    visor.addEventListener("click", e => { if (e.target === visor) visor.close(); });
  }

  TH.galeria = { init };
})();

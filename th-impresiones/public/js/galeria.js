/* Galería: construye los botones de filtro y las tarjetas de trabajos a partir
   de TH.categoriasTrabajos y TH.trabajos (cargados por datos.js), y maneja el
   visor ampliado. Las imágenes son <img> normales: el panel de administración
   decide qué foto va en cada trabajo, acá solo se muestran. */
window.TH = window.TH || {};

(function () {
  const { $, $$, waLink, esc } = TH.utils;

  let filtrosEl, galeriaEl, visor;

  function colorDeCategoria(id) {
    const c = (TH.categoriasTrabajos || []).find(c => c.id === id);
    return c ? c.color : "";
  }

  function renderFiltros() {
    const cats = TH.categoriasTrabajos || [];
    filtrosEl.innerHTML = '<button class="tab filtro" type="button" data-f="todo" aria-pressed="true">Todo</button>' +
      cats.map(c =>
        '<button class="tab filtro" type="button" data-f="' + esc(c.id) + '" aria-pressed="false" style="--tab-c:' + esc(c.color) + ';--tab-c-fg:#16244F">' + esc(c.nombre) + "</button>"
      ).join("");
  }

  function renderGaleria() {
    const trabajos = TH.trabajos || [];
    galeriaEl.innerHTML = trabajos.map(t => {
      const color = colorDeCategoria(t.categoria);
      return '<li data-cat="' + esc(t.categoria) + '">' +
        '<button class="work" type="button" data-id="' + esc(t.id) + '">' +
        '<span class="work-art" style="background:' + esc(color) + '"><img src="' + esc(t.imagen) + '" alt="' + esc(t.titulo) + '" loading="lazy"></span>' +
        '<span class="work-cap"><span class="work-name">' + esc(t.titulo) + '</span><span class="work-spec">' + esc(t.especificacion) + "</span></span>" +
        "</button></li>";
    }).join("");

    $$(".work", galeriaEl).forEach(btn => btn.addEventListener("click", () => {
      const t = trabajos.find(x => x.id === btn.dataset.id);
      if (!t) return;
      const img = $("#visor-img");
      img.src = t.imagen;
      img.alt = t.titulo;
      $("#visor-art").style.background = colorDeCategoria(t.categoria);
      $("#visor-t").textContent = t.titulo;
      $("#visor-s").textContent = t.especificacion;
      $("#visor-d").textContent = t.descripcion;
      $("#visor-cta").href = waLink("Hola, vi «" + t.titulo + "» en la web y quiero algo parecido.");
      visor.showModal();
    }));
  }

  function init() {
    filtrosEl = $(".filtros");
    galeriaEl = $("#galeria");
    visor = $("#visor");

    renderFiltros();
    renderGaleria();

    filtrosEl.addEventListener("click", e => {
      const b = e.target.closest(".filtro");
      if (!b) return;
      $$(".filtro", filtrosEl).forEach(x => x.setAttribute("aria-pressed", String(x === b)));
      $$("#galeria > li").forEach(li => { li.hidden = !(b.dataset.f === "todo" || li.dataset.cat === b.dataset.f); });
    });

    $("#visor-x").addEventListener("click", () => visor.close());
    visor.addEventListener("click", e => { if (e.target === visor) visor.close(); });
  }

  TH.galeria = { init };
})();

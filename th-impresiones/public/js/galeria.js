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

  let fotos = [], fotoIdx = 0, fotoTitulo = "";

  function pintarFoto() {
    const img = $("#visor-img");
    img.src = fotos[fotoIdx];
    img.alt = fotoTitulo + (fotos.length > 1 ? " (" + (fotoIdx + 1) + " de " + fotos.length + ")" : "");
    $$("#visor-thumbs button").forEach((b, i) => b.setAttribute("aria-current", String(i === fotoIdx)));
  }

  function irAFoto(delta) {
    fotoIdx = (fotoIdx + delta + fotos.length) % fotos.length;
    pintarFoto();
  }

  function mostrarFotos(t) {
    fotos = Array.isArray(t.imagenes) && t.imagenes.length ? t.imagenes : [t.imagen];
    fotoIdx = 0;
    fotoTitulo = t.titulo;
    const varias = fotos.length > 1;
    $("#visor-prev").hidden = !varias;
    $("#visor-next").hidden = !varias;
    const thumbs = $("#visor-thumbs");
    thumbs.hidden = !varias;
    thumbs.innerHTML = varias ? fotos.map((f, i) =>
      '<button type="button" data-i="' + i + '" aria-label="Ver foto ' + (i + 1) + '"><img src="' + esc(f) + '" alt=""></button>'
    ).join("") : "";
    pintarFoto();
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
      mostrarFotos(t);
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

    $("#visor-prev").addEventListener("click", () => irAFoto(-1));
    $("#visor-next").addEventListener("click", () => irAFoto(1));
    $("#visor-thumbs").addEventListener("click", e => {
      const b = e.target.closest("button");
      if (!b) return;
      fotoIdx = Number(b.dataset.i);
      pintarFoto();
    });
    visor.addEventListener("keydown", e => {
      if (fotos.length < 2) return;
      if (e.key === "ArrowLeft") irAFoto(-1);
      else if (e.key === "ArrowRight") irAFoto(1);
    });
    $("#visor-x").addEventListener("click", () => visor.close());
    visor.addEventListener("click", e => { if (e.target === visor) visor.close(); });
  }

  TH.galeria = { init };
})();

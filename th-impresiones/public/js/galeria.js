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

  function listaFotos(t) {
    return Array.isArray(t.imagenes) && t.imagenes.length ? t.imagenes : [t.imagen];
  }

  /* Carrusel: pista con scroll-snap (se desliza con el dedo o el mouse), flechas
     y puntitos. Devuelve el HTML; carruselInit() le conecta los botones. */
  function carruselHtml(fotos, titulo, eager) {
    const varias = fotos.length > 1;
    return '<div class="car" data-n="' + fotos.length + '">' +
      '<div class="car-pista">' + fotos.map((f, i) =>
        '<img src="' + esc(f) + '" alt="' + esc(titulo) + (varias ? " (" + (i + 1) + " de " + fotos.length + ")" : "") + '"' + (eager || i === 0 ? "" : ' loading="lazy"') + '>'
      ).join("") + '</div>' +
      (varias ? '<button class="car-nav car-prev" type="button" aria-label="Foto anterior">‹</button>' +
        '<button class="car-nav car-next" type="button" aria-label="Foto siguiente">›</button>' +
        '<div class="car-dots" aria-hidden="true">' + fotos.map((_, i) => '<i' + (i === 0 ? ' class="on"' : "") + "></i>").join("") + "</div>" : "") +
      "</div>";
  }

  function carruselInit(car) {
    const pista = $(".car-pista", car);
    const n = Number(car.dataset.n);
    if (n < 2) return;
    const dots = $$(".car-dots i", car);
    const ir = i => pista.scrollTo({ left: pista.clientWidth * ((i + n) % n), behavior: "smooth" });
    const actual = () => Math.round(pista.scrollLeft / pista.clientWidth);
    $(".car-prev", car).addEventListener("click", e => { e.stopPropagation(); ir(actual() - 1); });
    $(".car-next", car).addEventListener("click", e => { e.stopPropagation(); ir(actual() + 1); });
    pista.addEventListener("scroll", () => {
      const i = actual();
      dots.forEach((d, k) => d.classList.toggle("on", k === i));
    }, { passive: true });
  }

  function renderGaleria() {
    const trabajos = TH.trabajos || [];
    galeriaEl.innerHTML = trabajos.map(t => {
      const color = colorDeCategoria(t.categoria);
      return '<li data-cat="' + esc(t.categoria) + '">' +
        '<div class="work-wrap">' +
        '<span class="work-art" style="background:' + esc(color) + '">' + carruselHtml(listaFotos(t), t.titulo, false) + "</span>" +
        '<button class="work" type="button" data-id="' + esc(t.id) + '">' +
        '<span class="work-cap"><span class="work-name">' + esc(t.titulo) + '</span><span class="work-spec">' + esc(t.especificacion) + "</span></span>" +
        "</button></div></li>";
    }).join("");

    $$("#galeria .car").forEach(carruselInit);

    function abrir(t) {
      const art = $("#visor-art");
      art.innerHTML = carruselHtml(listaFotos(t), t.titulo, true);
      carruselInit($(".car", art));
      art.style.background = colorDeCategoria(t.categoria);
      $("#visor-t").textContent = t.titulo;
      $("#visor-s").textContent = t.especificacion;
      $("#visor-d").textContent = t.descripcion;
      $("#visor-cta").href = waLink("Hola, vi «" + t.titulo + "» en la web y quiero algo parecido.");
      visor.showModal();
    }

    // Clic en la foto o en el título abre el visor; las flechas no.
    $$("#galeria li").forEach((li, i) => {
      const t = trabajos[i];
      $(".work", li).addEventListener("click", () => abrir(t));
      $(".work-art", li).addEventListener("click", e => { if (!e.target.closest(".car-nav")) abrir(t); });
    });
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

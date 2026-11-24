/* Main: arranca el tema (no depende de datos.json) de inmediato, carga
   data/datos.json y, si sale bien, inicia la calculadora, la galería y el
   contacto; si falla, muestra el aviso amable en vez de romper la página. */
window.TH = window.TH || {};

(function () {
  TH.tema.init();

  /* Portada: imagen y textos editables desde el panel (pantalla "Portada").
     Si no hay nada cargado, quedan los textos escritos en index.html. */
  function pintarPortada() {
    const { $, esc } = TH.utils;
    const heroFig = $("#hero-img");
    if (heroFig && TH.config.imagenHero) {
      const img = $("#hero-img-el");
      img.src = TH.config.imagenHero;
      img.alt = (TH.portada && TH.portada.imagenAlt) || "";
      heroFig.hidden = false;
    }
    const p = TH.portada;
    if (!p) return;
    $("#hero-etiqueta").textContent = p.etiqueta || "";
    $("#hero-etiqueta").hidden = !p.etiqueta;
    $("#hero-titulo").innerHTML = String(p.titulo || "").split("\n").filter(Boolean).map(l => "<span>" + esc(l) + "</span>").join("");
    $("#hero-texto").textContent = p.texto || "";
    $("#hero-texto").hidden = !p.texto;
    $("#hero-btn1").textContent = p.botonPrincipal || "Calcular mi pedido";
    $("#hero-btn2").textContent = p.botonSecundario || "";
    $("#hero-btn2").hidden = !p.botonSecundario;
    const datos = Array.isArray(p.datos) ? p.datos : [];
    $("#hero-datos").innerHTML = datos.map(d => "<div><dt>" + esc(d.titulo) + "</dt><dd>" + esc(d.valor) + "</dd></div>").join("");
    $("#hero-datos").hidden = !datos.length;
    $("#hero-datos").style.gridTemplateColumns = "repeat(" + Math.max(datos.length, 1) + ",minmax(0,1fr))";
  }

  async function iniciar() {
    const ok = await TH.datos.cargar();
    if (!ok) {
      const aviso = document.getElementById("aviso-datos");
      if (aviso) aviso.hidden = false;
      return;
    }

    pintarPortada();

    TH.calculadora.init();
    TH.galeria.init();
    TH.contacto.init();

    TH.utils.$$(".js-wa").forEach(a => {
      a.href = TH.utils.waLink("Hola, quiero consultar por un trabajo de impresión.");
    });
  }

  iniciar();
})();

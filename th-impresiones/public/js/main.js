/* Main: arranca el tema (no depende de datos.json) de inmediato, carga
   data/datos.json y, si sale bien, inicia la calculadora, la galería y el
   contacto; si falla, muestra el aviso amable en vez de romper la página. */
window.TH = window.TH || {};

(function () {
  TH.tema.init();

  async function iniciar() {
    const ok = await TH.datos.cargar();
    if (!ok) {
      const aviso = document.getElementById("aviso-datos");
      if (aviso) aviso.hidden = false;
      return;
    }

    TH.calculadora.init();
    TH.galeria.init();
    TH.contacto.init();

    TH.utils.$$(".js-wa").forEach(a => {
      a.href = TH.utils.waLink("Hola, quiero consultar por un trabajo de impresión.");
    });
  }

  iniciar();
})();

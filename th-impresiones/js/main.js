/* Main: inicia todos los módulos en orden y resuelve los enlaces genéricos de
   WhatsApp (botón "Escribinos" del encabezado y "Abrir" de la ficha de contacto). */
window.TH = window.TH || {};

(function () {
  TH.calculadora.init();
  TH.galeria.init();
  TH.contacto.init();
  TH.tema.init();

  TH.utils.$$(".js-wa").forEach(a => {
    a.href = TH.utils.waLink("Hola, quiero consultar por un trabajo de impresión.");
  });
})();

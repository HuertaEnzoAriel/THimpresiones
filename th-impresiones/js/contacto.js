/* Contacto: vuelca los datos de contacto (teléfono, mail, Instagram, horarios),
   los botones de copiar de la ficha, y el formulario que arma el mensaje de
   WhatsApp sin enviar nada a ningún servidor. */
window.TH = window.TH || {};

(function () {
  const { $, $$, waLink, copyText } = TH.utils;
  const CONFIG = TH.config;
  const ITEMS = TH.precios.items;

  function init() {
    $$("[data-cfg]").forEach(el => { el.textContent = CONFIG[el.dataset.cfg] || ""; });
    $("#ig-link").href = "https://instagram.com/" + CONFIG.instagram.replace(/^@/, "");
    $("#horarios").innerHTML = CONFIG.horarios.map(([d, h]) => "<div><dt>" + d + "</dt><dd>" + h + "</dd></div>").join("");
    $$("[data-copy]").forEach(b => b.addEventListener("click", () => {
      const el = document.getElementById(b.dataset.copy);
      copyText(el.textContent, b, el);
    }));

    const pTrabajo = $("#p-trabajo");
    pTrabajo.innerHTML = ITEMS.map(i => '<option value="' + i.nombre + '">' + i.nombre + "</option>").join("") +
      '<option value="un trabajo a medida">Otro trabajo a medida</option>';

    function mensajePedido() {
      const nombre = $("#p-nombre").value.trim();
      const detalle = $("#p-detalle").value.trim();
      return "Hola" + (nombre ? ", soy " + nombre : "") + ". Quiero consultar por: " + pTrabajo.value + "." +
        (detalle ? " Detalle: " + detalle + "." : "") + " ¿Me pasan precio y plazo?";
    }
    function renderPedido() {
      const msg = mensajePedido();
      $("#p-preview").textContent = msg;
      $("#p-wa").href = waLink(msg);
    }

    ["#p-nombre", "#p-trabajo", "#p-detalle"].forEach(s => $(s).addEventListener("input", renderPedido));
    $("#pedido").addEventListener("submit", e => e.preventDefault());
    $("#p-copy").addEventListener("click", e => copyText(mensajePedido(), e.currentTarget, $("#p-preview")));
    renderPedido();
  }

  TH.contacto = { init };
})();

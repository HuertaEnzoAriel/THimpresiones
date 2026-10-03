/* Contacto: vuelca los datos de contacto (teléfono, mail, Instagram, horarios),
   los botones de copiar de la ficha, y el formulario que arma el mensaje de
   WhatsApp sin enviar nada a ningún servidor. Lee TH.config y TH.precios.items
   recién dentro de init(), porque llegan de forma asíncrona (data/datos.json). */
window.TH = window.TH || {};

(function () {
  const { $, $$, waLink, copyText, esc } = TH.utils;

  function init() {
    const CONFIG = TH.config;
    const ITEMS = TH.precios.items;

    $$("[data-cfg]").forEach(el => { el.textContent = CONFIG[el.dataset.cfg] || ""; });
    if (CONFIG.instagram) $("#ig-link").href = "https://instagram.com/" + CONFIG.instagram.replace(/^@/, "");
    $("#horarios").innerHTML = (CONFIG.horarios || []).map(h =>
      "<div><dt>" + esc(h.dia) + "</dt><dd>" + esc(h.horas) + "</dd></div>"
    ).join("");
    $$("[data-copy]").forEach(b => b.addEventListener("click", () => {
      const el = document.getElementById(b.dataset.copy);
      copyText(el.textContent, b, el);
    }));

    const pTrabajo = $("#p-trabajo");
    pTrabajo.innerHTML = ITEMS.map(i => '<option value="' + esc(i.nombre) + '">' + esc(i.nombre) + "</option>").join("") +
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

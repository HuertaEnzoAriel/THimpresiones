/* Contacto: vuelca los datos de contacto (teléfono, mail, Instagram, horarios),
   los botones de copiar de la ficha, y el formulario que arma el mensaje de
   WhatsApp sin enviar nada a ningún servidor. Lee TH.config y TH.precios.items
   recién dentro de init(), porque llegan de forma asíncrona (data/datos.json). */
window.TH = window.TH || {};

(function () {
  const { $, $$, waLink, copyText, esc } = TH.utils;

  /* Cartel Abierto/Cerrado según el horario (hora de Argentina). */
  function pintarEstado(semana) {
    const e = Array.isArray(semana) ? TH.horario.estado(semana) : null;
    $$(".abierto").forEach(el => {
      el.hidden = !e;
      if (!e) return;
      el.classList.toggle("cerrado", !e.abierto);
      el.innerHTML = '<i aria-hidden="true"></i>' + esc(e.texto);
    });
  }

  /* Checklist de archivo y preguntas frecuentes (sección "Antes de pedir"). */
  function renderAyuda() {
    const ayuda = TH.ayuda || {};
    const checklist = (Array.isArray(ayuda.checklist) ? ayuda.checklist : []).filter(Boolean);
    const preguntas = (Array.isArray(ayuda.preguntas) ? ayuda.preguntas : []).filter(p => p && p.pregunta);
    $("#checklist").innerHTML = checklist.map((txt, i) =>
      '<li><input type="checkbox" id="chk-' + i + '"><label for="chk-' + i + '">' + esc(txt) + "</label></li>"
    ).join("");
    $("#checklist-box").hidden = !checklist.length;
    $("#faq").innerHTML = preguntas.map(p =>
      "<details><summary>" + esc(p.pregunta) + "</summary><p>" + esc(p.respuesta) + "</p></details>"
    ).join("");
    $("#faq-box").hidden = !preguntas.length;
    $("#preguntas").hidden = !checklist.length && !preguntas.length;
  }

  function init() {
    const CONFIG = TH.config;
    const ITEMS = TH.precios.items;

    $$("[data-cfg]").forEach(el => { el.textContent = CONFIG[el.dataset.cfg] || ""; });
    if (CONFIG.instagram) $("#ig-link").href = "https://instagram.com/" + CONFIG.instagram.replace(/^@/, "");
    // Horario semanal (lo nuevo) o, si los datos son viejos, la lista de texto.
    const semana = CONFIG.horariosSemana;
    const filas = Array.isArray(semana) ? TH.horario.agrupar(semana) : (CONFIG.horarios || []);
    $("#horarios").innerHTML = filas.map(h =>
      "<div><dt>" + esc(h.dia) + "</dt><dd>" + esc(h.horas) + "</dd></div>"
    ).join("");
    pintarEstado(semana);
    setInterval(() => pintarEstado(semana), 60000);

    renderAyuda();
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

/* Calculadora visual de pedido. Todo lo que se puede elegir sale de
   TH.calc (sección "calculadora" de data/datos.json, editable desde el panel):
   - trabajos: tarjetas con ícono; cada uno dice qué pasos usa (tamaño, faz,
     color, papel) y con qué opciones. Un paso con una sola opción no se
     muestra, pero cuenta en el precio y en el detalle.
   - el precio por cantidad sale de la lista de precios (precioId). Cada
     opción puede sumar un recargo en % o usar otro precio de la lista.
   - atajos que precargan la calculadora.
   Lee los datos recién dentro de init(): llegan de forma asíncrona. */
window.TH = window.TH || {};

(function () {
  const { $, $$, money, waLink, tierPrice, nextTier, rangeLabel, copyText, esc } = TH.utils;
  const I = TH.iconos;

  const PASOS = [
    { id: "tamano", titulo: "Tamaño" },
    { id: "faz", titulo: "Faz" },
    { id: "color", titulo: "Color" },
    { id: "papel", titulo: "Papel" }
  ];
  const FAZ = {
    simple: { nombre: "Simple faz", detalle: "Solo el frente" },
    doble: { nombre: "Doble faz", detalle: "Frente y dorso" }
  };
  const COLOR = {
    bn: { nombre: "Blanco y negro", detalle: "Escala de grises" },
    color: { nombre: "Color", detalle: "A todo color" }
  };
  const MAX = 9999;
  const PDFJS = "https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/";

  let cfg = { tamanos: [], papeles: [], trabajos: [], atajos: [] };
  let maxCm = 1;
  const st = { trabajo: null, sel: {}, qty: 1, archivo: null };

  function items() { return TH.precios.items; }

  /* ===== Configuración ===== */
  function leerConfig() {
    const c = TH.calc || {};
    const lista = a => (Array.isArray(a) ? a : []);
    cfg = { tamanos: lista(c.tamanos), papeles: lista(c.papeles), trabajos: lista(c.trabajos), atajos: lista(c.atajos) };
    cfg.trabajos = cfg.trabajos.filter(t => t && TH.precios.byId(t.precioId));
    // Sin configuración de calculadora (datos viejos): un trabajo por cada precio, sin pasos.
    if (!cfg.trabajos.length) {
      cfg.trabajos = items().map(i => ({ id: i.id, nombre: i.nombre, icono: I.adivinar(i.nombre), precioId: i.id, pasos: {} }));
    }
    maxCm = cfg.tamanos.reduce((m, t) => Math.max(m, Number(t.ancho) || 0, Number(t.alto) || 0), 1);
  }

  function infoOpcion(paso, id) {
    if (paso === "tamano") {
      const t = cfg.tamanos.find(x => x.id === id);
      return t && { nombre: t.nombre, detalle: I.cm(t.ancho) + " × " + I.cm(t.alto) + " cm", referencia: t.referencia || "", ancho: Number(t.ancho), alto: Number(t.alto) };
    }
    if (paso === "papel") {
      const p = cfg.papeles.find(x => x.id === id);
      return p && { nombre: p.nombre + " " + p.gramaje + " g", detalle: p.comparacion || "", gramaje: p.gramaje };
    }
    if (paso === "faz") return FAZ[id] || null;
    if (paso === "color") return COLOR[id] || null;
    return null;
  }
  function opciones(t, paso) {
    return ((t.pasos && t.pasos[paso]) || []).filter(o => o && infoOpcion(paso, o.id));
  }
  function elegida(t, paso) {
    return opciones(t, paso).find(o => o.id === st.sel[paso]) || null;
  }
  function textoOpcion(paso, info) {
    // Si el nombre ya es la medida (ej: "13 × 18"), no se repite.
    if (paso !== "tamano" || /\d\s*×\s*\d/.test(info.nombre)) return paso === "tamano" ? info.nombre + " cm" : info.nombre;
    return info.nombre + " (" + info.detalle + ")";
  }
  const clampQty = n => { n = parseInt(n, 10); return Number.isFinite(n) && n > 0 ? Math.min(n, MAX) : 1; };

  /* ===== Cálculo ===== */
  function calcular() {
    const t = st.trabajo;
    let precioId = t.precioId, factor = 1;
    const detalle = [];
    PASOS.forEach(p => {
      const o = elegida(t, p.id);
      if (!o) return;
      if (o.precioId && TH.precios.byId(o.precioId)) precioId = o.precioId;
      factor *= 1 + (Number(o.recargo) || 0) / 100;
      detalle.push({ paso: p, info: infoOpcion(p.id, o.id) });
    });
    const base = TH.precios.byId(precioId);
    const item = Object.assign({}, base, { escalas: base.escalas.map(e => ({ desde: e.desde, precio: Math.round(e.precio * factor) })) });
    const paginas = !!t.cuentaPaginas;
    const doble = st.sel.faz === "doble" && !!elegida(t, "faz");
    // En impresiones se cuenta por páginas: en doble faz cada hoja lleva dos.
    const unidades = paginas && doble ? Math.ceil(st.qty / 2) : st.qty;
    const unit = tierPrice(item, unidades);
    return { t, item, detalle, paginas, doble, unidades, unit, total: unit * unidades };
  }

  const nombreUnidad = (item, n) => (n === 1 ? item.unidad : item.plural);

  function textoCantidad(r) {
    if (!r.paginas) return st.qty + " " + nombreUnidad(r.item, st.qty);
    return st.qty + (st.qty === 1 ? " página" : " páginas") + " → " + r.unidades + " " + nombreUnidad(r.item, r.unidades);
  }

  function mensaje(r) {
    const L = ["Hola, quiero hacer este pedido:", "• Trabajo: " + r.t.nombre];
    r.detalle.forEach(d => L.push("• " + d.paso.titulo + ": " + textoOpcion(d.paso.id, d.info)));
    L.push("• Cantidad: " + textoCantidad(r));
    if (st.archivo) L.push("• Archivo: " + st.archivo + " (se los mando por acá)");
    L.push("• Precio por " + r.item.unidad + ": " + money(r.unit));
    L.push("• Total estimado: " + money(r.total));
    if (r.t.entrega) L.push("• Entrega: " + r.t.entrega);
    L.push("", "¿Me confirman precio y plazo?");
    return L.join("\n");
  }

  /* ===== Pintado ===== */
  function renderTrabajos() {
    $$("#calc-trabajos .trab").forEach(b => b.setAttribute("aria-pressed", String(b.dataset.id === st.trabajo.id)));
  }

  function botonOpcion(paso, o) {
    const info = infoOpcion(paso, o.id);
    const on = st.sel[paso] === o.id;
    let cuerpo;
    if (paso === "tamano") {
      cuerpo = I.svgTamano(info.ancho, info.alto, maxCm) + '<span class="opt-t">' + esc(info.nombre) + '</span><span class="opt-d">' + esc(info.detalle) + "</span>" +
        (info.referencia ? '<span class="opt-r">' + esc(info.referencia) + "</span>" : "");
    } else if (paso === "faz") {
      cuerpo = I.svgFaz(o.id === "doble") + '<span class="opt-t">' + esc(info.nombre) + '</span><span class="opt-d">' + esc(info.detalle) + "</span>";
    } else if (paso === "color") {
      cuerpo = I.franja(o.id === "color") + '<span class="opt-t">' + esc(info.nombre) + '</span><span class="opt-d">' + esc(info.detalle) + "</span>";
    } else {
      cuerpo = '<span class="opt-t">' + esc(info.nombre) + "</span>" + I.grosor(info.gramaje) + '<span class="opt-d">' + esc(info.detalle) + "</span>";
    }
    return '<button class="opt opt-' + paso + '" type="button" data-paso="' + paso + '" data-id="' + esc(o.id) + '" aria-pressed="' + on + '">' + cuerpo + "</button>";
  }

  // Arma los pasos visibles del trabajo elegido y renumera.
  function renderPasos() {
    const t = st.trabajo;
    let n = 1;
    $("#calc-pasos").innerHTML = PASOS.map(p => {
      const ops = opciones(t, p.id);
      if (ops.length < 2) return "";
      n++;
      return '<fieldset class="paso"><legend><span class="paso-n">' + n + "</span>" + p.titulo + "</legend>" +
        '<div class="opts opts-' + p.id + '">' + ops.map(o => botonOpcion(p.id, o)).join("") + "</div></fieldset>";
    }).join("");
    $("#paso-cant-n").textContent = String(n + 1);
    $("#calc-pdf").hidden = !t.cuentaPaginas;
    $("#calc-pdf-estado").textContent = "Tu archivo no se sube: las páginas se cuentan en tu navegador.";
  }

  function chipsDe(t, r) {
    const propias = (Array.isArray(t.cantidades) ? t.cantidades : []).map(Number).filter(n => n > 0);
    return propias.length ? propias : r.item.escalas.map(e => e.desde);
  }

  function hojaPreview(r) {
    const wrap = $("#calc-hoja-wrap");
    const tam = r.detalle.find(d => d.paso.id === "tamano");
    if (!tam) { wrap.hidden = true; return; }
    wrap.hidden = false;
    const hoja = $("#calc-hoja");
    const caja = { w: 240, h: 160 };
    const esc2 = Math.min(caja.w / tam.info.ancho, caja.h / tam.info.alto);
    hoja.style.width = Math.round(tam.info.ancho * esc2) + "px";
    hoja.style.height = Math.round(tam.info.alto * esc2) + "px";
    const colorSel = elegida(r.t, "color");
    hoja.classList.toggle("bn", !!colorSel && colorSel.id === "bn");
    hoja.classList.toggle("foto", r.t.icono === "foto");
    const fazSel = elegida(r.t, "faz");
    const dorsoImpreso = !!fazSel && fazSel.id === "doble";
    hoja.classList.toggle("dorso-impreso", dorsoImpreso);
    $("#calc-hoja-ayuda").textContent = (hoja.getAttribute("aria-pressed") === "true" ? "Viendo el dorso" : "Viendo el frente") +
      " · " + (dorsoImpreso ? "dorso impreso" : "dorso en blanco") + ". Tocá la hoja para darla vuelta.";
  }

  function render() {
    const r = calcular();
    const t = r.t;
    const item = r.item;

    $("#calc-unit").textContent = "(en " + (r.paginas ? "páginas" : item.plural) + ")";
    $$("#calc-chips .chip").forEach(b => b.setAttribute("aria-pressed", String(Number(b.dataset.n) === st.qty)));

    $("#calc-tramos-t").textContent = "Precio por " + item.unidad + " según cantidad";
    $("#calc-tramos").innerHTML = item.escalas.map((e, i) => {
      const sig = item.escalas[i + 1];
      const on = r.unidades >= e.desde && (!sig || r.unidades < sig.desde);
      return '<li class="tramo' + (on ? " on" : "") + '"' + (on ? ' aria-current="true"' : "") + '><span class="q">' + esc(rangeLabel(item, i)) +
        '</span><strong>' + money(e.precio) + "</strong></li>";
    }).join("");

    const nx = nextTier(item, r.unidades);
    if (nx) {
      const faltan = nx.desde - r.unidades;
      const extraPag = r.paginas && r.doble ? " (" + faltan * 2 + " páginas)" : "";
      $("#calc-hint").textContent = "Con " + faltan + " " + nombreUnidad(item, faltan) + " más" + extraPag + " pasás a " + money(nx.precio) + " por " + item.unidad + ".";
    } else {
      $("#calc-hint").textContent = "Ya tenés el mejor precio por cantidad.";
    }

    const filas = [["Trabajo", t.nombre]];
    r.detalle.forEach(d => filas.push([d.paso.titulo, textoOpcion(d.paso.id, d.info)]));
    if (r.paginas) {
      filas.push(["Páginas", String(st.qty)]);
      filas.push(["Hojas físicas", r.unidades + (r.doble ? " (doble faz)" : "")]);
    } else {
      filas.push(["Cantidad", st.qty + " " + nombreUnidad(item, st.qty)]);
    }
    if (st.archivo) filas.push(["Archivo", st.archivo]);
    if (t.entrega) filas.push(["Entrega", t.entrega]);
    $("#calc-detalle").innerHTML = filas.map(f => "<div><dt>" + esc(f[0]) + "</dt><dd>" + esc(f[1]) + "</dd></div>").join("");

    $("#calc-unitprice").textContent = money(r.unit) + " por " + item.unidad;
    $("#calc-total").textContent = money(r.total);
    $("#barra-total").textContent = money(r.total);

    hojaPreview(r);

    const msg = mensaje(r);
    $("#calc-msg").textContent = msg;
    const link = waLink(msg);
    $("#calc-wa").href = link;
    $("#barra-wa").href = link;
  }

  function renderCantidad() {
    const r = calcular();
    $("#calc-qty").value = st.qty;
    $("#calc-chips").innerHTML = chipsDe(st.trabajo, r).map(n =>
      '<button class="chip" type="button" data-n="' + n + '" aria-pressed="false">' + n + "</button>"
    ).join("");
  }

  function elegirTrabajo(id, preset) {
    const t = cfg.trabajos.find(x => x.id === id);
    if (!t) return false;
    st.trabajo = t;
    st.sel = {};
    PASOS.forEach(p => {
      const ops = opciones(t, p.id);
      if (!ops.length) return;
      const pedida = preset && preset.opciones ? ops.find(o => o.id === preset.opciones[p.id]) : null;
      st.sel[p.id] = (pedida || ops.find(o => o.defecto) || ops[0]).id;
    });
    st.qty = clampQty((preset && preset.cantidad) || t.cantidadInicial || 1);
    st.archivo = null;
    $("#calc-hoja").setAttribute("aria-pressed", "false");
    renderTrabajos();
    renderPasos();
    renderCantidad();
    render();
    return true;
  }

  /* ===== PDF: se cuenta en el navegador con pdf.js, nunca se sube ===== */
  let pdfjsPromesa = null;
  function cargarPdfJs() {
    if (!pdfjsPromesa) {
      pdfjsPromesa = import(PDFJS + "pdf.min.mjs").then(m => {
        m.GlobalWorkerOptions.workerSrc = PDFJS + "pdf.worker.min.mjs";
        return m;
      });
      pdfjsPromesa.catch(() => { pdfjsPromesa = null; });
    }
    return pdfjsPromesa;
  }
  async function contarPaginas(file) {
    const out = $("#calc-pdf-estado");
    if (!file) return;
    out.textContent = "Contando páginas de " + file.name + "…";
    try {
      const pdfjs = await cargarPdfJs();
      const doc = await pdfjs.getDocument({ data: new Uint8Array(await file.arrayBuffer()), isEvalSupported: false }).promise;
      const n = doc.numPages;
      doc.destroy();
      st.qty = clampQty(n);
      st.archivo = file.name;
      $("#calc-qty").value = st.qty;
      out.textContent = file.name + ": " + n + (n === 1 ? " página" : " páginas") + ". El archivo no se subió: se contó en tu navegador.";
      render();
    } catch (e) {
      out.textContent = "No pudimos leer ese PDF. Escribí la cantidad de páginas a mano.";
    }
  }

  /* ===== Pestañas: "Armá tu pedido" / "¿Necesitás algo distinto?" ===== */
  let modoActual = "calc";
  function modo(cual) {
    modoActual = cual === "distinto" ? "distinto" : "calc";
    $$("#modos .modo").forEach(b => {
      const on = b.getAttribute("aria-controls") === modoActual;
      b.setAttribute("aria-selected", String(on));
      b.tabIndex = on ? 0 : -1;
    });
    $("#calc").hidden = modoActual !== "calc";
    $("#distinto").hidden = modoActual !== "distinto";
    actualizarBarra();
  }
  function initModos() {
    const cont = $("#modos");
    if (!cont) return;
    cont.addEventListener("click", e => { const b = e.target.closest(".modo"); if (b) modo(b.getAttribute("aria-controls")); });
    // Flechas izquierda/derecha para moverse entre pestañas con el teclado.
    cont.addEventListener("keydown", e => {
      if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
      const otra = modoActual === "calc" ? "distinto" : "calc";
      modo(otra);
      $('#modos .modo[aria-controls="' + otra + '"]').focus();
      e.preventDefault();
    });
  }

  /* ===== Barra fija en celular (mientras se ve Precios y está abierta la calculadora) ===== */
  let enPrecios = false;
  function actualizarBarra() {
    const barra = $("#barra-movil");
    if (!barra) return;
    const on = enPrecios && modoActual === "calc";
    barra.classList.toggle("on", on);
    barra.setAttribute("aria-hidden", String(!on));
    $$("a", barra).forEach(a => { a.tabIndex = on ? 0 : -1; });
    document.body.classList.toggle("barra-on", on);
  }
  function barraMovil() {
    const sec = $("#precios");
    if (!sec || !("IntersectionObserver" in window)) return;
    new IntersectionObserver(entradas => {
      enPrecios = entradas[0].isIntersecting;
      actualizarBarra();
    }, { rootMargin: "-30% 0px -30% 0px" }).observe(sec);
  }

  function irACalculadora() {
    const calc = $("#calc");
    if (calc) calc.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function init() {
    if (!items().length) return;

    leerConfig();

    $("#calc-trabajos").innerHTML = cfg.trabajos.map(t =>
      '<button class="trab" type="button" data-id="' + esc(t.id) + '" aria-pressed="false">' + I.icono(t.icono) + '<span>' + esc(t.nombre) + "</span></button>"
    ).join("");
    $("#calc-trabajos").addEventListener("click", e => { const b = e.target.closest(".trab"); if (b) elegirTrabajo(b.dataset.id); });

    const atajos = cfg.atajos.filter(a => cfg.trabajos.some(t => t.id === a.trabajo));
    $("#calc-atajos-wrap").hidden = !atajos.length;
    $("#calc-atajos").innerHTML = atajos.map((a, i) =>
      '<button class="atajo" type="button" data-i="' + i + '">' + esc(a.nombre) + "</button>"
    ).join("");
    $("#calc-atajos").addEventListener("click", e => {
      const b = e.target.closest(".atajo");
      if (b) { const a = atajos[Number(b.dataset.i)]; elegirTrabajo(a.trabajo, a); }
    });

    $("#calc-pasos").addEventListener("click", e => {
      const b = e.target.closest(".opt");
      if (!b) return;
      st.sel[b.dataset.paso] = b.dataset.id;
      $$('.opt[data-paso="' + b.dataset.paso + '"]').forEach(x => x.setAttribute("aria-pressed", String(x === b)));
      render();
    });

    const qtyInput = $("#calc-qty");
    qtyInput.addEventListener("input", () => { st.qty = clampQty(qtyInput.value); render(); });
    qtyInput.addEventListener("change", () => { qtyInput.value = st.qty; });
    $("#qty-minus").addEventListener("click", () => { st.qty = Math.max(1, st.qty - 1); qtyInput.value = st.qty; render(); });
    $("#qty-plus").addEventListener("click", () => { st.qty = Math.min(MAX, st.qty + 1); qtyInput.value = st.qty; render(); });
    $("#calc-chips").addEventListener("click", e => {
      const b = e.target.closest(".chip");
      if (b) { st.qty = clampQty(b.dataset.n); qtyInput.value = st.qty; render(); }
    });

    const pdfIn = $("#calc-pdf-in");
    $("#calc-pdf-btn").addEventListener("click", () => pdfIn.click());
    pdfIn.addEventListener("change", () => { const f = pdfIn.files[0]; pdfIn.value = ""; contarPaginas(f); });

    $("#calc-hoja").addEventListener("click", e => {
      const b = e.currentTarget;
      b.setAttribute("aria-pressed", String(b.getAttribute("aria-pressed") !== "true"));
      hojaPreview(calcular());
    });

    $("#calc-copy").addEventListener("click", e => copyText(mensaje(calcular()), e.currentTarget, $("#calc-msg")));

    const preferido = cfg.trabajos[0];
    elegirTrabajo(preferido.id);
    initModos();
    barraMovil();
  }

  /* Para la galería: preselecciona un trabajo y lleva a la calculadora. */
  function pedir(id) {
    if (!cfg.trabajos.some(t => t.id === id)) return false;
    modo("calc");
    elegirTrabajo(id);
    irACalculadora();
    return true;
  }

  TH.calculadora = { init, pedir, modo, existe: id => cfg.trabajos.some(t => t.id === id) };
})();

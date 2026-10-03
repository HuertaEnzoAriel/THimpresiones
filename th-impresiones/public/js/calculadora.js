/* Calculadora: pestañas con la lista de precios por categoría (y los enlaces
   "Ver precios" de la sección de servicios) más la calculadora de pedido con
   selector de trabajo, cantidad y total estimado. Lee TH.precios recién
   dentro de init(), porque esos datos llegan de forma asíncrona (data/datos.json)
   y todavía no existen cuando este archivo se ejecuta. */
window.TH = window.TH || {};

(function () {
  const { $, $$, money, waLink, tierPrice, nextTier, rangeLabel, copyText, esc } = TH.utils;

  let catActual = null;
  let tabs, listaEl;

  function categorias() { return TH.precios.categorias; }
  function items() { return TH.precios.items; }

  function renderLista() {
    $$(".tab", tabs).forEach(b => b.setAttribute("aria-pressed", String(b.dataset.cat === catActual)));
    listaEl.innerHTML = items().filter(i => i.categoria === catActual).map(i =>
      '<li class="row"><div><h3>' + esc(i.nombre) + '</h3><p class="spec">' + esc(i.detalle) + " · precio por " + esc(i.unidad) + "</p></div>" +
      '<ul class="tiers" aria-label="Precio según cantidad">' +
      i.escalas.map((e, idx) => '<li class="tier"><span class="q">' + esc(rangeLabel(i, idx)) + "</span><strong>" + money(e.precio) + "</strong></li>").join("") +
      "</ul></li>"
    ).join("");
  }
  function setCat(id) {
    if (!categorias().some(c => c.id === id)) return;
    catActual = id;
    renderLista();
  }

  function leerQty(qtyInput) {
    const n = parseInt(qtyInput.value, 10);
    return Number.isFinite(n) && n > 0 ? Math.min(n, 9999) : 1;
  }
  function detalleCalc(selItem, qtyInput) {
    const item = TH.precios.byId(selItem.value), qty = leerQty(qtyInput);
    const total = tierPrice(item, qty) * qty;
    return "Hola, quiero consultar por " + qty + " " + (qty === 1 ? item.unidad : item.plural) +
      " de «" + item.nombre + "» (estimado " + money(total) + "). ¿Me confirman precio y plazo?";
  }

  function init() {
    const cats = categorias();
    if (!cats.length || !items().length) return;

    tabs = $("#tabs");
    listaEl = $("#lista");
    catActual = cats[0].id;
    tabs.innerHTML = cats.map(c =>
      '<button class="tab" type="button" data-cat="' + esc(c.id) + '" aria-pressed="false" style="--tab-c:' + esc(c.color) + ';--tab-c-fg:#16244F">' + esc(c.nombre) + "</button>"
    ).join("");
    renderLista();
    tabs.addEventListener("click", e => { const b = e.target.closest(".tab"); if (b) setCat(b.dataset.cat); });
    $$(".serv a[data-cat]").forEach(a => a.addEventListener("click", () => setCat(a.dataset.cat)));

    const selItem = $("#calc-item");
    const qtyInput = $("#calc-qty");
    selItem.innerHTML = cats.map(c =>
      '<optgroup label="' + esc(c.nombre) + '">' +
      items().filter(i => i.categoria === c.id).map(i => '<option value="' + esc(i.id) + '">' + esc(i.nombre) + "</option>").join("") +
      "</optgroup>"
    ).join("");
    const preferido = TH.precios.byId("a4-color");
    selItem.value = preferido ? preferido.id : items()[0].id;

    function renderCalc() {
      const item = TH.precios.byId(selItem.value);
      if (!item) return;
      const qty = leerQty(qtyInput);
      const unit = tierPrice(item, qty);
      $("#calc-unit").textContent = "(en " + item.plural + ")";
      $("#calc-unitprice").textContent = money(unit) + " por " + item.unidad;
      $("#calc-total").textContent = money(unit * qty);
      const nx = nextTier(item, qty);
      $("#calc-hint").textContent = nx
        ? "Con " + nx.desde + " " + item.plural + " o más pagás " + money(nx.precio) + " por " + item.unidad + "."
        : "Ya tenés el mejor precio por cantidad.";
      $("#calc-wa").href = waLink(detalleCalc(selItem, qtyInput));
    }

    selItem.addEventListener("change", renderCalc);
    qtyInput.addEventListener("input", renderCalc);
    qtyInput.addEventListener("change", () => { qtyInput.value = leerQty(qtyInput); renderCalc(); });
    $("#qty-minus").addEventListener("click", () => { qtyInput.value = Math.max(1, leerQty(qtyInput) - 1); renderCalc(); });
    $("#qty-plus").addEventListener("click", () => { qtyInput.value = Math.min(9999, leerQty(qtyInput) + 1); renderCalc(); });
    $("#calc-copy").addEventListener("click", e => copyText(detalleCalc(selItem, qtyInput), e.currentTarget, null));
    renderCalc();
  }

  TH.calculadora = { init, setCat };
})();

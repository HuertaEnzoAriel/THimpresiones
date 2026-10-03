/* Calculadora: pestañas con la lista de precios por categoría (y los enlaces
   "Ver precios" de la sección de servicios) más la calculadora de pedido con
   selector de trabajo, cantidad y total estimado. */
window.TH = window.TH || {};

(function () {
  const { $, $$, money, waLink, tierPrice, nextTier, rangeLabel, copyText } = TH.utils;
  const CATEGORIAS = TH.precios.categorias;
  const ITEMS = TH.precios.items;
  const byId = TH.precios.byId;

  let catActual = CATEGORIAS[0].id;
  let tabs, listaEl;

  function renderLista() {
    $$(".tab", tabs).forEach(b => b.setAttribute("aria-pressed", String(b.dataset.cat === catActual)));
    listaEl.innerHTML = ITEMS.filter(i => i.cat === catActual).map(i =>
      '<li class="row"><div><h3>' + i.nombre + '</h3><p class="spec">' + i.spec + " · precio por " + i.unidad + "</p></div>" +
      '<ul class="tiers" aria-label="Precio según cantidad">' +
      i.tiers.map(([f, p], idx) => '<li class="tier"><span class="q">' + rangeLabel(i, idx) + "</span><strong>" + money(p) + "</strong></li>").join("") +
      "</ul></li>"
    ).join("");
  }
  function setCat(id) {
    catActual = id;
    renderLista();
  }

  function leerQty(qtyInput) {
    const n = parseInt(qtyInput.value, 10);
    return Number.isFinite(n) && n > 0 ? Math.min(n, 9999) : 1;
  }
  function detalleCalc(selItem, qtyInput) {
    const item = byId(selItem.value), qty = leerQty(qtyInput);
    const total = tierPrice(item, qty) * qty;
    return "Hola, quiero consultar por " + qty + " " + (qty === 1 ? item.unidad : item.plural) +
      " de «" + item.nombre + "» (estimado " + money(total) + "). ¿Me confirman precio y plazo?";
  }

  function init() {
    tabs = $("#tabs");
    listaEl = $("#lista");
    tabs.innerHTML = CATEGORIAS.map(c =>
      '<button class="tab" type="button" data-cat="' + c.id + '" aria-pressed="false" style="--tab-c:' + c.color + ';--tab-c-fg:#16244F">' + c.nombre + "</button>"
    ).join("");
    renderLista();
    tabs.addEventListener("click", e => { const b = e.target.closest(".tab"); if (b) setCat(b.dataset.cat); });
    $$(".serv a[data-cat]").forEach(a => a.addEventListener("click", () => setCat(a.dataset.cat)));

    const selItem = $("#calc-item");
    const qtyInput = $("#calc-qty");
    selItem.innerHTML = CATEGORIAS.map(c =>
      '<optgroup label="' + c.nombre + '">' +
      ITEMS.filter(i => i.cat === c.id).map(i => '<option value="' + i.id + '">' + i.nombre + "</option>").join("") +
      "</optgroup>"
    ).join("");
    selItem.value = "a4-color";

    function renderCalc() {
      const item = byId(selItem.value), qty = leerQty(qtyInput);
      const unit = tierPrice(item, qty);
      $("#calc-unit").textContent = "(en " + item.plural + ")";
      $("#calc-unitprice").textContent = money(unit) + " por " + item.unidad;
      $("#calc-total").textContent = money(unit * qty);
      const nx = nextTier(item, qty);
      $("#calc-hint").textContent = nx
        ? "Con " + nx[0] + " " + item.plural + " o más pagás " + money(nx[1]) + " por " + item.unidad + "."
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

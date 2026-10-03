/* Utils: helpers genéricos usados por los demás módulos — selección de elementos,
   formato de dinero, enlace de WhatsApp, cálculo de precio por escala de cantidad,
   copiar texto al portapapeles (con reserva de selección si falla) y escapado de
   texto para cuando se inserta con innerHTML (los datos vienen del panel, nunca
   hay que insertarlos como HTML sin escapar). */
window.TH = window.TH || {};

(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  const money = n => new Intl.NumberFormat("es-AR", { style: "currency", currency: "ARS", maximumFractionDigits: 0 }).format(n);
  const waLink = text => "https://wa.me/" + TH.config.whatsapp + "?text=" + encodeURIComponent(text);

  const ENTIDADES = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, c => ENTIDADES[c]);
  }

  function tierPrice(item, qty) {
    let p = item.escalas[0].precio;
    item.escalas.forEach(e => { if (qty >= e.desde) p = e.precio; });
    return p;
  }
  function nextTier(item, qty) {
    return item.escalas.find(e => e.desde > qty) || null;
  }
  function rangeLabel(item, idx) {
    const from = item.escalas[idx].desde;
    const next = item.escalas[idx + 1];
    if (!next) return from + " o más";
    const to = next.desde - 1;
    return from === to ? String(from) : from + " a " + to;
  }

  function flash(btn, msg) {
    if (!btn.dataset.label) btn.dataset.label = btn.textContent;
    btn.textContent = msg;
    clearTimeout(btn._t);
    btn._t = setTimeout(() => { btn.textContent = btn.dataset.label; }, 1800);
  }
  async function copyText(text, btn, sourceEl) {
    try {
      await navigator.clipboard.writeText(text);
      flash(btn, "Copiado");
    } catch (e) {
      if (sourceEl) {
        const r = document.createRange();
        r.selectNodeContents(sourceEl);
        const s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
        flash(btn, "Seleccionado: Ctrl+C");
      } else {
        flash(btn, "No se pudo copiar");
      }
    }
  }

  TH.utils = { $, $$, money, waLink, esc, tierPrice, nextTier, rangeLabel, flash, copyText };
})();

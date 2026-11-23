/* Íconos y dibujos: SVG chicos que usa la calculadora (tarjetas de trabajo,
   tamaños a escala, simple/doble faz, franja de color y grosor del papel).
   No depende de nada más, así el panel de administración lo puede incluir
   para mostrar las mismas vistas previas. */
window.TH = window.TH || {};

(function () {
  const TRAZO = 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';

  const ICONOS = {
    hoja: { nombre: "Hoja", svg: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 15h6M9 18h4"/>' },
    foto: { nombre: "Foto", svg: '<rect x="3" y="5" width="18" height="14" rx="1.5"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 8"/>' },
    tarjeta: { nombre: "Tarjeta", svg: '<rect x="2.5" y="6" width="19" height="12" rx="1.5"/><path d="M6 10h6M6 13.5h9"/>' },
    flyer: { nombre: "Flyer", svg: '<path d="M7 3h10v18H7z"/><path d="M9.5 6.5h5v4h-5z"/><path d="M9.5 14h5M9.5 17h3"/>' },
    triptico: { nombre: "Tríptico", svg: '<path d="M3 5l6 1.5v13L3 18z"/><path d="M9 6.5l6-1.5v13l-6 1.5z"/><path d="M15 5l6 1.5v13L15 18z"/>' },
    sticker: { nombre: "Sticker", svg: '<path d="M5 4h14v9l-6 7H5z"/><path d="M13 20v-5a2 2 0 0 1 2-2h4"/>' },
    cd: { nombre: "CD / DVD", svg: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/>' },
    credencial: { nombre: "Credencial", svg: '<rect x="5" y="2.5" width="14" height="19" rx="2"/><circle cx="12" cy="10" r="2.5"/><path d="M8.5 16.5a3.5 3.5 0 0 1 7 0M10 5.5h4"/>' },
    anillado: { nombre: "Anillado", svg: '<rect x="7" y="4" width="13" height="17" rx="1"/><path d="M4 7.5h5M4 11h5M4 14.5h5M4 18h5"/>' },
    otro: { nombre: "Otro", svg: '<path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4l-5.3 3 1.2-6-4.5-4.1 6-.7z"/>' }
  };

  function icono(id) {
    const ic = ICONOS[id] || ICONOS.otro;
    return '<svg class="ico" viewBox="0 0 24 24" ' + TRAZO + ' aria-hidden="true">' + ic.svg + "</svg>";
  }

  // "29.7" -> "29,7"
  const cm = n => String(Math.round(Number(n) * 10) / 10).replace(".", ",");

  /* Rectángulo proporcional a la medida real. maxCm es la medida más grande
     de todo el catálogo: así todos los tamaños quedan a la misma escala. */
  function svgTamano(ancho, alto, maxCm) {
    const caja = 44, escala = caja / Math.max(maxCm || 1, ancho, alto, 1);
    const w = Math.max(3, ancho * escala), h = Math.max(3, alto * escala);
    const x = (48 - w) / 2, y = 46 - h;
    return '<svg class="tam-svg" viewBox="0 0 48 48" aria-hidden="true"><rect x="' + x.toFixed(1) + '" y="' + y.toFixed(1) +
      '" width="' + w.toFixed(1) + '" height="' + h.toFixed(1) + '" rx="1" fill="var(--paper)" stroke="currentColor" stroke-width="1.5"/></svg>';
  }

  /* Simple faz: frente impreso + dorso en blanco. Doble faz: las dos caras impresas. */
  function svgFaz(doble) {
    const hoja = (x, impresa) =>
      '<rect x="' + x + '" y="3" width="24" height="34" rx="1.5" fill="var(--paper)" stroke="currentColor" stroke-width="1.5"' + (impresa ? "" : ' stroke-dasharray="3 2.5"') + "/>" +
      (impresa ? '<rect x="' + (x + 4) + '" y="8" width="16" height="8" fill="var(--m)"/><path d="M' + (x + 4) + " 21h16M" + (x + 4) + " 25.5h16M" + (x + 4) + ' 30h10" stroke="currentColor" stroke-width="1.5"/>' : "");
    return '<svg class="faz-svg" viewBox="0 0 60 40" aria-hidden="true">' + hoja(3, true) + hoja(33, doble) + "</svg>";
  }

  function franja(color) {
    const tonos = color ? ["var(--c)", "var(--m)", "var(--y)", "#16244F"] : ["#1E1E1E", "#5C5C5C", "#9A9A9A", "#D2D2D2"];
    return '<span class="franja" aria-hidden="true">' + tonos.map(t => '<i style="background:' + t + '"></i>').join("") + "</span>";
  }

  /* Indicador de grosor: la hoja vista de canto, más gruesa cuanto más gramaje. */
  function grosor(gramaje) {
    const px = Math.max(1.5, Math.min(10, Number(gramaje) / 32));
    return '<span class="grosor" aria-hidden="true"><i style="height:' + px.toFixed(1) + 'px"></i></span>';
  }

  /* Adivina el ícono por el nombre del trabajo (ej: "Foto 21 × 15" -> foto). */
  const PISTAS = [
    ["foto", /foto/], ["tarjeta", /tarjeta/], ["flyer", /flyer|volante/], ["triptico", /tr[ií]ptico|d[ií]ptico/],
    ["sticker", /sticker|adhesiv|etiqueta/], ["cd", /\bcd\b|dvd/], ["credencial", /credencial|pvc/], ["anillado", /anillad|encuadern/],
    ["hoja", /\ba4\b|\ba3\b|\ba5\b|oficio|impresi|hoja|blanco y negro|fotocopia/]
  ];
  function adivinar(texto) {
    const t = String(texto || "").toLowerCase();
    const p = PISTAS.find(x => x[1].test(t));
    return p ? p[0] : "otro";
  }

  TH.iconos = { ICONOS, icono, cm, svgTamano, svgFaz, franja, grosor, adivinar };
})();

<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Calculadora';
$paginaActiva = 'calculadora';
require __DIR__ . '/_layout_top.php';
?>
<h1>Calculadora</h1>
<p class="ayuda-pagina">Lo que el cliente elige en "Calculá tu pedido". Los precios por cantidad salen de la pantalla <a href="precios.php">Precios</a>: acá elegís cuál usa cada trabajo y cuánto suma cada opción.</p>

<div class="ptab-cats" id="sub-tabs" role="group" aria-label="Partes de la calculadora"></div>
<p class="ayuda-pagina" id="ayuda-vista"></p>
<div class="ptabla" id="vista"></div>

<div class="subir-row" style="margin-top:14px">
  <button class="btn btn-primary" type="button" id="btn-agregar"></button>
</div>

<div class="panel-guardar">
  <span class="estado" id="estado-guardado"></span>
  <button class="btn btn-primary" type="button" id="btn-guardar">Guardar cambios</button>
</div>

<script src="../js/iconos.js?v=<?= filemtime(__DIR__ . '/../js/iconos.js') ?>"></script>
<script>
(function () {
  const I = TH.iconos;
  const PASOS = [
    { id: 'tamano', titulo: 'Tamaño' },
    { id: 'faz', titulo: 'Faz' },
    { id: 'color', titulo: 'Color' },
    { id: 'papel', titulo: 'Papel' }
  ];
  const FIJAS = {
    faz: [{ id: 'simple', nombre: 'Simple faz', detalle: 'Solo el frente' }, { id: 'doble', nombre: 'Doble faz', detalle: 'Frente y dorso' }],
    color: [{ id: 'bn', nombre: 'Blanco y negro', detalle: 'La vista previa se ve en gris' }, { id: 'color', nombre: 'Color', detalle: 'A todo color' }]
  };
  const VISTAS = {
    trabajos: { nombre: 'Trabajos', agregar: 'Agregar trabajo', ayuda: 'Cada tarjeta con ícono que ve el cliente. Marcá qué opciones tiene en cada paso: con 2 o más opciones el paso se muestra; con una sola no se muestra, pero se usa en el precio y aparece en el detalle; sin ninguna, el paso no aplica.' },
    tamanos: { nombre: 'Tamaños', agregar: 'Agregar tamaño', ayuda: 'Medidas en centímetros. El dibujo se hace a escala, igual que en la página.' },
    papeles: { nombre: 'Papeles', agregar: 'Agregar papel', ayuda: 'El gramaje define el indicador de grosor. La comparación es lo que lee el cliente para entender cómo es el papel.' },
    atajos: { nombre: 'Atajos', agregar: 'Agregar atajo', ayuda: 'Botones que dejan la calculadora cargada de un toque (ej: "Apuntes de la facu"). Lo que no elijas queda como viene por defecto.' }
  };

  const vistaEl = document.getElementById('vista');
  const tabsEl = document.getElementById('sub-tabs');
  const btnAgregar = document.getElementById('btn-agregar');
  let datosCompletos = null;
  let C = null;
  let vista = 'trabajos';
  const abiertos = new Set();

  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const nuevoId = p => p + '-' + Math.random().toString(36).slice(2, 8);
  const cambio = () => controlador.marcarCambios();
  const precioPorId = id => datosCompletos.precios.find(p => p.id === id);

  function opcionesPrecios(actual, textoVacio) {
    let html = textoVacio !== undefined ? '<option value="">' + esc(textoVacio) + '</option>' : '';
    datosCompletos.categoriasPrecios.forEach(c => {
      const items = datosCompletos.precios.filter(p => p.categoria === c.id && p.id);
      if (!items.length) return;
      html += '<optgroup label="' + esc(c.nombre) + '">' + items.map(p =>
        '<option value="' + esc(p.id) + '"' + (p.id === actual ? ' selected' : '') + '>' + esc(p.nombre) + '</option>').join('') + '</optgroup>';
    });
    return html;
  }

  function catalogo(paso) {
    if (paso === 'tamano') return C.tamanos.map(t => ({ id: t.id, nombre: t.nombre || '(sin nombre)', detalle: I.cm(t.ancho) + ' × ' + I.cm(t.alto) + ' cm' }));
    if (paso === 'papel') return C.papeles.map(p => ({ id: p.id, nombre: (p.nombre || '(sin nombre)') + ' ' + p.gramaje + ' g', detalle: p.comparacion }));
    return FIJAS[paso];
  }

  function maxCm() { return C.tamanos.reduce((m, t) => Math.max(m, Number(t.ancho) || 0, Number(t.alto) || 0), 1); }

  /* Filas ↑ ↓ Eliminar sobre un array; eliminar ofrece deshacer. */
  function botonesOrden(arr, i, nombre, alEliminar) {
    const cont = document.createElement('div');
    cont.className = 'orden-btns';
    cont.innerHTML =
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="subir" aria-label="Subir">↑</button>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="bajar" aria-label="Bajar">↓</button>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="eliminar">Eliminar</button>';
    const mover = d => {
      const j = i + d;
      if (j < 0 || j >= arr.length) return;
      [arr[i], arr[j]] = [arr[j], arr[i]];
      cambio();
      render();
    };
    cont.querySelector('[data-accion="subir"]').addEventListener('click', () => mover(-1));
    cont.querySelector('[data-accion="bajar"]').addEventListener('click', () => mover(1));
    cont.querySelector('[data-accion="eliminar"]').addEventListener('click', e => {
      const aviso = alEliminar ? alEliminar.aviso() : '';
      Panel.confirmarEnLinea(e.currentTarget, '¿Eliminar "' + nombre() + '"?' + aviso, () => {
        const quitado = arr.splice(i, 1)[0];
        const extra = alEliminar ? alEliminar.hacer() : null;
        cambio();
        render();
        Panel.mostrarDeshacer('Se eliminó "' + nombre.call(null, quitado) + '".', () => {
          arr.splice(i, 0, quitado);
          if (extra) extra();
          cambio();
          render();
        });
      });
    });
    return cont;
  }

  function campo(etiqueta, control, ayuda) {
    const f = document.createElement('div');
    f.className = 'field';
    const l = document.createElement('label');
    l.textContent = etiqueta;
    const id = nuevoId('c');
    control.id = id;
    l.htmlFor = id;
    f.append(l, control);
    if (ayuda) { const p = document.createElement('p'); p.className = 'ayuda'; p.textContent = ayuda; f.appendChild(p); }
    return f;
  }
  function input(valor, alCambiar, tipo) {
    const i = document.createElement('input');
    i.type = tipo || 'text';
    if (tipo === 'number') i.inputMode = 'decimal';
    i.value = valor == null ? '' : valor;
    i.addEventListener('input', () => { alCambiar(i.value, i); cambio(); });
    return i;
  }

  /* ===== Trabajos ===== */
  function ejemplo(t) {
    let precioId = t.precioId, factor = 1, doble = false;
    PASOS.forEach(p => {
      const ops = t.pasos[p.id] || [];
      const o = ops.find(x => x.defecto) || ops[0];
      if (!o) return;
      if (o.precioId && precioPorId(o.precioId)) precioId = o.precioId;
      factor *= 1 + (Number(o.recargo) || 0) / 100;
      if (p.id === 'faz' && o.id === 'doble') doble = true;
    });
    const item = precioPorId(precioId);
    if (!item || !item.escalas || !item.escalas.length) return null;
    const q = Number(t.cantidadInicial) || 1;
    const unidades = t.cuentaPaginas && doble ? Math.ceil(q / 2) : q;
    let p = item.escalas[0].precio;
    item.escalas.forEach(e => { if (unidades >= e.desde) p = e.precio; });
    const unit = Math.round(p * factor);
    return { q, unidades, unit, total: unit * unidades, item };
  }

  function resumenTrabajo(t) {
    const vis = PASOS.filter(p => (t.pasos[p.id] || []).length > 1).map(p => p.titulo);
    const ej = ejemplo(t);
    let txt = 'Pasos que ve el cliente: ' + (vis.length ? vis.join(', ') : 'ninguno (solo cantidad)') + '. ';
    if (ej) {
      const cant = t.cuentaPaginas
        ? ej.q + ' páginas (' + ej.unidades + ' ' + (ej.unidades === 1 ? ej.item.unidad : ej.item.plural) + ')'
        : ej.q + ' ' + (ej.q === 1 ? ej.item.unidad : ej.item.plural);
      txt += 'Al entrar, con lo marcado por defecto: ' + cant + ' → ' + Panel.money(ej.unit) + ' por ' + ej.item.unidad + ', total ' + Panel.money(ej.total) + '.';
    } else {
      txt += 'Elegí un precio de la lista para ver el ejemplo.';
    }
    return txt;
  }

  function bloquePaso(t, paso, alCambiar) {
    const cat = catalogo(paso.id);
    const ops = t.pasos[paso.id] = t.pasos[paso.id] || [];
    const det = document.createElement('details');
    det.className = 'cpaso';
    const sum = document.createElement('summary');
    const pintarSum = () => {
      const n = t.pasos[paso.id].length;
      sum.innerHTML = '<strong>' + paso.titulo + '</strong> <span>' +
        (n === 0 ? 'no aplica' : n === 1 ? '1 opción: no se muestra, va en el detalle' : n + ' opciones: el cliente elige') + '</span>';
    };
    pintarSum();
    det.appendChild(sum);

    if (!cat.length) {
      const p = document.createElement('p');
      p.className = 'ayuda';
      p.textContent = 'Todavía no cargaste ' + (paso.id === 'tamano' ? 'tamaños' : 'papeles') + '. Agregalos en la pestaña ' + (paso.id === 'tamano' ? 'Tamaños' : 'Papeles') + '.';
      det.appendChild(p);
      return det;
    }

    const tabla = document.createElement('div');
    tabla.className = 'copc-lista';
    const nombreRadio = nuevoId('def');
    cat.forEach(c => {
      const o = ops.find(x => x.id === c.id);
      const fila = document.createElement('div');
      fila.className = 'copc' + (o ? '' : ' apagada');
      fila.innerHTML =
        '<label class="copc-usa"><input type="checkbox" data-r="usa"' + (o ? ' checked' : '') + '><span><strong>' + esc(c.nombre) + '</strong><small>' + esc(c.detalle || '') + '</small></span></label>' +
        '<label class="copc-def"><input type="radio" name="' + nombreRadio + '" data-r="def"' + (o && o.defecto ? ' checked' : '') + '> Por defecto</label>' +
        '<div class="field"><label>Suma (%)</label><input type="number" step="1" min="-90" max="500" data-r="recargo" value="' + (o ? Number(o.recargo) || 0 : 0) + '"></div>' +
        '<div class="field"><label>Usa el precio de</label><select data-r="precio">' + opcionesPrecios(o ? o.precioId : '', 'El del trabajo') + '</select></div>';
      fila.dataset.id = c.id;
      tabla.appendChild(fila);
    });

    // Se relee todo el paso desde la tabla, en el orden del catálogo.
    function leer() {
      const nuevas = [];
      tabla.querySelectorAll('.copc').forEach(f => {
        const usa = f.querySelector('[data-r="usa"]').checked;
        f.classList.toggle('apagada', !usa);
        f.querySelectorAll('[data-r="def"],[data-r="recargo"],[data-r="precio"]').forEach(x => { x.disabled = !usa; });
        if (!usa) return;
        const op = { id: f.dataset.id, recargo: parseInt(f.querySelector('[data-r="recargo"]').value, 10) || 0, precioId: f.querySelector('[data-r="precio"]').value };
        if (f.querySelector('[data-r="def"]').checked) op.defecto = true;
        nuevas.push(op);
      });
      t.pasos[paso.id] = nuevas;
      pintarSum();
      alCambiar();
    }
    tabla.addEventListener('input', () => { leer(); cambio(); });
    tabla.addEventListener('change', () => { leer(); cambio(); });
    tabla.querySelectorAll('.copc').forEach(f => {
      const usa = f.querySelector('[data-r="usa"]').checked;
      f.querySelectorAll('[data-r="def"],[data-r="recargo"],[data-r="precio"]').forEach(x => { x.disabled = !usa; });
    });
    const ayuda = document.createElement('p');
    ayuda.className = 'ayuda';
    ayuda.textContent = 'Suma (%): cuánto aumenta el precio con esa opción (0 = igual; puede ser negativo). "Usa el precio de" cambia la lista de precios por cantidad que se usa, por ejemplo Color → "A4 color".';
    det.append(tabla, ayuda);
    return det;
  }

  function crearTrabajo(t, i) {
    t.pasos = t.pasos || {};
    const nodo = document.createElement('div');
    nodo.className = 'pitem';
    const cab = document.createElement('div');
    cab.className = 'pitem-cab';
    const titulo = document.createElement('div');
    titulo.className = 'cab-ico';
    cab.appendChild(titulo);
    const pintarCab = () => { titulo.innerHTML = '<span class="prev-ico">' + I.icono(t.icono) + '</span><strong>' + esc(t.nombre || '(trabajo nuevo)') + '</strong>'; };
    pintarCab();
    cab.appendChild(botonesOrden(C.trabajos, i, q => (q || t).nombre || 'este trabajo', {
      aviso: () => {
        const at = C.atajos.filter(a => a.trabajo === t.id).map(a => a.nombre);
        return at.length ? ' También se borran los atajos que lo usan: ' + at.join(', ') + '.' : '';
      },
      hacer: () => {
        const quitados = C.atajos.map((a, k) => [a, k]).filter(([a]) => a.trabajo === t.id);
        C.atajos = C.atajos.filter(a => a.trabajo !== t.id);
        return () => { quitados.forEach(([a, k]) => C.atajos.splice(k, 0, a)); };
      }
    }));

    const prev = document.createElement('p');
    prev.className = 'previsualizacion';
    const pintarPrev = () => { prev.textContent = resumenTrabajo(t); };

    const campos = document.createElement('div');
    campos.className = 'pitem-campos';
    campos.appendChild(campo('Nombre en la tarjeta', input(t.nombre, v => { t.nombre = v.trim(); pintarCab(); })));

    const selIco = document.createElement('select');
    selIco.innerHTML = Object.keys(I.ICONOS).map(k => '<option value="' + k + '"' + (k === t.icono ? ' selected' : '') + '>' + esc(I.ICONOS[k].nombre) + '</option>').join('');
    selIco.addEventListener('change', () => { t.icono = selIco.value; pintarCab(); cambio(); });
    campos.appendChild(campo('Ícono', selIco));

    const selPrecio = document.createElement('select');
    selPrecio.innerHTML = opcionesPrecios(t.precioId, t.precioId ? undefined : 'Elegí un precio…');
    selPrecio.addEventListener('change', () => { t.precioId = selPrecio.value; pintarPrev(); cambio(); });
    campos.appendChild(campo('Precio por cantidad (de la lista)', selPrecio, 'De acá salen los tramos de descuento y la unidad (hoja, foto, pack…).'));

    campos.appendChild(campo('Tiempo de entrega', input(t.entrega, v => { t.entrega = v.trim(); }), 'Ej: En el día · 24 a 48 h'));
    campos.appendChild(campo('Cantidades rápidas', input((t.cantidades || []).join(', '), v => {
      t.cantidades = v.split(/[^0-9]+/).map(Number).filter(n => n > 0);
    }), 'Los botones de cantidad. Separadas por coma, ej: 10, 50, 100. Vacío = usa los tramos de precio.'));
    campos.appendChild(campo('Cantidad al entrar', input(t.cantidadInicial, v => { t.cantidadInicial = parseInt(v, 10) || 1; pintarPrev(); }, 'number')));

    const chk = document.createElement('label');
    chk.className = 'check-linea';
    chk.innerHTML = '<input type="checkbox"> <span>Se cuenta por páginas: deja elegir un PDF para contarlas y, en doble faz, cobra la mitad de hojas.</span>';
    const chkIn = chk.querySelector('input');
    chkIn.checked = !!t.cuentaPaginas;
    chkIn.addEventListener('change', () => { t.cuentaPaginas = chkIn.checked; pintarPrev(); cambio(); });

    const pasos = document.createElement('div');
    pasos.className = 'cpasos';
    PASOS.forEach(p => pasos.appendChild(bloquePaso(t, p, pintarPrev)));

    pintarPrev();
    // Cada trabajo se abre y cierra para que la pantalla no quede eterna.
    const cuerpo = document.createElement('div');
    cuerpo.className = 'ctrab-cuerpo';
    cuerpo.append(campos, chk, pasos);
    const abierto = abiertos.has(t.id);
    cuerpo.hidden = !abierto;
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-ghost btn-sm';
    toggle.setAttribute('aria-expanded', String(abierto));
    toggle.textContent = abierto ? 'Cerrar' : 'Editar';
    toggle.addEventListener('click', () => {
      const ahora = cuerpo.hidden;
      cuerpo.hidden = !ahora;
      if (ahora) abiertos.add(t.id); else abiertos.delete(t.id);
      toggle.textContent = ahora ? 'Cerrar' : 'Editar';
      toggle.setAttribute('aria-expanded', String(ahora));
    });
    cab.querySelector('.orden-btns').prepend(toggle);
    nodo.append(cab, cuerpo, prev);
    return nodo;
  }

  /* ===== Tamaños ===== */
  function usosDe(paso, id) {
    return C.trabajos.filter(t => ((t.pasos || {})[paso] || []).some(o => o.id === id)).map(t => t.nombre);
  }
  function quitarDeTrabajos(paso, id) {
    const antes = C.trabajos.map(t => (t.pasos[paso] || []).slice());
    C.trabajos.forEach(t => { if (t.pasos && t.pasos[paso]) t.pasos[paso] = t.pasos[paso].filter(o => o.id !== id); });
    return () => { C.trabajos.forEach((t, k) => { t.pasos[paso] = antes[k]; }); };
  }

  function crearTamano(t, i) {
    const nodo = document.createElement('div');
    nodo.className = 'pitem pitem-con-prev';
    const prev = document.createElement('div');
    prev.className = 'tam-prev';
    const pintar = () => {
      prev.innerHTML = I.svgTamano(Number(t.ancho) || 1, Number(t.alto) || 1, maxCm()) +
        '<span><strong>' + esc(t.nombre || '—') + '</strong><small>' + I.cm(t.ancho || 0) + ' × ' + I.cm(t.alto || 0) + ' cm</small><small>' + esc(t.referencia || '') + '</small></span>';
    };
    pintar();
    const campos = document.createElement('div');
    campos.className = 'pitem-campos';
    campos.appendChild(campo('Nombre', input(t.nombre, v => { t.nombre = v.trim(); pintar(); }), 'Ej: A4 · 10 × 15'));
    campos.appendChild(campo('Referencia simple', input(t.referencia, v => { t.referencia = v.trim(); pintar(); }), 'Ej: Una hoja de carpeta'));
    campos.appendChild(campo('Ancho (cm)', input(t.ancho, v => { t.ancho = parseFloat(String(v).replace(',', '.')) || 0; pintar(); }, 'number')));
    campos.appendChild(campo('Alto (cm)', input(t.alto, v => { t.alto = parseFloat(String(v).replace(',', '.')) || 0; pintar(); }, 'number')));
    const usos = usosDe('tamano', t.id);
    const info = document.createElement('p');
    info.className = 'ayuda';
    info.textContent = usos.length ? 'Lo usan: ' + usos.join(', ') + '.' : 'Ningún trabajo lo usa todavía (activalo en la pestaña Trabajos).';
    const cab = document.createElement('div');
    cab.className = 'pitem-cab';
    cab.append(prev, botonesOrden(C.tamanos, i, q => (q || t).nombre || 'este tamaño', {
      aviso: () => usos.length ? ' Se va a quitar de: ' + usos.join(', ') + '.' : '',
      hacer: () => quitarDeTrabajos('tamano', t.id)
    }));
    nodo.append(cab, campos, info);
    return nodo;
  }

  /* ===== Papeles ===== */
  function crearPapel(p, i) {
    const nodo = document.createElement('div');
    nodo.className = 'pitem';
    const prev = document.createElement('div');
    prev.className = 'papel-prev';
    const pintar = () => {
      prev.innerHTML = '<strong>' + esc((p.nombre || '—') + ' ' + (p.gramaje || 0) + ' g') + '</strong>' + I.grosor(p.gramaje || 0) + '<small>' + esc(p.comparacion || '') + '</small>';
    };
    pintar();
    const campos = document.createElement('div');
    campos.className = 'pitem-campos';
    campos.appendChild(campo('Nombre', input(p.nombre, v => { p.nombre = v.trim(); pintar(); }), 'Ej: Obra · Ilustración mate'));
    campos.appendChild(campo('Gramaje (g)', input(p.gramaje, v => { p.gramaje = parseInt(v, 10) || 0; pintar(); }, 'number')));
    const comp = campo('Comparación en palabras simples', input(p.comparacion, v => { p.comparacion = v.trim(); pintar(); }), 'Ej: Como una hoja de impresora común');
    comp.classList.add('ancho-completo');
    campos.appendChild(comp);
    const usos = usosDe('papel', p.id);
    const info = document.createElement('p');
    info.className = 'ayuda';
    info.textContent = usos.length ? 'Lo usan: ' + usos.join(', ') + '.' : 'Ningún trabajo lo usa todavía (activalo en la pestaña Trabajos).';
    const cab = document.createElement('div');
    cab.className = 'pitem-cab';
    cab.append(prev, botonesOrden(C.papeles, i, q => (q || p).nombre || 'este papel', {
      aviso: () => usos.length ? ' Se va a quitar de: ' + usos.join(', ') + '.' : '',
      hacer: () => quitarDeTrabajos('papel', p.id)
    }));
    nodo.append(cab, campos, info);
    return nodo;
  }

  /* ===== Atajos ===== */
  function crearAtajo(a, i) {
    a.opciones = a.opciones && typeof a.opciones === 'object' && !Array.isArray(a.opciones) ? a.opciones : {};
    const nodo = document.createElement('div');
    nodo.className = 'pitem';
    const cab = document.createElement('div');
    cab.className = 'pitem-cab';
    const tit = document.createElement('strong');
    tit.className = 'atajo-prev';
    const pintarTit = () => { tit.textContent = a.nombre || '(atajo nuevo)'; };
    pintarTit();
    cab.append(tit, botonesOrden(C.atajos, i, q => (q || a).nombre || 'este atajo'));

    const campos = document.createElement('div');
    campos.className = 'pitem-campos';
    campos.appendChild(campo('Texto del botón', input(a.nombre, v => { a.nombre = v.trim(); pintarTit(); }), 'Ej: Apuntes de la facu'));
    const selT = document.createElement('select');
    selT.innerHTML = C.trabajos.map(t => '<option value="' + esc(t.id) + '"' + (t.id === a.trabajo ? ' selected' : '') + '>' + esc(t.nombre) + '</option>').join('');
    if (!C.trabajos.some(t => t.id === a.trabajo) && C.trabajos[0]) a.trabajo = C.trabajos[0].id;
    selT.addEventListener('change', () => { a.trabajo = selT.value; a.opciones = {}; cambio(); render(); });
    campos.appendChild(campo('Trabajo', selT));
    campos.appendChild(campo('Cantidad', input(a.cantidad, v => { a.cantidad = parseInt(v, 10) || 1; }, 'number')));

    const t = C.trabajos.find(x => x.id === a.trabajo);
    if (t) {
      PASOS.forEach(p => {
        const ops = (t.pasos[p.id] || []);
        if (ops.length < 2) return;
        const cat = catalogo(p.id);
        const sel = document.createElement('select');
        sel.innerHTML = '<option value="">Lo que viene por defecto</option>' + ops.map(o => {
          const c = cat.find(x => x.id === o.id);
          return '<option value="' + esc(o.id) + '"' + (a.opciones[p.id] === o.id ? ' selected' : '') + '>' + esc(c ? c.nombre : o.id) + '</option>';
        }).join('');
        sel.addEventListener('change', () => {
          if (sel.value) a.opciones[p.id] = sel.value; else delete a.opciones[p.id];
          cambio();
        });
        campos.appendChild(campo(p.titulo, sel));
      });
    }
    nodo.append(cab, campos);
    return nodo;
  }

  /* ===== Pintado general ===== */
  function render() {
    tabsEl.innerHTML = '';
    Object.keys(VISTAS).forEach(k => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'tab';
      b.setAttribute('aria-pressed', String(k === vista));
      b.textContent = VISTAS[k].nombre;
      b.addEventListener('click', () => { vista = k; render(); });
      tabsEl.appendChild(b);
    });
    document.getElementById('ayuda-vista').textContent = VISTAS[vista].ayuda;
    btnAgregar.textContent = VISTAS[vista].agregar;
    vistaEl.innerHTML = '';
    const lista = C[vista];
    const crear = { trabajos: crearTrabajo, tamanos: crearTamano, papeles: crearPapel, atajos: crearAtajo }[vista];
    if (!lista.length) vistaEl.innerHTML = '<p class="vacio">Todavía no hay nada acá.</p>';
    lista.forEach((x, i) => vistaEl.appendChild(crear(x, i)));
  }

  btnAgregar.addEventListener('click', () => {
    if (vista === 'trabajos') {
      const idNuevo = nuevoId('t');
      abiertos.add(idNuevo);
      C.trabajos.push({ id: idNuevo, nombre: '', icono: 'otro', precioId: '', entrega: '', cuentaPaginas: false, cantidades: [], cantidadInicial: 1, pasos: { tamano: [], faz: [], color: [], papel: [] } });
    } else if (vista === 'tamanos') {
      C.tamanos.push({ id: nuevoId('tam'), nombre: '', ancho: 10, alto: 15, referencia: '' });
    } else if (vista === 'papeles') {
      C.papeles.push({ id: nuevoId('pap'), nombre: '', gramaje: 90, comparacion: '' });
    } else {
      if (!C.trabajos.length) { Panel.avisar('Primero agregá un trabajo.'); return; }
      C.atajos.push({ id: nuevoId('at'), nombre: '', trabajo: C.trabajos[0].id, cantidad: 1, opciones: {} });
    }
    cambio();
    render();
    const ult = vistaEl.lastElementChild;
    if (ult) { ult.scrollIntoView({ block: 'center' }); const i = ult.querySelector('input'); if (i) i.focus(); }
  });

  const controlador = Panel.crearControladorGuardado({
    boton: document.getElementById('btn-guardar'),
    estado: document.getElementById('estado-guardado'),
    obtenerDatos: () => datosCompletos,
    alGuardarOk: datos => { datosCompletos = datos; preparar(); render(); }
  });

  function preparar() {
    const c = datosCompletos.calculadora = datosCompletos.calculadora || {};
    ['tamanos', 'papeles', 'trabajos', 'atajos'].forEach(k => { if (!Array.isArray(c[k])) c[k] = []; });
    c.trabajos.forEach(t => { t.pasos = t.pasos || {}; PASOS.forEach(p => { if (!Array.isArray(t.pasos[p.id])) t.pasos[p.id] = []; }); });
    C = c;
  }

  (async () => {
    try {
      datosCompletos = await controlador.cargar();
      preparar();
      render();
    } catch (e) {
      Panel.avisar('No se pudo cargar la información. Recargá la página.');
    }
  })();
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

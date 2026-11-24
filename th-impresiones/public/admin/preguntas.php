<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Preguntas y checklist';
$paginaActiva = 'preguntas';
require __DIR__ . '/_layout_top.php';
?>
<h1>Preguntas y checklist</h1>
<p class="ayuda-pagina">El checklist y las preguntas se ven en la sección "Antes de pedir" de la página (si dejás las dos listas vacías, la sección no se muestra). Las opciones de consulta son el menú "Qué necesitás" de la pestaña "¿Necesitás algo distinto?", en Precios.</p>

<h2 class="panel-sub">Opciones de "¿Necesitás algo distinto?"</h2>
<p class="ayuda" style="margin-bottom:10px">Lo que el cliente elige en "Qué necesitás" y aparece en el mensaje de WhatsApp. Si no dejás ninguna, ese menú no se muestra.</p>
<div class="ptabla" id="lista-consultas"></div>
<div class="subir-row" style="margin:10px 0 28px">
  <button class="btn btn-ghost" type="button" id="btn-agregar-consulta">Agregar opción</button>
</div>

<h2 class="panel-sub">Checklist de archivo</h2>
<div class="ptabla" id="lista-checklist"></div>
<div class="subir-row" style="margin:10px 0 28px">
  <button class="btn btn-ghost" type="button" id="btn-agregar-check">Agregar punto</button>
</div>

<h2 class="panel-sub">Preguntas frecuentes</h2>
<div class="ptabla" id="lista-preguntas"></div>
<div class="subir-row" style="margin-top:10px">
  <button class="btn btn-ghost" type="button" id="btn-agregar-pregunta">Agregar pregunta</button>
</div>

<div class="panel-guardar">
  <span class="estado" id="estado-guardado"></span>
  <button class="btn btn-primary" type="button" id="btn-guardar">Guardar cambios</button>
</div>

<script>
(function () {
  const checkEl = document.getElementById('lista-checklist');
  const pregEl = document.getElementById('lista-preguntas');
  const consEl = document.getElementById('lista-consultas');
  const CONSULTAS_POR_DEFECTO = ['Un trabajo a medida', 'No tengo diseño', 'Consulta de plazos o cantidades', 'Otra cosa'];
  let datosCompletos = null;

  // Botones ↑ ↓ Eliminar comunes a las dos listas. Cada fila guarda su objeto en nodo._dato.
  function botonesOrden(nodo, descripcion) {
    const cont = document.createElement('div');
    cont.className = 'orden-btns';
    cont.innerHTML =
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="subir" aria-label="Subir">↑</button>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="bajar" aria-label="Bajar">↓</button>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="eliminar">Eliminar</button>';
    cont.querySelector('[data-accion="subir"]').addEventListener('click', () => {
      if (nodo.previousElementSibling) { nodo.parentNode.insertBefore(nodo, nodo.previousElementSibling); controlador.marcarCambios(); }
    });
    cont.querySelector('[data-accion="bajar"]').addEventListener('click', () => {
      if (nodo.nextElementSibling) { nodo.parentNode.insertBefore(nodo.nextElementSibling, nodo); controlador.marcarCambios(); }
    });
    cont.querySelector('[data-accion="eliminar"]').addEventListener('click', e => {
      Panel.confirmarEnLinea(e.currentTarget, '¿Eliminar ' + descripcion() + '?', () => {
        const siguiente = nodo.nextSibling, padre = nodo.parentNode;
        nodo.remove();
        controlador.marcarCambios();
        Panel.mostrarDeshacer('Se eliminó.', () => {
          if (siguiente) padre.insertBefore(nodo, siguiente); else padre.appendChild(nodo);
          controlador.marcarCambios();
        });
      });
    });
    return cont;
  }

  function filaCheck(texto, opciones) {
    const o = opciones || { etiqueta: 'Punto', ejemplo: 'Ej: Archivo en PDF, JPG o PNG', max: 160, cuadro: true };
    const nodo = document.createElement('div');
    nodo.className = 'pitem pitem-fila';
    nodo.innerHTML = (o.cuadro ? '<span class="check-prev" aria-hidden="true"></span>' : '<span></span>') +
      '<div class="field"><label></label><input data-campo="texto"></div>';
    nodo.querySelector('label').textContent = o.etiqueta;
    const input = nodo.querySelector('input');
    input.maxLength = o.max;
    input.value = texto || '';
    input.placeholder = o.ejemplo;
    input.addEventListener('input', () => controlador.marcarCambios());
    nodo.appendChild(botonesOrden(nodo, () => '"' + (input.value.trim() || 'este punto') + '"'));
    return nodo;
  }

  function filaPregunta(p) {
    const nodo = document.createElement('div');
    nodo.className = 'pitem';
    nodo.innerHTML =
      '<div class="pitem-cab"><strong></strong></div>' +
      '<div class="field"><label>Pregunta</label><input data-campo="pregunta" maxlength="160" placeholder="Ej: ¿Cuánto tarda mi pedido?"></div>' +
      '<div class="field"><label>Respuesta</label><textarea data-campo="respuesta" rows="3" maxlength="1000"></textarea></div>';
    const preg = nodo.querySelector('[data-campo="pregunta"]');
    const resp = nodo.querySelector('[data-campo="respuesta"]');
    const titulo = nodo.querySelector('.pitem-cab strong');
    preg.value = p.pregunta || '';
    resp.value = p.respuesta || '';
    const pintar = () => { titulo.textContent = preg.value.trim() || '(pregunta nueva)'; };
    pintar();
    [preg, resp].forEach(i => i.addEventListener('input', () => { pintar(); controlador.marcarCambios(); }));
    nodo.querySelector('.pitem-cab').appendChild(botonesOrden(nodo, () => 'esta pregunta'));
    return nodo;
  }

  function render() {
    const ayuda = datosCompletos.ayuda || {};
    checkEl.innerHTML = '';
    (ayuda.checklist || []).forEach(t => checkEl.appendChild(filaCheck(t)));
    pregEl.innerHTML = '';
    (ayuda.preguntas || []).forEach(p => pregEl.appendChild(filaPregunta(p)));
    consEl.innerHTML = '';
    (Array.isArray(ayuda.consultas) ? ayuda.consultas : CONSULTAS_POR_DEFECTO).forEach(t => consEl.appendChild(filaConsulta(t)));
  }

  function filaConsulta(texto) {
    return filaCheck(texto, { etiqueta: 'Opción', ejemplo: 'Ej: Un trabajo a medida', max: 60, cuadro: false });
  }

  function leer() {
    return {
      checklist: Array.from(checkEl.querySelectorAll('[data-campo="texto"]')).map(i => i.value.trim()).filter(Boolean),
      consultas: Array.from(consEl.querySelectorAll('[data-campo="texto"]')).map(i => i.value.trim()).filter(Boolean),
      preguntas: Array.from(pregEl.children).map(n => ({
        pregunta: n.querySelector('[data-campo="pregunta"]').value.trim(),
        respuesta: n.querySelector('[data-campo="respuesta"]').value.trim()
      })).filter(p => p.pregunta || p.respuesta)
    };
  }

  document.getElementById('btn-agregar-check').addEventListener('click', () => {
    const n = filaCheck('');
    checkEl.appendChild(n);
    n.querySelector('input').focus();
    controlador.marcarCambios();
  });
  document.getElementById('btn-agregar-consulta').addEventListener('click', () => {
    const n = filaConsulta('');
    consEl.appendChild(n);
    n.querySelector('input').focus();
    controlador.marcarCambios();
  });
  document.getElementById('btn-agregar-pregunta').addEventListener('click', () => {
    const n = filaPregunta({});
    pregEl.appendChild(n);
    n.querySelector('input').focus();
    controlador.marcarCambios();
  });

  const controlador = Panel.crearControladorGuardado({
    boton: document.getElementById('btn-guardar'),
    estado: document.getElementById('estado-guardado'),
    obtenerDatos: () => { datosCompletos.ayuda = leer(); return datosCompletos; },
    alGuardarOk: datos => { datosCompletos = datos; render(); }
  });

  (async () => {
    try {
      datosCompletos = await controlador.cargar();
      render();
    } catch (e) {
      Panel.avisar('No se pudo cargar la información. Recargá la página.');
    }
  })();
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Precios';
$paginaActiva = 'precios';
require __DIR__ . '/_layout_top.php';
?>
<h1>Precios</h1>
<p class="ayuda-pagina">Elegí una categoría de la lista. Para cada trabajo podés poner varios precios según la cantidad: cuanto más pide el cliente, más barato sale cada uno.</p>

<div class="ptab-cats" id="ptab-cats"></div>

<div class="pitem" id="editor-categoria" hidden>
  <div class="pitem-cab">
    <strong>Esta categoría</strong>
  </div>
  <div class="pitem-campos">
    <div class="field">
      <label for="cat-nombre">Nombre de la categoría</label>
      <input id="cat-nombre" type="text">
    </div>
    <div class="field">
      <span class="label">Color</span>
      <div class="colores" id="cat-colores"></div>
    </div>
  </div>
</div>

<div class="ptabla" id="ptabla"></div>

<div class="subir-row" style="margin-top:14px">
  <button class="btn btn-primary" type="button" id="btn-agregar-precio">Agregar trabajo</button>
  <button class="btn btn-ghost" type="button" id="btn-agregar-categoria">Agregar categoría</button>
</div>

<div class="panel-guardar">
  <span class="estado" id="estado-guardado"></span>
  <button class="btn btn-primary" type="button" id="btn-guardar">Guardar cambios</button>
</div>

<script>
(function () {
  const PALETA = ['#3A9AC2', '#E06D93', '#EFBE41', '#E98346', '#233466', '#A33678'];
  const tabsEl = document.getElementById('ptab-cats');
  const editorEl = document.getElementById('editor-categoria');
  const ptablaEl = document.getElementById('ptabla');

  let datosCompletos = null;
  let categoriaActiva = null;

  function pintarColores(contId, colorActual) {
    const cont = document.getElementById(contId);
    cont.innerHTML = '';
    PALETA.forEach((c, i) => {
      const id = contId + '-' + i;
      const input = document.createElement('input');
      input.type = 'radio'; input.name = contId; input.id = id; input.value = c;
      if (c.toUpperCase() === (colorActual || '').toUpperCase()) input.checked = true;
      const label = document.createElement('label');
      label.setAttribute('for', id);
      label.style.background = c;
      label.title = c;
      input.addEventListener('change', () => controlador.marcarCambios());
      cont.append(input, label);
    });
  }

  function pintarTabs() {
    tabsEl.innerHTML = '';
    datosCompletos.categoriasPrecios.forEach(c => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'tab';
      b.style.setProperty('--tab-c', c.color);
      b.style.setProperty('--tab-c-fg', '#16244F');
      b.setAttribute('aria-pressed', String(c.id === categoriaActiva));
      b.textContent = c.nombre || '(sin nombre)';
      b.addEventListener('click', () => cambiarCategoria(c.id));
      tabsEl.appendChild(b);
    });
  }

  function pintarEditorCategoria() {
    const cat = datosCompletos.categoriasPrecios.find(c => c.id === categoriaActiva);
    if (!cat) { editorEl.hidden = true; return; }
    editorEl.hidden = false;
    document.getElementById('cat-nombre').value = cat.nombre || '';
    pintarColores('cat-colores', cat.color);
  }

  function actualizarPreview(nodo) {
    const nombre = nodo.querySelector('[data-campo="nombre"]').value.trim() || '(sin nombre)';
    const unidad = nodo.querySelector('[data-campo="unidad"]').value.trim() || 'unidad';
    const filas = Array.from(nodo.querySelectorAll('[data-escalas] .pescala-fila'));
    const partes = filas.map(f => {
      const desde = f.querySelector('[data-campo="desde"]').value || '?';
      const precio = f.querySelector('[data-campo="precio"]').value || '0';
      return 'desde ' + desde + ' sale ' + Panel.money(parseInt(precio, 10) || 0);
    });
    nodo.querySelector('[data-preview]').textContent = partes.length
      ? 'Así lo ve el cliente: "' + nombre + '" — ' + partes.join(' · ') + ' (por ' + unidad + ').'
      : 'Agregá al menos un precio por cantidad.';
    nodo.querySelector('.pitem-cab strong').textContent = nombre;
  }

  function crearFilaEscala(e) {
    const fila = document.createElement('div');
    fila.className = 'pescala-fila';
    fila.innerHTML =
      '<div class="field"><label>Si piden desde</label><input type="number" min="1" inputmode="numeric" data-campo="desde"></div>' +
      '<div class="field"><label>Cada una sale</label><input type="number" min="0" inputmode="numeric" data-campo="precio"></div>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="quitar-escala">Quitar</button>';
    fila.querySelector('[data-campo="desde"]').value = e.desde ?? '';
    fila.querySelector('[data-campo="precio"]').value = e.precio ?? '';
    fila.querySelectorAll('input').forEach(i => i.addEventListener('input', () => {
      actualizarPreview(fila.closest('.pitem'));
      controlador.marcarCambios();
    }));
    fila.querySelector('[data-accion="quitar-escala"]').addEventListener('click', () => {
      const item = fila.closest('.pitem');
      fila.remove();
      actualizarPreview(item);
      controlador.marcarCambios();
    });
    return fila;
  }

  function moverItem(nodo, direccion) {
    const padre = nodo.parentNode;
    if (direccion === -1 && nodo.previousElementSibling) padre.insertBefore(nodo, nodo.previousElementSibling);
    else if (direccion === 1 && nodo.nextElementSibling) padre.insertBefore(nodo.nextElementSibling, nodo);
    controlador.marcarCambios();
  }

  function crearNodoItem(item) {
    const nodo = document.createElement('div');
    nodo.className = 'pitem';
    nodo.dataset.id = item.id || '';
    nodo.innerHTML =
      '<div class="pitem-cab"><strong></strong><div class="orden-btns">' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="subir" aria-label="Subir">↑</button>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="bajar" aria-label="Bajar">↓</button>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="eliminar">Eliminar</button>' +
      '</div></div>' +
      '<div class="pitem-campos">' +
      '<div class="field"><label>Nombre</label><input data-campo="nombre"></div>' +
      '<div class="field"><label>Descripción corta</label><input data-campo="detalle"><p class="ayuda">Ej: 80 g · simple faz</p></div>' +
      '<div class="field"><label>Unidad (singular)</label><input data-campo="unidad"><p class="ayuda">Ej: hoja</p></div>' +
      '<div class="field"><label>Unidad (plural)</label><input data-campo="plural"><p class="ayuda">Ej: hojas</p></div>' +
      '</div>' +
      '<div class="pescalas" data-escalas></div>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-accion="agregar-escala">Agregar precio por cantidad</button>' +
      '<p class="previsualizacion" data-preview></p>';

    nodo.querySelector('[data-campo="nombre"]').value = item.nombre || '';
    nodo.querySelector('[data-campo="detalle"]').value = item.detalle || '';
    nodo.querySelector('[data-campo="unidad"]').value = item.unidad || '';
    nodo.querySelector('[data-campo="plural"]').value = item.plural || '';

    const escalasCont = nodo.querySelector('[data-escalas]');
    const escalas = (item.escalas && item.escalas.length) ? item.escalas : [{ desde: 1, precio: 0 }];
    escalas.forEach(e => escalasCont.appendChild(crearFilaEscala(e)));

    nodo.querySelectorAll('.pitem-campos input').forEach(i => i.addEventListener('input', () => {
      actualizarPreview(nodo);
      controlador.marcarCambios();
    }));
    nodo.querySelector('[data-accion="agregar-escala"]').addEventListener('click', () => {
      escalasCont.appendChild(crearFilaEscala({ desde: '', precio: '' }));
      actualizarPreview(nodo);
      controlador.marcarCambios();
    });
    nodo.querySelector('[data-accion="subir"]').addEventListener('click', () => moverItem(nodo, -1));
    nodo.querySelector('[data-accion="bajar"]').addEventListener('click', () => moverItem(nodo, 1));
    nodo.querySelector('[data-accion="eliminar"]').addEventListener('click', e => {
      const boton = e.currentTarget;
      const nombre = nodo.querySelector('[data-campo="nombre"]').value.trim() || 'este trabajo';
      Panel.confirmarEnLinea(boton, '¿Eliminar "' + nombre + '" de la lista de precios?', () => {
        const siguiente = nodo.nextSibling, padre = nodo.parentNode;
        nodo.remove();
        controlador.marcarCambios();
        Panel.mostrarDeshacer('Se eliminó "' + nombre + '".', () => {
          if (siguiente) padre.insertBefore(nodo, siguiente); else padre.appendChild(nodo);
        });
      });
    });

    actualizarPreview(nodo);
    return nodo;
  }

  function pintarItems() {
    ptablaEl.innerHTML = '';
    datosCompletos.precios.filter(p => p.categoria === categoriaActiva).forEach(item => {
      ptablaEl.appendChild(crearNodoItem(item));
    });
  }

  function render() {
    pintarTabs();
    pintarEditorCategoria();
    pintarItems();
  }

  function leerItemDesdeDOM(nodo) {
    const escalas = Array.from(nodo.querySelectorAll('[data-escalas] .pescala-fila')).map(f => ({
      desde: parseInt(f.querySelector('[data-campo="desde"]').value, 10) || 1,
      precio: parseInt(f.querySelector('[data-campo="precio"]').value, 10) || 0
    }));
    return {
      id: nodo.dataset.id || '',
      categoria: categoriaActiva,
      nombre: nodo.querySelector('[data-campo="nombre"]').value.trim(),
      detalle: nodo.querySelector('[data-campo="detalle"]').value.trim(),
      unidad: nodo.querySelector('[data-campo="unidad"]').value.trim(),
      plural: nodo.querySelector('[data-campo="plural"]').value.trim(),
      escalas
    };
  }

  function sincronizarActivaDesdeDOM() {
    if (!categoriaActiva) return;
    const cat = datosCompletos.categoriasPrecios.find(c => c.id === categoriaActiva);
    if (cat && !editorEl.hidden) {
      cat.nombre = document.getElementById('cat-nombre').value.trim();
      const colorInput = document.querySelector('#cat-colores input:checked');
      if (colorInput) cat.color = colorInput.value;
    }
    const itemsNuevos = Array.from(ptablaEl.querySelectorAll('.pitem')).map(leerItemDesdeDOM);
    const otros = datosCompletos.precios.filter(p => p.categoria !== categoriaActiva);
    datosCompletos.precios = otros.concat(itemsNuevos);
  }

  function cambiarCategoria(id) {
    sincronizarActivaDesdeDOM();
    categoriaActiva = id;
    render();
  }

  document.getElementById('btn-agregar-precio').addEventListener('click', () => {
    const nodo = crearNodoItem({ id: '', categoria: categoriaActiva, nombre: '', detalle: '', unidad: '', plural: '', escalas: [{ desde: 1, precio: 0 }] });
    ptablaEl.appendChild(nodo);
    nodo.querySelector('[data-campo="nombre"]').focus();
    controlador.marcarCambios();
  });

  document.getElementById('btn-agregar-categoria').addEventListener('click', () => {
    sincronizarActivaDesdeDOM();
    const nuevaId = 'cat-' + Math.random().toString(36).slice(2, 8);
    datosCompletos.categoriasPrecios.push({ id: nuevaId, nombre: 'Nueva categoría', color: PALETA[0] });
    categoriaActiva = nuevaId;
    render();
    const campoNombre = document.getElementById('cat-nombre');
    campoNombre.focus();
    campoNombre.select();
    controlador.marcarCambios();
  });

  const controlador = Panel.crearControladorGuardado({
    boton: document.getElementById('btn-guardar'),
    estado: document.getElementById('estado-guardado'),
    obtenerDatos: () => { sincronizarActivaDesdeDOM(); return datosCompletos; },
    alGuardarOk: datos => {
      datosCompletos = datos;
      render();
    }
  });

  (async () => {
    try {
      datosCompletos = await controlador.cargar();
      categoriaActiva = (datosCompletos.categoriasPrecios[0] || {}).id || null;
      render();
    } catch (e) {
      Panel.avisar('No se pudo cargar la información. Recargá la página.');
    }
  })();
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

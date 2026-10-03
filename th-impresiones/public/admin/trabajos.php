<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Trabajos';
$paginaActiva = 'trabajos';
require __DIR__ . '/_layout_top.php';
?>
<h1>Trabajos</h1>
<p class="ayuda-pagina">Las fotos y tarjetas que ve la gente en "Muestras de lo que hacemos". Podés subir una foto nueva, cambiar el texto, ocultar un trabajo sin borrarlo, o eliminarlo.</p>

<div class="ptab-cats" id="ttab-cats"></div>

<div class="pitem" id="editor-categoria-t" hidden>
  <div class="pitem-cab"><strong>Esta categoría</strong></div>
  <div class="pitem-campos">
    <div class="field">
      <label for="catt-nombre">Nombre de la categoría</label>
      <input id="catt-nombre" type="text">
    </div>
    <div class="field">
      <span class="label">Color</span>
      <div class="colores" id="catt-colores"></div>
    </div>
  </div>
</div>

<div class="tgrid" id="tgrid"></div>

<div class="subir-row" style="margin-top:14px">
  <button class="btn btn-primary" type="button" id="btn-agregar-trabajo">Agregar trabajo</button>
  <button class="btn btn-ghost" type="button" id="btn-agregar-categoria-t">Agregar categoría</button>
</div>

<div class="panel-guardar">
  <span class="estado" id="estado-guardado"></span>
  <button class="btn btn-primary" type="button" id="btn-guardar">Guardar cambios</button>
</div>

<script>
(function () {
  const PALETA = ['#3A9AC2', '#E06D93', '#EFBE41', '#E98346', '#233466', '#A33678'];
  const tabsEl = document.getElementById('ttab-cats');
  const editorEl = document.getElementById('editor-categoria-t');
  const gridEl = document.getElementById('tgrid');

  let datosCompletos = null;
  let filtroActivo = '';

  function colorDeCategoria(id) {
    const c = datosCompletos.categoriasTrabajos.find(c => c.id === id);
    return c ? c.color : '#CCCCCC';
  }
  function nombreDeCategoria(id) {
    const c = datosCompletos.categoriasTrabajos.find(c => c.id === id);
    return c ? c.nombre : '(sin categoría)';
  }

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
    const todas = document.createElement('button');
    todas.type = 'button'; todas.className = 'tab';
    todas.setAttribute('aria-pressed', String(filtroActivo === ''));
    todas.textContent = 'Todas';
    todas.addEventListener('click', () => cambiarFiltro(''));
    tabsEl.appendChild(todas);

    datosCompletos.categoriasTrabajos.forEach(c => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'tab';
      b.style.setProperty('--tab-c', c.color);
      b.style.setProperty('--tab-c-fg', '#16244F');
      b.setAttribute('aria-pressed', String(c.id === filtroActivo));
      b.textContent = c.nombre || '(sin nombre)';
      b.addEventListener('click', () => cambiarFiltro(c.id));
      tabsEl.appendChild(b);
    });
  }

  function pintarEditorCategoria() {
    const cat = datosCompletos.categoriasTrabajos.find(c => c.id === filtroActivo);
    if (!cat) { editorEl.hidden = true; return; }
    editorEl.hidden = false;
    document.getElementById('catt-nombre').value = cat.nombre || '';
    pintarColores('catt-colores', cat.color);
  }

  async function subirArchivo(file, nodo) {
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
      Panel.avisar('La foto pesa más de 5 MB. Probá con una más liviana.');
      return;
    }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      Panel.avisar('Subí una foto en JPG, PNG o WebP.');
      return;
    }
    const fd = new FormData();
    fd.append('foto', file);
    nodo.classList.add('subiendo');
    try {
      const r = await Panel.apiFetch('api/subir_imagen.php', { method: 'POST', body: fd });
      nodo.dataset.imagen = r.archivo;
      nodo.querySelector('[data-foto-img]').src = '../' + r.archivo;
      nodo.querySelector('.tcard-foto').classList.remove('tdrop');
      controlador.marcarCambios();
      Panel.avisar('Foto subida.');
    } catch (e) {
      Panel.avisar(e.message);
    } finally {
      nodo.classList.remove('subiendo');
    }
  }

  function crearNodoTrabajo(item) {
    const nodo = document.createElement('div');
    nodo.className = 'tcard' + (item.visible === false ? ' oculto' : '');
    nodo.dataset.id = item.id || '';
    nodo.dataset.imagen = item.imagen || '';

    const foto = document.createElement('div');
    foto.className = 'tcard-foto' + (item.imagen ? '' : ' tdrop');
    const badge = document.createElement('span');
    badge.className = 'tcard-badge';
    badge.textContent = nombreDeCategoria(item.categoria);
    const img = document.createElement('img');
    img.setAttribute('data-foto-img', '1');
    img.alt = '';
    img.src = item.imagen ? '../' + item.imagen : '';
    const inputFile = document.createElement('input');
    inputFile.type = 'file';
    inputFile.accept = 'image/jpeg,image/png,image/webp';
    inputFile.style.display = 'none';
    foto.append(badge, img, inputFile);

    foto.addEventListener('click', e => { if (e.target !== inputFile) inputFile.click(); });
    inputFile.addEventListener('change', () => subirArchivo(inputFile.files[0], nodo));
    foto.addEventListener('dragover', e => { e.preventDefault(); foto.classList.add('sobre'); });
    foto.addEventListener('dragleave', () => foto.classList.remove('sobre'));
    foto.addEventListener('drop', e => {
      e.preventDefault();
      foto.classList.remove('sobre');
      if (e.dataTransfer.files[0]) subirArchivo(e.dataTransfer.files[0], nodo);
    });

    const cuerpo = document.createElement('div');
    cuerpo.className = 'tcard-cuerpo';
    cuerpo.innerHTML =
      '<div class="field"><label>Título</label><input data-campo="titulo"></div>' +
      '<div class="field"><label>Especificación</label><input data-campo="especificacion"><p class="ayuda">Ej: 300 g · mate · 9 × 5 cm</p></div>' +
      '<div class="field"><label>Descripción</label><textarea data-campo="descripcion" rows="3"></textarea></div>' +
      '<div class="field"><label>Categoría</label><select data-campo="categoria"></select></div>' +
      '<label style="display:flex;align-items:center;gap:8px;min-height:44px"><input type="checkbox" data-campo="visible" style="width:20px;height:20px"> Mostrar en la página</label>';
    cuerpo.querySelector('[data-campo="titulo"]').value = item.titulo || '';
    cuerpo.querySelector('[data-campo="especificacion"]').value = item.especificacion || '';
    cuerpo.querySelector('[data-campo="descripcion"]').value = item.descripcion || '';
    const selectCat = cuerpo.querySelector('[data-campo="categoria"]');
    datosCompletos.categoriasTrabajos.forEach(c => {
      const op = document.createElement('option');
      op.value = c.id; op.textContent = c.nombre;
      if (c.id === item.categoria) op.selected = true;
      selectCat.appendChild(op);
    });
    cuerpo.querySelector('[data-campo="visible"]').checked = item.visible !== false;

    cuerpo.querySelectorAll('input,textarea,select').forEach(campo => {
      campo.addEventListener('input', () => controlador.marcarCambios());
      campo.addEventListener('change', () => {
        controlador.marcarCambios();
        if (campo.dataset.campo === 'visible') nodo.classList.toggle('oculto', !campo.checked);
        if (campo.dataset.campo === 'categoria') badge.textContent = nombreDeCategoria(campo.value);
      });
    });

    const acciones = document.createElement('div');
    acciones.className = 'tcard-acciones';
    const btnCambiarFoto = document.createElement('button');
    btnCambiarFoto.type = 'button'; btnCambiarFoto.className = 'btn btn-ghost btn-sm';
    btnCambiarFoto.textContent = 'Cambiar foto';
    btnCambiarFoto.addEventListener('click', () => inputFile.click());
    const btnEliminar = document.createElement('button');
    btnEliminar.type = 'button'; btnEliminar.className = 'btn btn-ghost btn-sm';
    btnEliminar.textContent = 'Eliminar';
    btnEliminar.addEventListener('click', e => {
      const boton = e.currentTarget;
      const titulo = cuerpo.querySelector('[data-campo="titulo"]').value.trim() || 'este trabajo';
      Panel.confirmarEnLinea(boton, '¿Eliminar "' + titulo + '" de la galería?', () => {
        const siguiente = nodo.nextSibling, padre = nodo.parentNode;
        nodo.remove();
        controlador.marcarCambios();
        Panel.mostrarDeshacer('Se eliminó "' + titulo + '".', () => {
          if (siguiente) padre.insertBefore(nodo, siguiente); else padre.appendChild(nodo);
        });
      });
    });
    acciones.append(btnCambiarFoto, btnEliminar);

    nodo.append(foto, cuerpo, acciones);
    return nodo;
  }

  function pintarGrilla() {
    gridEl.innerHTML = '';
    const items = filtroActivo
      ? datosCompletos.trabajos.filter(t => t.categoria === filtroActivo)
      : datosCompletos.trabajos;
    if (!items.length) {
      gridEl.innerHTML = '<p class="vacio">No hay trabajos en esta categoría todavía.</p>';
      return;
    }
    items.forEach(item => gridEl.appendChild(crearNodoTrabajo(item)));
  }

  function render() {
    pintarTabs();
    pintarEditorCategoria();
    pintarGrilla();
  }

  function leerTrabajoDesdeDOM(nodo) {
    return {
      id: nodo.dataset.id || '',
      titulo: nodo.querySelector('[data-campo="titulo"]').value.trim(),
      especificacion: nodo.querySelector('[data-campo="especificacion"]').value.trim(),
      descripcion: nodo.querySelector('[data-campo="descripcion"]').value.trim(),
      categoria: nodo.querySelector('[data-campo="categoria"]').value,
      imagen: nodo.dataset.imagen || '',
      visible: nodo.querySelector('[data-campo="visible"]').checked
    };
  }

  function sincronizarActivaDesdeDOM() {
    const cat = datosCompletos.categoriasTrabajos.find(c => c.id === filtroActivo);
    if (cat && !editorEl.hidden) {
      cat.nombre = document.getElementById('catt-nombre').value.trim();
      const colorInput = document.querySelector('#catt-colores input:checked');
      if (colorInput) cat.color = colorInput.value;
    }
    const leidos = Array.from(gridEl.querySelectorAll('.tcard')).map(leerTrabajoDesdeDOM);
    if (filtroActivo === '') {
      // "Todas" muestra la grilla completa: lo leído del DOM es todo el conjunto.
      datosCompletos.trabajos = leidos;
    } else {
      // Solo se ve (y se sincroniza) la categoría activa; el resto queda intacto.
      const otros = datosCompletos.trabajos.filter(t => t.categoria !== filtroActivo);
      datosCompletos.trabajos = otros.concat(leidos);
    }
  }

  function cambiarFiltro(id) {
    sincronizarActivaDesdeDOM();
    filtroActivo = id;
    render();
  }

  document.getElementById('btn-agregar-trabajo').addEventListener('click', () => {
    const nodo = crearNodoTrabajo({ id: '', titulo: '', especificacion: '', descripcion: '', categoria: filtroActivo || (datosCompletos.categoriasTrabajos[0] || {}).id || '', imagen: '', visible: true });
    if (gridEl.querySelector('.vacio')) gridEl.innerHTML = '';
    gridEl.prepend(nodo);
    nodo.querySelector('[data-campo="titulo"]').focus();
    controlador.marcarCambios();
  });

  document.getElementById('btn-agregar-categoria-t').addEventListener('click', () => {
    sincronizarActivaDesdeDOM();
    const nuevaId = 'cat-' + Math.random().toString(36).slice(2, 8);
    datosCompletos.categoriasTrabajos.push({ id: nuevaId, nombre: 'Nueva categoría', color: PALETA[0] });
    filtroActivo = nuevaId;
    render();
    const campoNombre = document.getElementById('catt-nombre');
    campoNombre.focus();
    campoNombre.select();
    controlador.marcarCambios();
  });

  const controlador = Panel.crearControladorGuardado({
    boton: document.getElementById('btn-guardar'),
    estado: document.getElementById('estado-guardado'),
    obtenerDatos: () => { sincronizarActivaDesdeDOM(); return datosCompletos; },
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

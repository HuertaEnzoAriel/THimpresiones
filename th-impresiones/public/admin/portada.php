<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Portada';
$paginaActiva = 'portada';
require __DIR__ . '/_layout_top.php';
?>
<h1>Portada</h1>
<p class="ayuda-pagina">Lo primero que ve quien entra a la página: la imagen principal y los textos de arriba de todo. A la derecha ves cómo queda mientras escribís.</p>

<div class="portada-grid">
  <form class="panel-form" id="form-portada" novalidate>
    <div class="field">
      <span class="label">Imagen principal</span>
      <div class="tcard-foto tdrop" id="hero-foto" style="max-width:280px;aspect-ratio:1/1">
        <img id="hero-prev" alt="" style="display:none">
        <input type="file" id="hero-file" accept="image/jpeg,image/png,image/webp">
        <span id="hero-txt" style="display:block;padding-top:40%">Tocá para elegir una foto</span>
      </div>
      <p class="ayuda">Se ve a la derecha del título (en el celular, debajo). Si no ponés ninguna, el título ocupa todo el ancho.</p>
      <div class="subir-row">
        <button class="btn btn-ghost btn-sm" type="button" id="hero-cambiar">Elegir foto</button>
        <button class="btn btn-ghost btn-sm" type="button" id="hero-quitar">Quitar</button>
      </div>
    </div>
    <div class="field">
      <label for="f-alt">Qué se ve en la foto</label>
      <input id="f-alt" type="text" maxlength="160" placeholder="Ej: Tarjetas y flyers recién impresos sobre el mostrador">
      <p class="ayuda">Lo leen los lectores de pantalla y Google. Una frase corta alcanza.</p>
    </div>

    <div class="field">
      <label for="f-etiqueta">Etiqueta de arriba</label>
      <input id="f-etiqueta" type="text" maxlength="80">
      <p class="ayuda">El texto chiquito en mayúsculas. Ej: Impresión digital · Fotografía · Papelería</p>
    </div>
    <div class="field">
      <label for="f-titulo">Título</label>
      <textarea id="f-titulo" rows="3"></textarea>
      <p class="ayuda">Un renglón por línea (hasta 4). Mejor corto: se muestra grande y en mayúsculas.</p>
    </div>
    <div class="field">
      <label for="f-texto">Texto debajo del título</label>
      <textarea id="f-texto" rows="3" maxlength="300"></textarea>
    </div>
    <div class="pitem-campos">
      <div class="field">
        <label for="f-btn1">Botón principal</label>
        <input id="f-btn1" type="text" maxlength="40">
        <p class="ayuda">Lleva a la calculadora.</p>
      </div>
      <div class="field">
        <label for="f-btn2">Botón secundario</label>
        <input id="f-btn2" type="text" maxlength="40">
        <p class="ayuda">Lleva a los trabajos. Vacío = no se muestra.</p>
      </div>
    </div>

    <div class="field">
      <span class="label">Datos de abajo</span>
      <p class="ayuda">Hasta 4. Ej: "Papeles" / "64 a 300 g".</p>
      <div class="hfilas" id="datos-filas"></div>
      <div class="subir-row"><button class="btn btn-ghost btn-sm" type="button" id="btn-agregar-dato">Agregar dato</button></div>
    </div>
  </form>

  <div class="portada-prev-wrap">
    <p class="label">Vista previa</p>
    <div class="prev-hero" id="prev-hero" aria-hidden="true"></div>
  </div>
</div>

<div class="panel-guardar">
  <span class="estado" id="estado-guardado"></span>
  <button class="btn btn-primary" type="button" id="btn-guardar">Guardar cambios</button>
</div>

<script>
(function () {
  // Lo que muestra la página si nunca se cargó la portada desde el panel.
  const POR_DEFECTO = {
    etiqueta: 'Impresión digital · Fotografía · Papelería',
    titulo: 'Salió como\nlo pensaste.',
    texto: 'Impresiones, fotos, tarjetas, flyers, stickers y credenciales. Mandanos tu archivo y te pasamos el precio.',
    botonPrincipal: 'Calcular mi pedido',
    botonSecundario: 'Ver trabajos',
    datos: [{ titulo: 'Formatos', valor: 'A6 a A4' }, { titulo: 'Papeles', valor: '64 a 300 g' }, { titulo: 'Archivos', valor: 'PDF · JPG · PNG' }],
    imagenAlt: ''
  };
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const filasEl = $('datos-filas');
  let datosCompletos = null;
  let imagenHero = '';

  /* ===== Imagen ===== */
  function pintarFoto() {
    const prev = $('hero-prev');
    prev.style.display = imagenHero ? 'block' : 'none';
    prev.src = imagenHero ? '../' + imagenHero : '';
    $('hero-txt').style.display = imagenHero ? 'none' : 'block';
    $('hero-foto').classList.toggle('tdrop', !imagenHero);
    $('hero-quitar').hidden = !imagenHero;
    pintarPrev();
  }
  async function subirFoto(file) {
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      Panel.avisar('Subí una foto en JPG, PNG o WebP.');
      return;
    }
    const editada = await Panel.editarFoto(file, { aspectoAncho: 1, aspectoAlto: 1 });
    if (!editada) return;
    if (editada.size > Panel.limiteImagenBytes()) {
      Panel.avisar('La foto pesa demasiado. Probá con una más liviana.');
      return;
    }
    const fd = new FormData();
    fd.append('foto', editada);
    try {
      const r = await Panel.apiFetch('api/subir_imagen.php', { method: 'POST', body: fd });
      imagenHero = r.archivo;
      pintarFoto();
      controlador.marcarCambios();
      Panel.avisar('Foto subida.');
    } catch (e) {
      Panel.avisar(e.message);
    }
  }
  const heroFile = $('hero-file');
  $('hero-foto').addEventListener('click', e => { if (e.target !== heroFile) heroFile.click(); });
  $('hero-cambiar').addEventListener('click', () => heroFile.click());
  $('hero-quitar').addEventListener('click', () => { imagenHero = ''; pintarFoto(); controlador.marcarCambios(); });
  heroFile.addEventListener('change', () => { const f = heroFile.files[0]; heroFile.value = ''; subirFoto(f); });

  /* ===== Datos de abajo ===== */
  function filaDato(d) {
    const fila = document.createElement('div');
    fila.className = 'hfila';
    fila.innerHTML =
      '<div class="field"><label>Título</label><input type="text" data-campo="titulo" maxlength="30" placeholder="Ej: Papeles"></div>' +
      '<div class="field"><label>Valor</label><input type="text" data-campo="valor" maxlength="40" placeholder="Ej: 64 a 300 g"></div>' +
      '<button type="button" class="btn btn-ghost btn-sm">Quitar</button>';
    fila.querySelector('[data-campo="titulo"]').value = d.titulo || '';
    fila.querySelector('[data-campo="valor"]').value = d.valor || '';
    fila.querySelector('button').addEventListener('click', () => { fila.remove(); cambio(); });
    return fila;
  }
  $('btn-agregar-dato').addEventListener('click', () => {
    if (filasEl.children.length >= 4) { Panel.avisar('Hasta 4 datos.'); return; }
    const f = filaDato({});
    filasEl.appendChild(f);
    f.querySelector('input').focus();
    cambio();
  });

  /* ===== Leer / pintar ===== */
  function leer() {
    return {
      etiqueta: $('f-etiqueta').value.trim(),
      titulo: $('f-titulo').value.split('\n').map(l => l.trim()).filter(Boolean).join('\n'),
      texto: $('f-texto').value.trim(),
      botonPrincipal: $('f-btn1').value.trim(),
      botonSecundario: $('f-btn2').value.trim(),
      datos: Array.from(filasEl.children).map(f => ({
        titulo: f.querySelector('[data-campo="titulo"]').value.trim(),
        valor: f.querySelector('[data-campo="valor"]').value.trim()
      })).filter(d => d.titulo || d.valor),
      imagenAlt: $('f-alt').value.trim()
    };
  }

  function pintarPrev() {
    const p = leer();
    const lineas = p.titulo.split('\n').filter(Boolean);
    $('prev-hero').classList.toggle('con-foto', !!imagenHero);
    $('prev-hero').innerHTML =
      '<div class="ph-txt">' +
      (p.etiqueta ? '<p class="ph-eti">' + esc(p.etiqueta) + '</p>' : '') +
      '<p class="ph-tit">' + (lineas.length ? lineas.map(l => '<span>' + esc(l) + '</span>').join('') : '<span class="ph-falta">Falta el título</span>') + '</p>' +
      (p.texto ? '<p class="ph-lead">' + esc(p.texto) + '</p>' : '') +
      '<p class="ph-btns"><span class="ph-b1">' + esc(p.botonPrincipal || 'Calcular mi pedido') + '</span>' +
      (p.botonSecundario ? '<span class="ph-b2">' + esc(p.botonSecundario) + '</span>' : '') + '</p>' +
      (p.datos.length ? '<dl class="ph-datos" style="grid-template-columns:repeat(' + p.datos.length + ',minmax(0,1fr))">' +
        p.datos.map(d => '<div><dt>' + esc(d.titulo) + '</dt><dd>' + esc(d.valor) + '</dd></div>').join('') + '</dl>' : '') +
      '</div>' +
      (imagenHero ? '<img class="ph-img" src="../' + esc(imagenHero) + '" alt="">' : '');
  }

  function cambio() { pintarPrev(); controlador.marcarCambios(); }
  $('form-portada').addEventListener('input', e => { if (e.target.type !== 'file') cambio(); });

  function pintarFormulario() {
    const p = Object.assign({}, POR_DEFECTO, datosCompletos.portada || {});
    $('f-etiqueta').value = p.etiqueta || '';
    $('f-titulo').value = p.titulo || '';
    $('f-texto').value = p.texto || '';
    $('f-btn1').value = p.botonPrincipal || '';
    $('f-btn2').value = p.botonSecundario || '';
    $('f-alt').value = p.imagenAlt || '';
    filasEl.innerHTML = '';
    (p.datos || []).forEach(d => filasEl.appendChild(filaDato(d)));
    imagenHero = (datosCompletos.negocio || {}).imagenHero || '';
    pintarFoto();
  }

  const controlador = Panel.crearControladorGuardado({
    boton: $('btn-guardar'),
    estado: $('estado-guardado'),
    obtenerDatos: () => {
      datosCompletos.portada = leer();
      datosCompletos.negocio = Object.assign({}, datosCompletos.negocio, { imagenHero });
      return datosCompletos;
    },
    alGuardarOk: datos => { datosCompletos = datos; }
  });

  (async () => {
    try {
      datosCompletos = await controlador.cargar();
      pintarFormulario();
    } catch (e) {
      Panel.avisar('No se pudo cargar la información. Recargá la página.');
    }
  })();
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

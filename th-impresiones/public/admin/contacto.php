<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Contacto y horarios';
$paginaActiva = 'contacto';
require __DIR__ . '/_layout_top.php';
?>
<h1>Contacto y horarios</h1>
<p class="ayuda-pagina">Esto es lo que ve quien entra a la página: el WhatsApp, el mail, el Instagram y los horarios de atención. Con el horario la página también muestra el cartel "Abierto ahora" o "Cerrado".</p>

<form class="panel-form" id="form-contacto" style="max-width:560px" novalidate>
  <div class="field">
    <label for="f-nombre">Nombre del negocio</label>
    <input id="f-nombre" type="text">
    <p class="ayuda">Ej: TH Impresiones</p>
  </div>
  <div class="field">
    <label for="f-whatsapp">WhatsApp</label>
    <input id="f-whatsapp" type="text" inputmode="numeric">
    <p class="ayuda">Solo números, con el código de país. Ej: 5492644775316</p>
  </div>
  <div class="field">
    <label for="f-telefono">Teléfono para mostrar</label>
    <input id="f-telefono" type="text">
    <p class="ayuda">Así se ve en la página. Ej: +54 9 264 477-5316</p>
  </div>
  <div class="field">
    <label for="f-email">Mail</label>
    <input id="f-email" type="email">
  </div>
  <div class="field">
    <label for="f-instagram">Instagram</label>
    <input id="f-instagram" type="text">
    <p class="ayuda">Con o sin arroba, como prefieras mostrarlo. Ej: @thimpresiones</p>
  </div>

  <div class="field">
    <span class="label">Imagen principal de la página</span>
    <div class="tcard-foto tdrop" id="hero-foto" style="max-width:320px;aspect-ratio:1/1">
      <img id="hero-prev" alt="" style="display:none">
      <input type="file" id="hero-file" accept="image/jpeg,image/png,image/webp">
      <span id="hero-txt" style="display:block;padding-top:40%">Tocá para elegir una foto</span>
    </div>
    <p class="ayuda">Es la imagen grande que se ve arriba, junto al título. Si no ponés ninguna, no se muestra nada.</p>
    <div class="subir-row">
      <button class="btn btn-ghost btn-sm" type="button" id="hero-cambiar">Elegir foto</button>
      <button class="btn btn-ghost btn-sm" type="button" id="hero-quitar">Quitar</button>
    </div>
  </div>

  <div class="field">
    <span class="label">Horarios de atención (hora de Argentina)</span>
    <p class="ayuda">Marcá los días que abrís. Si cortás al mediodía, completá también el segundo turno; si no, dejalo vacío.</p>
    <div class="hsemana" id="hsemana"></div>
    <div class="subir-row"><button class="btn btn-ghost btn-sm" type="button" id="btn-copiar-lunes">Copiar el lunes a martes–viernes</button></div>
    <p class="previsualizacion" id="horario-prev"></p>
  </div>
</form>

<div class="panel-guardar">
  <span class="estado" id="estado-guardado"></span>
  <button class="btn btn-primary" type="button" id="btn-guardar">Guardar cambios</button>
</div>

<script src="../js/horario.js?v=<?= filemtime(__DIR__ . '/../js/horario.js') ?>"></script>
<script>
(function () {
  const hsemana = document.getElementById('hsemana');
  let datosCompletos = null;
  let imagenHero = '';

  function pintarHero() {
    const prev = document.getElementById('hero-prev');
    prev.style.display = imagenHero ? 'block' : 'none';
    prev.src = imagenHero ? '../' + imagenHero : '';
    document.getElementById('hero-txt').style.display = imagenHero ? 'none' : 'block';
    document.getElementById('hero-foto').classList.toggle('tdrop', !imagenHero);
    document.getElementById('hero-quitar').hidden = !imagenHero;
  }

  async function subirHero(file) {
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
      pintarHero();
      controlador.marcarCambios();
      Panel.avisar('Foto subida.');
    } catch (e) {
      Panel.avisar(e.message);
    }
  }

  const heroFile = document.getElementById('hero-file');
  document.getElementById('hero-foto').addEventListener('click', e => { if (e.target !== heroFile) heroFile.click(); });
  document.getElementById('hero-cambiar').addEventListener('click', () => heroFile.click());
  document.getElementById('hero-quitar').addEventListener('click', () => { imagenHero = ''; pintarHero(); controlador.marcarCambios(); });
  heroFile.addEventListener('change', () => { const f = heroFile.files[0]; heroFile.value = ''; subirHero(f); });

  /* Horario semanal: una fila por día, con "Abre" y hasta dos turnos. */
  let semana = TH.horario.normalizar([]);

  function filaDia(d) {
    const fila = document.createElement('div');
    fila.className = 'hdia';
    fila.dataset.dia = d.dia;
    const nombre = TH.horario.NOMBRE[d.dia];
    const t = d.turnos;
    fila.innerHTML =
      '<label class="hdia-abre"><input type="checkbox" data-campo="abre"> <span></span></label>' +
      '<div class="hdia-turnos">' +
      [0, 1].map(i =>
        '<div class="hturno"><input type="time" data-campo="desde" data-i="' + i + '" aria-label="Turno ' + (i + 1) + ', abre">' +
        '<span>a</span><input type="time" data-campo="hasta" data-i="' + i + '" aria-label="Turno ' + (i + 1) + ', cierra"></div>'
      ).join('') + '</div>';
    fila.querySelector('.hdia-abre span').textContent = nombre.charAt(0).toUpperCase() + nombre.slice(1);
    fila.querySelector('[data-campo="abre"]').checked = t.length > 0;
    fila.querySelectorAll('input[type="time"]').forEach(inp => {
      const turno = t[Number(inp.dataset.i)];
      inp.value = turno ? turno[inp.dataset.campo] : '';
    });
    return fila;
  }

  function leerSemana() {
    return Array.from(hsemana.children).map(fila => {
      const abre = fila.querySelector('[data-campo="abre"]').checked;
      const turnos = [0, 1].map(i => ({
        desde: fila.querySelector('[data-campo="desde"][data-i="' + i + '"]').value,
        hasta: fila.querySelector('[data-campo="hasta"][data-i="' + i + '"]').value
      })).filter(x => x.desde || x.hasta);
      return { dia: fila.dataset.dia, turnos: abre ? turnos : [] };
    });
  }

  function actualizarFilas() {
    Array.from(hsemana.children).forEach(fila => {
      const abre = fila.querySelector('[data-campo="abre"]').checked;
      fila.classList.toggle('cerrado', !abre);
      fila.querySelectorAll('input[type="time"]').forEach(i => { i.disabled = !abre; });
    });
  }

  function pintarPrevHorario() {
    const s = leerSemana();
    const completos = s.map(d => ({ dia: d.dia, turnos: d.turnos.filter(t => t.desde && t.hasta && t.desde < t.hasta) }));
    const lineas = TH.horario.agrupar(completos).map(g => g.dia + ': ' + g.horas);
    const est = TH.horario.estado(completos);
    document.getElementById('horario-prev').textContent = 'Así se ve en la página: ' + lineas.join(' · ') +
      (est ? '. Ahora mismo muestra: "' + est.texto + '".' : '. Sin días abiertos no se muestra el cartel Abierto/Cerrado.');
  }

  function pintarSemana() {
    hsemana.innerHTML = '';
    semana.forEach(d => hsemana.appendChild(filaDia(d)));
    actualizarFilas();
    pintarPrevHorario();
  }

  hsemana.addEventListener('input', () => { actualizarFilas(); pintarPrevHorario(); controlador.marcarCambios(); });
  hsemana.addEventListener('change', () => { actualizarFilas(); pintarPrevHorario(); controlador.marcarCambios(); });

  document.getElementById('btn-copiar-lunes').addEventListener('click', () => {
    const s = leerSemana();
    const lunes = s[0];
    semana = s.map(d => (['mar', 'mie', 'jue', 'vie'].includes(d.dia) ? { dia: d.dia, turnos: lunes.turnos.map(t => Object.assign({}, t)) } : d));
    pintarSemana();
    controlador.marcarCambios();
  });

  function pintarFormulario(negocio) {
    document.getElementById('f-nombre').value = negocio.nombre || '';
    document.getElementById('f-whatsapp').value = negocio.whatsapp || '';
    document.getElementById('f-telefono').value = negocio.telefono || '';
    document.getElementById('f-email').value = negocio.email || '';
    document.getElementById('f-instagram').value = negocio.instagram || '';
    imagenHero = negocio.imagenHero || '';
    pintarHero();
    semana = TH.horario.normalizar(negocio.horariosSemana || []);
    pintarSemana();
  }

  document.getElementById('form-contacto').addEventListener('input', e => {
    if (e.target.tagName === 'INPUT') controlador.marcarCambios();
  });

  function leerFormulario() {
    return {
      nombre: document.getElementById('f-nombre').value.trim(),
      whatsapp: document.getElementById('f-whatsapp').value.trim(),
      telefono: document.getElementById('f-telefono').value.trim(),
      email: document.getElementById('f-email').value.trim(),
      instagram: document.getElementById('f-instagram').value.trim(),
      imagenHero,
      horariosSemana: leerSemana()
    };
  }

  const controlador = Panel.crearControladorGuardado({
    boton: document.getElementById('btn-guardar'),
    estado: document.getElementById('estado-guardado'),
    obtenerDatos: () => {
      datosCompletos.negocio = leerFormulario();
      return datosCompletos;
    },
    alGuardarOk: datos => { datosCompletos = datos; }
  });

  (async () => {
    try {
      datosCompletos = await controlador.cargar();
      pintarFormulario(datosCompletos.negocio || {});
    } catch (e) {
      Panel.avisar('No se pudo cargar la información. Recargá la página.');
    }
  })();
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

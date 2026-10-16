<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Contacto y horarios';
$paginaActiva = 'contacto';
require __DIR__ . '/_layout_top.php';
?>
<h1>Contacto y horarios</h1>
<p class="ayuda-pagina">Esto es lo que ve quien entra a la página: el WhatsApp, el mail, el Instagram y los horarios de atención.</p>

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
    <span class="label">Horarios de atención</span>
    <div class="hfilas" id="hfilas"></div>
    <button class="btn btn-ghost btn-sm" type="button" id="btn-agregar-horario">Agregar horario</button>
  </div>
</form>

<div class="panel-guardar">
  <span class="estado" id="estado-guardado"></span>
  <button class="btn btn-primary" type="button" id="btn-guardar">Guardar cambios</button>
</div>

<script>
(function () {
  const hfilas = document.getElementById('hfilas');
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

  function filaHorario(dia, horas) {
    const fila = document.createElement('div');
    fila.className = 'hfila';
    fila.innerHTML =
      '<div class="field"><label>Día</label><input type="text" data-campo="dia" placeholder="Ej: Lunes a viernes"></div>' +
      '<div class="field"><label>Horario</label><input type="text" data-campo="horas" placeholder="Ej: 9 a 13 y 16 a 20"></div>' +
      '<button type="button" class="btn btn-ghost btn-sm" aria-label="Quitar este horario">Quitar</button>';
    fila.querySelector('[data-campo="dia"]').value = dia || '';
    fila.querySelector('[data-campo="horas"]').value = horas || '';
    fila.querySelectorAll('input').forEach(i => i.addEventListener('input', () => controlador.marcarCambios()));
    fila.querySelector('button').addEventListener('click', () => { fila.remove(); controlador.marcarCambios(); });
    return fila;
  }

  document.getElementById('btn-agregar-horario').addEventListener('click', () => {
    hfilas.appendChild(filaHorario('', ''));
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
    hfilas.innerHTML = '';
    (negocio.horarios || []).forEach(h => hfilas.appendChild(filaHorario(h.dia, h.horas)));
  }

  document.getElementById('form-contacto').addEventListener('input', e => {
    if (e.target.tagName === 'INPUT') controlador.marcarCambios();
  });

  function leerFormulario() {
    const horarios = Array.from(hfilas.children).map(fila => ({
      dia: fila.querySelector('[data-campo="dia"]').value.trim(),
      horas: fila.querySelector('[data-campo="horas"]').value.trim()
    })).filter(h => h.dia && h.horas);
    return {
      nombre: document.getElementById('f-nombre').value.trim(),
      whatsapp: document.getElementById('f-whatsapp').value.trim(),
      telefono: document.getElementById('f-telefono').value.trim(),
      email: document.getElementById('f-email').value.trim(),
      instagram: document.getElementById('f-instagram').value.trim(),
      imagenHero,
      horarios
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

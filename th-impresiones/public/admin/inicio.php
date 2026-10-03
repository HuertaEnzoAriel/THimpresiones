<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Inicio';
$paginaActiva = 'inicio';
require __DIR__ . '/_layout_top.php';
?>
<h1>Hola, <?= h(th_usuario_actual()) ?></h1>
<p class="ayuda-pagina">Elegí qué querés actualizar. Los cambios no se publican hasta que apretás "Guardar cambios" en cada pantalla.</p>

<div class="panel-cards">
  <a class="panel-card" href="precios.php">
    <h2>Precios</h2>
    <p>Cambiar lo que sale cada trabajo, agregar o quitar precios por cantidad.</p>
  </a>
  <a class="panel-card" href="trabajos.php">
    <h2>Trabajos</h2>
    <p>Subir fotos nuevas, cambiar descripciones y mostrar u ocultar trabajos de la galería.</p>
  </a>
  <a class="panel-card" href="contacto.php">
    <h2>Contacto y horarios</h2>
    <p>WhatsApp, mail, Instagram y los horarios de atención.</p>
  </a>
  <a class="panel-card" href="backups.php">
    <h2>Copias de seguridad</h2>
    <p>Ver versiones anteriores de la página y volver a alguna si algo salió mal.</p>
  </a>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

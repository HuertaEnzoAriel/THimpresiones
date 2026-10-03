<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Copias de seguridad';
$paginaActiva = 'backups';
require __DIR__ . '/_layout_top.php';
?>
<h1>Copias de seguridad</h1>
<p class="ayuda-pagina">Cada vez que se guarda un cambio, queda guardada la versión anterior. Si algo salió mal, podés volver a cualquiera de estas.</p>

<div class="blista" id="lista-backups"></div>

<script>
(function () {
  const lista = document.getElementById('lista-backups');

  function fila(b) {
    const div = document.createElement('div');
    div.className = 'bitem';
    const info = document.createElement('div');
    const time = document.createElement('time');
    time.textContent = b.cuando;
    info.append(time);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-ghost btn-sm';
    btn.textContent = 'Volver a esta versión';
    btn.addEventListener('click', () => {
      Panel.confirmarEnLinea(btn, '¿Volver a la versión de "' + b.cuando + '"? Se va a guardar una copia de lo que hay ahora antes de reemplazarlo.', async () => {
        try {
          await Panel.apiFetch('api/backups_restaurar.php', { method: 'POST', body: JSON.stringify({ archivo: b.archivo }) });
          Panel.avisar('Listo, se restauró esa versión.');
          cargar();
        } catch (e) {
          Panel.avisar(e.message);
        }
      });
    });
    div.append(info, btn);
    return div;
  }

  async function cargar() {
    lista.innerHTML = '';
    try {
      const r = await Panel.apiFetch('api/backups_listar.php');
      if (!r.backups.length) {
        lista.innerHTML = '<p class="vacio">Todavía no hay copias de seguridad. Se crean automáticamente la primera vez que guardes un cambio.</p>';
        return;
      }
      r.backups.forEach(b => lista.appendChild(fila(b)));
    } catch (e) {
      lista.innerHTML = '<p class="vacio">No se pudo cargar la lista. Recargá la página.</p>';
    }
  }

  cargar();
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

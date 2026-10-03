<?php
require_once __DIR__ . '/../../private/bootstrap.php';
th_exigir_login();

$tituloPagina = 'Mi cuenta';
$paginaActiva = 'cuenta';
require __DIR__ . '/_layout_top.php';
?>
<h1>Mi cuenta</h1>
<p class="ayuda-pagina">Usuario: <strong><?= h(th_usuario_actual()) ?></strong></p>

<form class="panel-form" id="form-clave" style="max-width:420px" novalidate>
  <div class="field">
    <label for="c-actual">Contraseña actual</label>
    <input id="c-actual" type="password" autocomplete="current-password" required>
  </div>
  <div class="field">
    <label for="c-nueva">Contraseña nueva <span class="mono">(mínimo 8 caracteres)</span></label>
    <input id="c-nueva" type="password" autocomplete="new-password" required>
    <div class="medidor"><i id="medidor-barra"></i></div>
    <p class="medidor-txt" id="medidor-texto">Escribí una contraseña</p>
  </div>
  <div class="field">
    <label for="c-repetir">Repetí la contraseña nueva</label>
    <input id="c-repetir" type="password" autocomplete="new-password" required>
  </div>
  <button class="btn btn-primary" type="submit">Cambiar contraseña</button>
</form>

<script>
(function () {
  const nueva = document.getElementById('c-nueva');
  const barra = document.getElementById('medidor-barra');
  const texto = document.getElementById('medidor-texto');

  function medir(valor) {
    let puntos = 0;
    if (valor.length >= 8) puntos++;
    if (valor.length >= 12) puntos++;
    if (/[A-Z]/.test(valor)) puntos++;
    if (/[0-9]/.test(valor)) puntos++;
    if (/[^A-Za-z0-9]/.test(valor)) puntos++;
    const niveles = [
      { min: 0, ancho: '0%', color: '#C0392B', texto: 'Escribí una contraseña' },
      { min: 1, ancho: '25%', color: '#C0392B', texto: 'Floja: agregale largo o variedad' },
      { min: 2, ancho: '50%', color: '#E98346', texto: 'Más o menos' },
      { min: 3, ancho: '75%', color: '#EFBE41', texto: 'Buena' },
      { min: 4, ancho: '100%', color: '#2E7D32', texto: 'Muy buena' },
    ];
    const nivel = [...niveles].reverse().find(n => puntos >= n.min) || niveles[0];
    barra.style.width = nivel.ancho;
    barra.style.background = nivel.color;
    texto.textContent = valor ? nivel.texto : 'Escribí una contraseña';
  }
  nueva.addEventListener('input', () => medir(nueva.value));

  document.getElementById('form-clave').addEventListener('submit', async e => {
    e.preventDefault();
    const actual = document.getElementById('c-actual').value;
    const nva = nueva.value;
    const repetir = document.getElementById('c-repetir').value;
    if (nva !== repetir) { Panel.avisar('Las dos contraseñas nuevas no coinciden.'); return; }
    try {
      await Panel.apiFetch('api/cambiar_password.php', { method: 'POST', body: JSON.stringify({ actual, nueva: nva, confirmar: repetir }) });
      Panel.avisar('Listo, se cambió la contraseña.');
      document.getElementById('form-clave').reset();
      medir('');
    } catch (err) {
      Panel.avisar(err.message);
    }
  });
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php'; ?>

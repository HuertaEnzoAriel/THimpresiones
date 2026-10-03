<?php
require_once __DIR__ . '/../../private/bootstrap.php';

if (th_usuario_actual()) {
    header('Location: inicio.php');
    exit;
}

$error = null;
$segundosBloqueo = th_segundos_bloqueo_restantes();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $tokenEnviado = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $tokenEnviado)) {
        $error = 'No pudimos confirmar el formulario. Probá otra vez.';
    } elseif ($segundosBloqueo > 0) {
        $minutos = (int) ceil($segundosBloqueo / 60);
        $error = "Hubo demasiados intentos. Probá de nuevo en $minutos minuto" . ($minutos === 1 ? '' : 's') . ".";
    } else {
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $clave = (string) ($_POST['clave'] ?? '');
        $registro = $usuario !== '' ? th_buscar_usuario($usuario) : null;

        if ($registro && password_verify($clave, $registro['hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = $registro['usuario'];
            $_SESSION['ultima_actividad'] = time();
            th_limpiar_intentos();
            $siguiente = $_GET['next'] ?? 'inicio.php';
            header('Location: ' . ($siguiente !== '' ? $siguiente : 'inicio.php'));
            exit;
        }

        th_registrar_intento_fallido();
        $error = 'Usuario o contraseña incorrectos.';
        $segundosBloqueo = th_segundos_bloqueo_restantes();
        if ($segundosBloqueo > 0) {
            $minutos = (int) ceil($segundosBloqueo / 60);
            $error = "Demasiados intentos fallidos. Probá de nuevo en $minutos minuto" . ($minutos === 1 ? '' : 's') . ".";
        }
    }
}

$csrf = th_token_csrf();
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ingresar · Panel TH Impresiones</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Hanken+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="../css/tokens.css">
<link rel="stylesheet" href="../css/base.css">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="panel">
  <div class="login-wrap">
    <form class="login-card" method="post" novalidate>
      <h1>Panel TH Impresiones</h1>
      <?php if ($error): ?><p class="login-error"><?= h($error) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <div class="field">
        <label for="usuario">Usuario</label>
        <input id="usuario" name="usuario" type="text" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label for="clave">Contraseña</label>
        <input id="clave" name="clave" type="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn-primary" type="submit">Ingresar</button>
    </form>
  </div>
</body>
</html>

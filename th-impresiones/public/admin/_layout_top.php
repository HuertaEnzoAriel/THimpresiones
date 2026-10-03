<?php
/* Encabezado común de todas las páginas del panel (menos login.php). La
 * página que lo incluye debe definir antes $tituloPagina y $paginaActiva, y
 * ya haber llamado a th_exigir_login(). */
$usuarioActual = th_usuario_actual();
$csrf = th_token_csrf();
$limiteImagenBytes = th_limite_imagen_bytes();
$items_nav = [
    'inicio' => 'Inicio',
    'precios' => 'Precios',
    'trabajos' => 'Trabajos',
    'contacto' => 'Contacto y horarios',
    'backups' => 'Copias de seguridad',
    'cuenta' => 'Mi cuenta',
];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($tituloPagina) ?> · Panel TH Impresiones</title>
<meta name="csrf-token" content="<?= h($csrf) ?>">
<meta name="limite-imagen-bytes" content="<?= h((string) $limiteImagenBytes) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Hanken+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="../css/tokens.css">
<link rel="stylesheet" href="../css/base.css">
<link rel="stylesheet" href="assets/admin.css">
<script src="assets/admin.js"></script>
</head>
<body class="panel">
<div class="panel-shell">
  <header class="panel-top">
    <div class="panel-top-in">
      <span class="panel-brand">Panel · TH Impresiones</span>
      <div class="panel-top-act">
        <a class="btn btn-ghost btn-sm" href="../index.html" target="_blank" rel="noopener">Ver la página</a>
        <span class="panel-usuario"><?= h($usuarioActual) ?></span>
        <a class="btn btn-ghost btn-sm" href="logout.php">Salir</a>
      </div>
    </div>
  </header>
  <div class="panel-body">
    <nav class="panel-nav" aria-label="Secciones del panel">
      <?php foreach ($items_nav as $clave => $etiqueta): ?>
        <a href="<?= h($clave) ?>.php" class="<?= $paginaActiva === $clave ? 'activo' : '' ?>"><?= h($etiqueta) ?></a>
      <?php endforeach; ?>
    </nav>
    <main class="panel-main">

<?php
/* Script de línea de comandos para crear o resetear el usuario del panel.
   No existe ninguna página web para esto: se corre así, desde la terminal,
   parado en la carpeta del proyecto:

     php private/bin/crear_usuario.php

   Pide el nombre de usuario y la contraseña (dos veces) y los guarda en
   private/usuarios.json con password_hash(). Si el usuario ya existe, le
   cambia la contraseña. */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se puede ejecutar desde la línea de comandos.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../bootstrap.php';

function th_cli_leer(string $pregunta): string {
    echo $pregunta;
    $linea = fgets(STDIN);
    return trim((string) $linea);
}

function th_cli_leer_oculto(string $pregunta): string {
    echo $pregunta;
    $esWindows = stripos(PHP_OS, 'WIN') === 0;
    if ($esWindows) {
        // No hay forma simple de ocultar el tipeo en Windows sin extensiones
        // extra: se muestra en pantalla, pero no queda guardado en ningún lado.
        $linea = fgets(STDIN);
        return trim((string) $linea);
    }
    system('stty -echo');
    $linea = fgets(STDIN);
    system('stty echo');
    echo "\n";
    return trim((string) $linea);
}

echo "== Crear o resetear usuario del panel de TH Impresiones ==\n";
$usuario = th_cli_leer('Nombre de usuario: ');
if ($usuario === '') {
    fwrite(STDERR, "El nombre de usuario no puede estar vacío.\n");
    exit(1);
}

$clave1 = th_cli_leer_oculto('Contraseña (mínimo 8 caracteres): ');
if (strlen($clave1) < 8) {
    fwrite(STDERR, "La contraseña tiene que tener al menos 8 caracteres.\n");
    exit(1);
}
$clave2 = th_cli_leer_oculto('Repetí la contraseña: ');
if ($clave1 !== $clave2) {
    fwrite(STDERR, "Las contraseñas no coinciden.\n");
    exit(1);
}

$usuarios = th_leer_usuarios();
$hash = password_hash($clave1, PASSWORD_DEFAULT);
$encontrado = false;
foreach ($usuarios as &$u) {
    if (($u['usuario'] ?? '') === $usuario) {
        $u['hash'] = $hash;
        $encontrado = true;
        break;
    }
}
unset($u);
if (!$encontrado) {
    $usuarios[] = ['usuario' => $usuario, 'hash' => $hash, 'creadoEn' => date('c')];
}

th_guardar_usuarios($usuarios);

echo $encontrado
    ? "Listo: se actualizó la contraseña de \"$usuario\".\n"
    : "Listo: se creó el usuario \"$usuario\".\n";

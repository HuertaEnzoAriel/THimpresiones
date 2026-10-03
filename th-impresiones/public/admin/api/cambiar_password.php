<?php
/* Cambia la contraseña del usuario que tiene la sesión abierta. Pide la
 * contraseña actual para confirmar que es la misma persona. */
require_once __DIR__ . '/../../../private/bootstrap.php';

th_exigir_login_api();
th_exigir_post();
th_verificar_csrf();

$usuarioActual = th_usuario_actual();
$cuerpo = th_cuerpo_json();
$actual = (string) ($cuerpo['actual'] ?? '');
$nueva = (string) ($cuerpo['nueva'] ?? '');
$confirmar = (string) ($cuerpo['confirmar'] ?? '');

$registro = th_buscar_usuario($usuarioActual);
if (!$registro || !password_verify($actual, $registro['hash'])) {
    th_error_response('La contraseña actual no es correcta.', 400);
}
if (strlen($nueva) < 8) {
    th_error_response('La contraseña nueva tiene que tener al menos 8 caracteres.', 400);
}
if ($nueva !== $confirmar) {
    th_error_response('Las dos contraseñas nuevas no coinciden.', 400);
}

$usuarios = th_leer_usuarios();
foreach ($usuarios as &$u) {
    if (($u['usuario'] ?? '') === $usuarioActual) {
        $u['hash'] = password_hash($nueva, PASSWORD_DEFAULT);
        break;
    }
}
unset($u);
th_guardar_usuarios($usuarios);

th_json_response(['ok' => true]);

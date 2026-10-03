<?php
/* Restaura una copia de seguridad anterior como el datos.json actual. Antes
 * de reemplazar nada, guarda una copia de cómo estaba, para poder volver
 * atrás también de esto si hiciera falta. */
require_once __DIR__ . '/../../../private/bootstrap.php';
require_once __DIR__ . '/../../../private/validacion.php';

th_exigir_login_api();
th_exigir_post();
th_verificar_csrf();

$cuerpo = th_cuerpo_json();
$archivo = basename((string) ($cuerpo['archivo'] ?? ''));
if (!preg_match('/^datos-[0-9]{8}-[0-9]{6}-[0-9a-f]{6}\.json$/', $archivo)) {
    th_error_response('No encontramos esa copia de seguridad.', 400);
}
$ruta = TH_BACKUPS_DIR . '/' . $archivo;
if (!is_file($ruta)) {
    th_error_response('No encontramos esa copia de seguridad.', 404);
}

$contenido = file_get_contents($ruta);
$datos = json_decode((string) $contenido, true);
if (!is_array($datos)) {
    th_error_response('Esa copia de seguridad está dañada y no se puede usar.', 500, "JSON inválido en $archivo");
}

try {
    $datosValidados = th_validar_datos($datos);
} catch (Th_ErrorValidacion $e) {
    th_error_response('Esa copia de seguridad ya no es válida: ' . $e->getMessage(), 500);
}

$resultado = th_con_lock_datos(function () use ($datosValidados) {
    th_backup_datos();
    if (!th_escribir_datos_atomico($datosValidados)) {
        return ['error' => true];
    }
    $nuevo = th_leer_datos();
    return ['ok' => true, 'version' => $nuevo['version']];
});

if (!empty($resultado['error'])) {
    th_error_response('No se pudo restaurar. Probá de nuevo.', 500);
}

th_json_response(['ok' => true, 'version' => $resultado['version'], 'datos' => $datosValidados]);

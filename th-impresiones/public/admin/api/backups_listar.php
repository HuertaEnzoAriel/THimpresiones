<?php
/* Lista las copias de seguridad guardadas en private/backups/, más recientes
 * primero, con la fecha en lenguaje natural ("Ayer a las 18:40"). */
require_once __DIR__ . '/../../../private/bootstrap.php';

th_exigir_login_api();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    th_error_response('Pedido no válido.', 405);
}

$archivos = glob(TH_BACKUPS_DIR . '/datos-*.json') ?: [];
usort($archivos, fn($a, $b) => filemtime($b) - filemtime($a));

$lista = array_map(function ($ruta) {
    return [
        'archivo' => basename($ruta),
        'cuando' => th_fecha_humana(filemtime($ruta)),
        'marca' => filemtime($ruta),
    ];
}, $archivos);

th_json_response(['ok' => true, 'backups' => $lista]);

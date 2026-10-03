<?php
/* Devuelve el contenido actual de datos.json junto con un "token de versión"
 * (un hash del archivo). El panel lo guarda y lo manda de vuelta al guardar,
 * para que el servidor pueda detectar si alguien más guardó encima mientras
 * se editaba. */
require_once __DIR__ . '/../../../private/bootstrap.php';

th_exigir_login_api();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    th_error_response('Pedido no válido.', 405);
}

$r = th_leer_datos();
if ($r === null) {
    th_error_response('No se pudo leer la información guardada.', 500, 'datos.json no existe o está corrupto (admin/api/datos.php)');
}

th_json_response(['ok' => true, 'version' => $r['version'], 'datos' => $r['datos']]);

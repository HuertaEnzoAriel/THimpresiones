<?php
/* Guarda el datos.json completo que manda el panel. Antes de escribir:
 * 1) valida todo en el servidor (private/validacion.php),
 * 2) revisa que nadie haya guardado encima desde que el panel cargó esta
 *    pantalla (version), avisando en ese caso en vez de pisar nada,
 * 3) hace una copia de seguridad del archivo anterior,
 * 4) escribe con archivo temporal + rename (escritura atómica),
 * 5) borra del disco las fotos de uploads/ que ya no usa ningún trabajo. */
require_once __DIR__ . '/../../../private/bootstrap.php';
require_once __DIR__ . '/../../../private/validacion.php';

th_exigir_login_api();
th_exigir_post();
th_verificar_csrf();

$cuerpo = th_cuerpo_json();
$versionCliente = (string) ($cuerpo['version'] ?? '');
$datosCliente = is_array($cuerpo['datos'] ?? null) ? $cuerpo['datos'] : null;

if ($datosCliente === null) {
    th_error_response('No llegó nada para guardar.', 400);
}

try {
    $datosValidados = th_validar_datos($datosCliente);
} catch (Th_ErrorValidacion $e) {
    th_error_response($e->getMessage(), 422);
}

$resultado = th_con_lock_datos(function () use ($versionCliente, $datosValidados) {
    $actual = th_leer_datos();
    if ($actual !== null && $versionCliente !== '' && $actual['version'] !== $versionCliente) {
        return ['conflicto' => true];
    }

    $imagenesAntes = [];
    foreach ($actual['datos']['trabajos'] ?? [] as $t) {
        if (!empty($t['imagen'])) {
            $imagenesAntes[] = $t['imagen'];
        }
    }
    $imagenesDespues = array_map(fn($t) => $t['imagen'], $datosValidados['trabajos']);
    $huerfanas = array_diff($imagenesAntes, $imagenesDespues);

    th_backup_datos();
    if (!th_escribir_datos_atomico($datosValidados)) {
        return ['error' => true];
    }

    foreach ($huerfanas as $rutaRelativa) {
        // Las muestras de fábrica en img/muestras/ se conservan aunque un
        // trabajo deje de usarlas; solo se borra lo que subió el panel.
        if (str_starts_with($rutaRelativa, 'uploads/')) {
            $ruta = TH_PUBLIC_DIR . '/' . $rutaRelativa;
            if (is_file($ruta) && dirname($ruta) === TH_UPLOADS_DIR) {
                @unlink($ruta);
            }
        }
    }

    $nuevo = th_leer_datos();
    return ['ok' => true, 'version' => $nuevo['version']];
});

if (!empty($resultado['conflicto'])) {
    th_error_response('Alguien más guardó cambios mientras editabas esto. Recargá para ver lo último e intentá de nuevo.', 409);
}
if (!empty($resultado['error'])) {
    th_error_response('No se pudo guardar. Probá de nuevo en un momento.', 500, 'Fallo al escribir datos.json de forma atómica (admin/api/guardar.php)');
}

th_json_response(['ok' => true, 'version' => $resultado['version'], 'datos' => $datosValidados]);

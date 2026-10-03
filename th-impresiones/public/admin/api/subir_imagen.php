<?php
/* Recibe una foto para un trabajo de la galería. Nunca confía en el nombre
 * ni en la extensión del archivo: revisa el contenido real con finfo, y
 * además la vuelve a generar de cero con GD (la decodifica y la vuelve a
 * codificar), así cualquier cosa que no sea una imagen de verdad queda
 * descartada y no quedan metadatos ni código escondido en el archivo. El
 * nombre final lo elige el servidor, nunca el que sube la foto. */
require_once __DIR__ . '/../../../private/bootstrap.php';

th_exigir_login_api();
th_exigir_post();
th_verificar_csrf();

if (empty($_FILES['foto'])) {
    th_error_response('No llegó ninguna foto. Probá de nuevo.', 400);
}

$archivo = $_FILES['foto'];

$erroresSubida = [
    UPLOAD_ERR_INI_SIZE => 'La foto pesa demasiado para este servidor. Probá con una más liviana.',
    UPLOAD_ERR_FORM_SIZE => 'La foto pesa demasiado. Probá con una más liviana.',
    UPLOAD_ERR_PARTIAL => 'La foto se cortó al subirla. Probá de nuevo.',
    UPLOAD_ERR_NO_FILE => 'No llegó ninguna foto. Probá de nuevo.',
];
if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $mensaje = $erroresSubida[$archivo['error']] ?? 'No se pudo subir la foto. Probá de nuevo.';
    th_error_response($mensaje, 400, 'Código de error de subida: ' . $archivo['error']);
}

if (!is_uploaded_file($archivo['tmp_name'])) {
    th_error_response('No se pudo subir la foto. Probá de nuevo.', 400, 'tmp_name no es un archivo subido real');
}

$limiteBytes = th_limite_imagen_bytes();
if ($archivo['size'] > $limiteBytes) {
    $maxMb = round($limiteBytes / 1024 / 1024, 1);
    th_error_response("La foto pesa más de {$maxMb} MB. Probá con una más liviana.", 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($archivo['tmp_name']);
if (!in_array($mime, TH_TIPOS_IMAGEN_PERMITIDOS, true)) {
    th_error_response('Esa foto no es un JPG, PNG o WebP válido. Probá con otra.', 400, "Tipo real detectado: $mime");
}

function th_extension_para_mime(string $mime): string {
    return match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };
}

function th_funcion_gd_para_cargar(string $mime): ?string {
    return match ($mime) {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        default => null,
    };
}
function th_funcion_gd_para_guardar(string $mime): ?string {
    return match ($mime) {
        'image/jpeg' => 'imagejpeg',
        'image/png' => 'imagepng',
        'image/webp' => 'imagewebp',
        default => null,
    };
}

$funcionCarga = th_funcion_gd_para_cargar($mime);
$funcionGuardado = th_funcion_gd_para_guardar($mime);
if (!$funcionCarga || !$funcionGuardado || !function_exists($funcionCarga) || !function_exists($funcionGuardado)) {
    // Pasa si el PHP del servidor tiene la extensión GD compilada sin
    // soporte para ese formato (es común que falte WebP en instalaciones
    // viejas). Mejor avisar claro que dejar que explote más adelante.
    th_error_response('Este servidor no puede procesar fotos ' . strtoupper(th_extension_para_mime($mime)) . '. Probá con un JPG o un PNG.', 400, "GD sin soporte para $mime (falta $funcionCarga o $funcionGuardado)");
}

$imagenOriginal = @call_user_func($funcionCarga, $archivo['tmp_name']);
if (!$imagenOriginal) {
    th_error_response('No pudimos procesar esa foto. Probá con otra.', 400, 'GD no pudo decodificar el archivo subido');
}

$anchoOriginal = imagesx($imagenOriginal);
$altoOriginal = imagesy($imagenOriginal);

if ($anchoOriginal > TH_MAX_IMAGEN_ANCHO) {
    $nuevoAncho = TH_MAX_IMAGEN_ANCHO;
    $nuevoAlto = (int) round($altoOriginal * ($nuevoAncho / $anchoOriginal));
    $redimensionada = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($redimensionada, false);
        imagesavealpha($redimensionada, true);
    }
    imagecopyresampled($redimensionada, $imagenOriginal, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $anchoOriginal, $altoOriginal);
    $imagenFinal = $redimensionada;
} else {
    $imagenFinal = $imagenOriginal;
}

if (!is_dir(TH_UPLOADS_DIR)) {
    @mkdir(TH_UPLOADS_DIR, 0750, true);
}

$nombreArchivo = bin2hex(random_bytes(10)) . '.' . th_extension_para_mime($mime);
$rutaFinal = TH_UPLOADS_DIR . '/' . $nombreArchivo;

$calidad = $mime === 'image/png' ? 6 : 82;
$guardada = @call_user_func($funcionGuardado, $imagenFinal, $rutaFinal, $calidad);

if (!$guardada) {
    th_error_response('No pudimos guardar la foto procesada. Probá de nuevo.', 500, 'Fallo al escribir la imagen reprocesada en uploads/');
}

th_json_response(['ok' => true, 'archivo' => 'uploads/' . $nombreArchivo]);

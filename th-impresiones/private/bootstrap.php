<?php
/* Bootstrap: lo incluye cada página y cada endpoint del panel. Arranca la
   sesión seguro, maneja errores sin mostrarlos nunca al usuario, y junta los
   helpers de CSRF, lectura/escritura de datos.json, backups y bloqueo de
   intentos de ingreso. Nada de esto se usa desde la página pública. */

require_once __DIR__ . '/config.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', TH_LOG_FILE);
error_reporting(E_ALL);

function th_log(string $mensaje): void {
    if (!is_dir(TH_LOGS_DIR)) {
        @mkdir(TH_LOGS_DIR, 0750, true);
    }
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
    @file_put_contents(TH_LOG_FILE, $linea, FILE_APPEND | LOCK_EX);
}

set_exception_handler(function (Throwable $e): void {
    th_log('Excepción: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    $mensaje = 'Ocurrió un error inesperado. Ya quedó registrado; probá de nuevo en un momento.';
    // Si el pedido venía de una de las api/*.php del panel, devolvemos JSON:
    // el JavaScript del panel siempre espera poder leer la respuesta como
    // JSON, y si no puede, termina mostrando un mensaje todavía más genérico.
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $mensaje]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $mensaje;
    }
    exit;
});

/* ===== Texto seguro para imprimir en HTML ===== */
function h($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/* ===== Sesión ===== */
function th_es_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function th_iniciar_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(TH_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        // Secure solo puede ir en true cuando la conexión es HTTPS: los
        // navegadores descartan la cookie si se manda Secure sobre HTTP.
        // En producción, detrás de HTTPS (ver DEPLOY.md), esto da true solo.
        'secure' => th_es_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $ahora = time();
    if (!empty($_SESSION['usuario'])) {
        if (isset($_SESSION['ultima_actividad']) && ($ahora - $_SESSION['ultima_actividad']) > TH_INACTIVITY_SECONDS) {
            th_cerrar_sesion();
        } else {
            $_SESSION['ultima_actividad'] = $ahora;
        }
    }
}

function th_cerrar_sesion(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function th_usuario_actual(): ?string {
    return $_SESSION['usuario'] ?? null;
}

function th_exigir_login(): void {
    if (!th_usuario_actual()) {
        $siguiente = $_SERVER['REQUEST_URI'] ?? 'inicio.php';
        header('Location: login.php?next=' . urlencode($siguiente));
        exit;
    }
}

/* ===== CSRF ===== */
function th_token_csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function th_verificar_csrf(): void {
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    $esperado = $_SESSION['csrf'] ?? '';
    if ($esperado === '' || $enviado === '' || !hash_equals($esperado, $enviado)) {
        th_error_response('No pudimos confirmar tu sesión. Recargá la página e intentá de nuevo.', 403, 'CSRF inválido o ausente');
    }
}

/* ===== Respuestas JSON de las API ===== */
function th_json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function th_error_response(string $mensajeUsuario, int $code = 400, ?string $detalleTecnico = null): void {
    if ($detalleTecnico) {
        th_log($detalleTecnico);
    }
    th_json_response(['ok' => false, 'error' => $mensajeUsuario], $code);
}

function th_exigir_login_api(): void {
    if (!th_usuario_actual()) {
        th_error_response('Tu sesión venció. Volvé a ingresar.', 401);
    }
}

function th_exigir_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        th_error_response('Pedido no válido.', 405);
    }
}

function th_cuerpo_json(): array {
    $crudo = file_get_contents('php://input');
    $datos = json_decode($crudo, true);
    return is_array($datos) ? $datos : [];
}

/* ===== Lectura y escritura de datos.json ===== */
function th_leer_datos(): ?array {
    if (!file_exists(TH_DATA_FILE)) {
        return null;
    }
    $fp = fopen(TH_DATA_FILE, 'r');
    if (!$fp) {
        return null;
    }
    flock($fp, LOCK_SH);
    $contenido = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $datos = json_decode($contenido, true);
    if (!is_array($datos)) {
        return null;
    }
    return ['datos' => $datos, 'version' => sha1($contenido)];
}

function th_escribir_datos_atomico(array $datos): bool {
    $dir = dirname(TH_DATA_FILE);
    $tmp = $dir . '/.datos_' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    if (!rename($tmp, TH_DATA_FILE)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function th_backup_datos(): void {
    if (!file_exists(TH_DATA_FILE)) {
        return;
    }
    if (!is_dir(TH_BACKUPS_DIR)) {
        @mkdir(TH_BACKUPS_DIR, 0750, true);
    }
    $nombre = 'datos-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.json';
    @copy(TH_DATA_FILE, TH_BACKUPS_DIR . '/' . $nombre);
    th_rotar_backups();
}

function th_rotar_backups(): void {
    $archivos = glob(TH_BACKUPS_DIR . '/datos-*.json');
    if (!$archivos) {
        return;
    }
    usort($archivos, fn($a, $b) => filemtime($b) - filemtime($a));
    foreach (array_slice($archivos, TH_MAX_BACKUPS) as $viejo) {
        @unlink($viejo);
    }
}

/* Serializa todo el flujo "leer versión actual -> comparar -> backup -> escribir"
   para que dos guardados a la vez no se pisen entre sí. */
function th_con_lock_datos(callable $fn) {
    if (!is_dir(TH_PRIVATE_DIR)) {
        @mkdir(TH_PRIVATE_DIR, 0750, true);
    }
    $lockPath = TH_PRIVATE_DIR . '/.datos.lock';
    $fp = fopen($lockPath, 'c');
    if (!$fp) {
        throw new RuntimeException('No se pudo abrir el archivo de bloqueo de datos.json');
    }
    flock($fp, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/* ===== Usuarios ===== */
function th_leer_usuarios(): array {
    if (!file_exists(TH_USERS_FILE)) {
        return [];
    }
    $datos = json_decode((string) file_get_contents(TH_USERS_FILE), true);
    return is_array($datos['usuarios'] ?? null) ? $datos['usuarios'] : [];
}

function th_guardar_usuarios(array $usuarios): void {
    $dir = dirname(TH_USERS_FILE);
    $tmp = $dir . '/.usuarios_' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode(['usuarios' => $usuarios], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    rename($tmp, TH_USERS_FILE);
}

function th_buscar_usuario(string $nombre): ?array {
    foreach (th_leer_usuarios() as $u) {
        if (($u['usuario'] ?? '') === $nombre) {
            return $u;
        }
    }
    return null;
}

/* ===== Intentos de ingreso fallidos (bloqueo por IP) ===== */
function th_ip_cliente(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function th_leer_intentos(): array {
    if (!file_exists(TH_ATTEMPTS_FILE)) {
        return [];
    }
    $fp = fopen(TH_ATTEMPTS_FILE, 'r');
    if (!$fp) {
        return [];
    }
    flock($fp, LOCK_SH);
    $contenido = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $datos = json_decode($contenido, true);
    return is_array($datos) ? $datos : [];
}

function th_guardar_intentos(array $datos): void {
    $dir = dirname(TH_ATTEMPTS_FILE);
    $tmp = $dir . '/.intentos_' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode($datos), LOCK_EX);
    rename($tmp, TH_ATTEMPTS_FILE);
}

function th_segundos_bloqueo_restantes(): int {
    $ip = th_ip_cliente();
    $intentos = th_leer_intentos();
    $hasta = $intentos[$ip]['bloqueadoHasta'] ?? 0;
    return $hasta > time() ? ($hasta - time()) : 0;
}

function th_registrar_intento_fallido(): void {
    $lockPath = TH_PRIVATE_DIR . '/.intentos.lock';
    $fp = fopen($lockPath, 'c');
    flock($fp, LOCK_EX);
    $intentos = th_leer_intentos();
    $ip = th_ip_cliente();
    $ahora = time();
    $actual = $intentos[$ip] ?? ['fallos' => 0, 'bloqueadoHasta' => 0, 'ultimoFallo' => 0];
    if (($actual['bloqueadoHasta'] ?? 0) < $ahora && ($ahora - ($actual['ultimoFallo'] ?? 0)) > TH_BLOQUEO_SEGUNDOS) {
        $actual = ['fallos' => 0, 'bloqueadoHasta' => 0, 'ultimoFallo' => 0];
    }
    $actual['fallos'] = ($actual['fallos'] ?? 0) + 1;
    $actual['ultimoFallo'] = $ahora;
    if ($actual['fallos'] >= TH_MAX_INTENTOS) {
        $actual['bloqueadoHasta'] = $ahora + TH_BLOQUEO_SEGUNDOS;
    }
    $intentos[$ip] = $actual;
    th_guardar_intentos($intentos);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function th_limpiar_intentos(): void {
    $lockPath = TH_PRIVATE_DIR . '/.intentos.lock';
    $fp = fopen($lockPath, 'c');
    flock($fp, LOCK_EX);
    $intentos = th_leer_intentos();
    unset($intentos[th_ip_cliente()]);
    th_guardar_intentos($intentos);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/* ===== Fecha en lenguaje natural para la lista de copias de seguridad ===== */
function th_fecha_humana(int $marca): string {
    $ahora = time();
    $diff = $ahora - $marca;
    $hora = date('H:i', $marca);
    if ($diff < 60) {
        return 'Hace un momento';
    }
    if ($diff < 3600) {
        $m = intdiv($diff, 60);
        return 'Hace ' . $m . ' minuto' . ($m === 1 ? '' : 's');
    }
    $hoy = date('Y-m-d', $ahora);
    $ayer = date('Y-m-d', $ahora - 86400);
    $fechaMarca = date('Y-m-d', $marca);
    if ($fechaMarca === $hoy) {
        return "Hoy a las $hora";
    }
    if ($fechaMarca === $ayer) {
        return "Ayer a las $hora";
    }
    return date('d/m/Y', $marca) . " a las $hora";
}

th_iniciar_sesion();

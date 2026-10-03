<?php
/* Config: rutas y parámetros del panel. No hace nada por sí solo — lo incluye
   bootstrap.php. Cambiar estos valores no requiere tocar ningún otro archivo. */

// Carpetas (calculadas desde la ubicación de este archivo, así funcionan igual
// en Windows, en Linux y sin importar dónde se instale el proyecto).
define('TH_PRIVATE_DIR', __DIR__);
define('TH_BASE_DIR', dirname(__DIR__));
define('TH_PUBLIC_DIR', TH_BASE_DIR . '/public');

define('TH_DATA_FILE', TH_PUBLIC_DIR . '/data/datos.json');
define('TH_UPLOADS_DIR', TH_PUBLIC_DIR . '/uploads');
define('TH_BACKUPS_DIR', TH_PRIVATE_DIR . '/backups');
define('TH_LOGS_DIR', TH_PRIVATE_DIR . '/logs');
define('TH_LOG_FILE', TH_LOGS_DIR . '/errores.log');
define('TH_USERS_FILE', TH_PRIVATE_DIR . '/usuarios.json');
define('TH_ATTEMPTS_FILE', TH_PRIVATE_DIR . '/intentos.json');

// Sesión
define('TH_SESSION_NAME', 'th_admin_sesion');
define('TH_INACTIVITY_SECONDS', 30 * 60);     // cierre automático por inactividad

// Ingreso
define('TH_MAX_INTENTOS', 5);
define('TH_BLOQUEO_SEGUNDOS', 15 * 60);

// Imágenes de trabajos
define('TH_MAX_IMAGEN_BYTES', 5 * 1024 * 1024);
define('TH_MAX_IMAGEN_ANCHO', 1600);
define('TH_TIPOS_IMAGEN_PERMITIDOS', ['image/jpeg', 'image/png', 'image/webp']);

// Copias de seguridad
define('TH_MAX_BACKUPS', 30);

// Límites de texto para la validación del servidor
define('TH_MAX_TEXTO_CORTO', 120);
define('TH_MAX_TEXTO_LARGO', 500);

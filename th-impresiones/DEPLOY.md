# Cómo instalar esto en el VPS

Esta guía asume un VPS con **Ubuntu o Debian**, acceso por SSH como un usuario con `sudo`, y que todavía no tenés nada instalado. Cada paso tiene el comando exacto y qué deberías ver. Si un paso falla, no sigas con el siguiente: resolvé ese primero.

En toda la guía, reemplazá:
- `tudominio.com` por tu dominio real.
- `/var/www/th-impresiones` por donde quieras instalar el proyecto (podés usar ese mismo path, es solo un ejemplo).

## 1. Paquetes necesarios

Conectate por SSH y actualizá el sistema:

```bash
sudo apt update && sudo apt upgrade -y
```

### Opción A: Nginx + PHP-FPM (recomendada)

```bash
sudo apt install -y nginx php-fpm php-gd php-mbstring php-xml php-cli unzip git
```

### Opción B: Apache + PHP

```bash
sudo apt install -y apache2 libapache2-mod-php php-gd php-mbstring php-xml php-cli unzip git
sudo a2enmod rewrite
```

Extensiones de PHP que usa el proyecto (ya vienen con los paquetes de arriba): `gd` (procesar fotos), `fileinfo` (ya viene con PHP core en casi todas las distros; si no, `sudo apt install -y php-fileinfo`), `json` y `session` (vienen siempre con PHP).

Verificá que están activas:

```bash
php -m | grep -iE "gd|fileinfo|json|session"
```

Tenés que ver las cuatro líneas. Si falta alguna, instalala (ej: `sudo apt install -y php-gd`) y después `sudo systemctl restart php*-fpm` (o `apache2`).

## 2. Subir el proyecto

Si lo tenés en un repositorio Git:

```bash
sudo mkdir -p /var/www/th-impresiones
sudo chown $USER:$USER /var/www/th-impresiones
git clone TU_REPOSITORIO.git /var/www/th-impresiones
```

Si lo subís por SFTP/SCP en vez de Git, asegurate de copiar **toda** la carpeta `th-impresiones/` (con `public/` y `private/` adentro) a `/var/www/th-impresiones`.

## 3. Carpetas y permisos

La idea: el usuario del servidor web (`www-data` en Ubuntu/Debian, tanto para Nginx+PHP-FPM como para Apache) solo necesita poder **escribir** en tres carpetas. Todo el resto del código puede quedar de solo lectura para él.

```bash
cd /var/www/th-impresiones

# Dueño de todo el proyecto: tu usuario (para poder actualizar el código con git pull)
sudo chown -R $USER:$USER /var/www/th-impresiones

# El servidor web necesita escribir en estas tres:
sudo chgrp -R www-data public/data public/uploads private
sudo chmod -R 750 public/data public/uploads private
sudo find public/data public/uploads private -type f -exec chmod 640 {} \;
sudo find public/data public/uploads private -type d -exec chmod 750 {} \;

# Y tu usuario necesita poder escribir ahí también para los próximos pasos
sudo usermod -a -G www-data $USER
```

Después de `usermod`, cerrá la sesión SSH y volvé a entrar para que el cambio de grupo tome efecto.

Confirmá que `datos.json` existe:

```bash
ls -la public/data/datos.json
```

Si no existe (por ejemplo, instalación nueva desde cero sin el archivo inicial), copiá el que viene en el repositorio o creá uno vacío con esta estructura mínima:

```bash
cat > public/data/datos.json << 'EOF'
{"negocio":{"nombre":"","whatsapp":"","telefono":"","email":"","instagram":"","horarios":[]},"categoriasPrecios":[],"precios":[],"categoriasTrabajos":[],"trabajos":[]}
EOF
chmod 640 public/data/datos.json
```

## 4. Configuración del servidor web

### Opción A: Nginx + PHP-FPM

Creá `/etc/nginx/sites-available/th-impresiones`:

```nginx
server {
    listen 80;
    server_name tudominio.com www.tudominio.com;

    root /var/www/th-impresiones/public;
    index index.html;

    # Bloquea cualquier .php dentro de uploads/, aunque alguna vez
    # terminara ahí un archivo con esa extensión.
    location ^~ /uploads/ {
        location ~ \.php$ { return 403; }
    }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        # Ajustá la versión de PHP si es distinta (php -v para verla)
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

Nota: `private/` no está dentro de `root`, así que Nginx nunca puede servirlo bajo ninguna URL — no hace falta ninguna regla extra para bloquearlo.

Activá el sitio y probá la configuración:

```bash
sudo ln -s /etc/nginx/sites-available/th-impresiones /etc/nginx/sites-enabled/
sudo nginx -t
```

Tenés que ver `syntax is ok` y `test is successful`. Si el `fastcgi_pass` no coincide con tu versión de PHP, este paso te va a avisar. Buscá el socket correcto con:

```bash
ls /run/php/
```

Reiniciá Nginx:

```bash
sudo systemctl reload nginx
```

### Opción B: Apache

Creá `/etc/apache2/sites-available/th-impresiones.conf`:

```apache
<VirtualHost *:80>
    ServerName tudominio.com
    ServerAlias www.tudominio.com
    DocumentRoot /var/www/th-impresiones/public

    <Directory /var/www/th-impresiones/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Redundante con el .htaccess de uploads/, pero por si AllowOverride
    # estuviera desactivado en algún lado.
    <Directory /var/www/th-impresiones/public/uploads>
        <FilesMatch "\.php$">
            Require all denied
        </FilesMatch>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/th-impresiones-error.log
    CustomLog ${APACHE_LOG_DIR}/th-impresiones-access.log combined
</VirtualHost>
```

Igual que con Nginx: `private/` queda fuera de `DocumentRoot`, así que Apache no puede servirlo aunque alguien adivine la URL.

```bash
sudo a2ensite th-impresiones.conf
sudo apache2ctl configtest
```

Tenés que ver `Syntax OK`. Reiniciá Apache:

```bash
sudo systemctl reload apache2
```

## 5. Probar sin HTTPS todavía

Antes de meter HTTPS, confirmá que la página pública carga:

```bash
curl -I http://tudominio.com/
```

Tiene que responder `200 OK`. Si responde error 500, revisá los permisos del paso 3, o mirá el log: `private/logs/errores.log` (los errores técnicos nunca se muestran al visitante, siempre quedan ahí).

## 6. HTTPS con certbot

```bash
sudo apt install -y certbot
```

Para Nginx:
```bash
sudo apt install -y python3-certbot-nginx
sudo certbot --nginx -d tudominio.com -d www.tudominio.com
```

Para Apache:
```bash
sudo apt install -y python3-certbot-apache
sudo certbot --apache -d tudominio.com -d www.tudominio.com
```

Certbot te va a preguntar un mail (para avisos de renovación) y si querés redirigir todo el tráfico HTTP a HTTPS — decile que sí. Al final vas a ver `Congratulations!` y la ruta del certificado.

El panel usa cookies de sesión marcadas como "Secure", que **solo funcionan sobre HTTPS**. Hasta que no hagas este paso, podés entrar a la página pública sin problema, pero el panel (`/admin/`) no va a poder iniciar sesión correctamente por HTTP — es intencional, así nunca viaja la sesión sin cifrar.

Probá:
```bash
curl -I https://tudominio.com/
```

Certbot renueva solo; confirmalo con:
```bash
sudo certbot renew --dry-run
```

## 7. Crear el primer usuario del panel

Esto se hace una sola vez, desde la terminal (nunca hay una página web para esto):

```bash
cd /var/www/th-impresiones
php private/bin/crear_usuario.php
```

Te va a pedir un nombre de usuario y una contraseña (dos veces). Al final vas a ver `Listo: se creó el usuario "...".`.

Si alguna vez te olvidás la contraseña o necesitás crear otro usuario, corré el mismo comando de nuevo — si el usuario ya existe, le cambia la contraseña.

## 8. Entrar al panel

Abrí `https://tudominio.com/admin/` en el navegador, ingresá con el usuario que creaste, y deberías ver la pantalla de Inicio con las 4 tarjetas (Precios, Trabajos, Contacto y horarios, Copias de seguridad).

## 9. Backups

### Backup automático de los datos

El panel ya hace esto solo: cada vez que se guarda un cambio desde el panel, la versión anterior de `public/data/datos.json` queda copiada en `private/backups/`, y se conservan las últimas 30. Desde el panel (pantalla "Copias de seguridad") se puede volver a cualquiera con un botón.

### Backup completo del proyecto (recomendado, aparte, periódico)

Esto es un respaldo manual de todo — por si el servidor completo tuviera un problema. Incluye el código, los datos, las fotos subidas y los usuarios del panel.

```bash
cd /var/www
sudo tar -czf th-impresiones-backup-$(date +%Y%m%d).tar.gz \
  --exclude='th-impresiones/private/logs/*.log' \
  th-impresiones
```

Movés ese `.tar.gz` a otro lugar (tu computadora, otro servidor, un servicio de almacenamiento) — un backup que vive en el mismo servidor no te salva si el servidor entero falla. Podés automatizar este comando con `cron` si querés que se repita solo, por ejemplo una vez por semana.

### Restaurar un backup completo

```bash
cd /var/www
sudo tar -xzf th-impresiones-backup-FECHA.tar.gz
```

Esto reemplaza la carpeta `th-impresiones/` entera por la del backup. Después, repetí el paso 3 (permisos) porque `tar` no siempre conserva el dueño/grupo exactos:

```bash
cd /var/www/th-impresiones
sudo chown -R $USER:$USER /var/www/th-impresiones
sudo chgrp -R www-data public/data public/uploads private
sudo chmod -R 750 public/data public/uploads private
```

## 10. Actualizar el código más adelante

Cuando haya cambios de código (no de contenido — el contenido lo edita el panel):

```bash
cd /var/www/th-impresiones
git pull
```

Los cambios de contenido (precios, fotos, textos) viven en `public/data/datos.json` y `public/uploads/`, que `git pull` no toca. No hace falta reiniciar nada: PHP y el estático se sirven de nuevo en cada pedido.

## Resumen de permisos (para volver a revisar si algo falla)

| Carpeta | Quién escribe | Por qué |
|---|---|---|
| `public/data/` | servidor web (`www-data`) | ahí vive `datos.json`, que el panel reescribe al guardar |
| `public/uploads/` | servidor web (`www-data`) | las fotos que sube el panel |
| `private/` | servidor web (`www-data`) | usuarios, intentos de ingreso, backups y el log de errores |
| todo lo demás (código) | tu usuario, de solo lectura para `www-data` | el programador lo actualiza con `git pull`; el servidor web solo lo lee |

## Problemas comunes

- **"Ocurrió un error inesperado" en el panel**: mirá `private/logs/errores.log` — ahí está el detalle técnico real (nunca se muestra al usuario).
- **No puedo iniciar sesión en el panel, pero la contraseña es correcta**: revisá que estés entrando por `https://` (no `http://`) — la cookie de sesión no se guarda sin HTTPS, a propósito.
- **"No se pudo guardar"**: casi siempre son permisos. Confirmá que `public/data/datos.json` es escribible por `www-data` (paso 3).
- **Subir una foto falla siempre**: revisá que la extensión `gd` de PHP esté instalada (`php -m | grep gd`) y que `public/uploads/` tenga permiso de escritura para `www-data`.

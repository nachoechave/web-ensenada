# Etapa 8C — Cuenta de ejecución única

## Conexión y seguridad

La API de Laravel usa la conexión principal tanto para las rutas públicas como para las administrativas de Noticias. El perfil privado de iWinds mantiene `APP_ENV=staging`, `APP_DEBUG=false` y `APP_URL=https://prueba.ensenada.gov.ar`. En su `.env` existente comprobar `DB_CONNECTION=mysql`, `DB_HOST=localhost`, `DB_PORT=3306`, `DB_DATABASE=ensenada_web`, `DB_USERNAME=ensenada_web_app` y `DB_COLLATION=utf8mb4_unicode_520_ci`. La contraseña existente permanece en `.env`. Las variables `NEWS_DB_USERNAME` y `NEWS_DB_PASSWORD` quedaron sin uso y se pueden retirar al cerrar la actualización. SQLite conserva el archivo local de Noticias de solo lectura para sus pruebas.

Usar la misma cuenta **elimina el aislamiento de lectura a nivel de cuenta MySQL**. Una vulnerabilidad que permita ejecutar SQL arbitrario fuera del recorrido público o un compromiso del proceso de Laravel puede alcanzar el DML otorgado a `ensenada_web_app` y leer tablas que necesita la aplicación. Las medidas compensatorias son las transacciones `READ ONLY` por solicitud pública, el filtro exacto y parametrizado `PUBLICADA`, la proyección explícita del DTO público y los permisos mínimos por tabla. La transacción impide escrituras durante esa lectura, pero sus `SELECT` siguen sujetos a los permisos de la cuenta compartida. Las rutas administrativas conservan sesión, rol, CSRF y validación. `.env`, el caché de configuración y la base permanecen fuera del document root. [MariaDB, SET TRANSACTION](https://mariadb.com/docs/server/reference/sql-statements/transactions/set-transaction).

## Permisos y definidor del trigger

Mantener el marcador `.iwinds-api-enabled` ausente. Las cuentas ya existentes son `ensenada_web_app` para ejecución y `ensenada_web_schema` para administración y como definidor de `revoke_auth_sessions`. Conservar la identidad y los permisos efectivos del definidor. Comprobar `SHOW CREATE TRIGGER ensenada_web.revoke_auth_sessions` con el administrador. El trigger lee las columnas que compara, puede asignar `NEW.token_version` y elimina sesiones del usuario. El administrador debe comprobar sus permisos sobre `usuarios` y `auth_sessions` y el permiso `TRIGGER`. No aplicar el retiro de permisos a la cuenta del esquema.

Antes de autorizar cualquier `GRANT` o `REVOKE`, ejecutar `SELECT CURRENT_USER(), CURRENT_ROLE();` y `SHOW GRANTS;` en la sesión que aplicaría los cambios. Confirmar que el otorgante posee **cada privilegio que delegará y `GRANT OPTION`** en el alcance correspondiente. Para revocar, verificar la capacidad de revocar los privilegios presentes en ese nivel. Confirmar la identidad y host de la cuenta Laravel mediante su propia conexión. Si `ensenada_web_schema` carece de esa delegación, el administrador del hosting debe aplicar los permisos por tabla y retirar los amplios mediante cPanel o una cuenta autorizada de MariaDB. Si cPanel solo ofrece permisos a nivel de base, pedir al proveedor la operación por tabla. Guardar la salida de `SHOW GRANTS` en privado, pues MariaDB puede incluir material de autenticación. [MariaDB, GRANT](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant), [MariaDB, REVOKE](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/revoke), [MariaDB, CREATE TRIGGER](https://mariadb.com/docs/server/server-usage/triggers-events/triggers/create-trigger).

1. Registrar los grants previos de ambas cuentas y el definidor. Revisar el host `@localhost` del ejemplo `schema-grants.sql` contra la identidad real de `ensenada_web_app`. Aplicar la propuesta por tabla con el otorgante autorizado.
2. Revisar que aparezcan todos los grants por tabla antes de quitar el grant amplio. Si se confirmó `ALL PRIVILEGES ON ensenada_web.*` para la identidad exacta que se muestra abajo, retirar solo ese alcance. Si la revisión detecta delegación, roles o permisos globales adicionales, el DBA debe resolverlos en su nivel. La cuenta de ejecución queda sin DDL, administración, delegación ni permisos sobre otros esquemas.

```sql
REVOKE ALL PRIVILEGES ON `ensenada_web`.* FROM 'ensenada_web_app'@'localhost';
SHOW GRANTS FOR 'ensenada_web_app'@'localhost';
```

3. Ejecutar `schema-check.php` con la identidad Laravel después de la actualización. El comprobador valida las 12 tablas, collation, controles de integridad y marca, y exige que los grants coincidan con la plantilla. Rechaza grants amplios, delegación, roles adicionales y permisos ausentes. La validación del definidor se realiza aparte con el administrador. Si falla cualquiera de las comprobaciones, mantener la API cerrada.

## Actualización incremental en cPanel

El paquete contiene archivos privados puntuales, `FILES` y `SHA256SUMS`. Su contenido corresponde a la rama de migración. El SQL de grants es una propuesta administrativa, sin ejecución durante la copia de archivos. Subir el ZIP y su `.sha256` a una carpeta privada de `/home/ensenada` y verificar antes de extraer. El siguiente ejemplo usa una carpeta nueva llamada `web-ensenada-8c-update`. Ejecutarlo en una ventana autorizada con el usuario propietario de la instalación.

```sh
cd /home/ensenada
umask 077
sha256sum -c web-ensenada-iwinds-stage8c.zip.sha256 || exit 1
mkdir web-ensenada-8c-update || exit 1
unzip -q web-ensenada-iwinds-stage8c.zip -d web-ensenada-8c-update || exit 1
cd web-ensenada-8c-update || exit 1
sha256sum -c SHA256SUMS || exit 1
APP=/home/ensenada/backend-laravel
test "$(realpath "$APP")" = "$APP" && test ! -e "$APP/.iwinds-api-enabled" && test ! -L "$APP/.iwinds-api-enabled" || exit 1
BACKUP="/home/ensenada/backend-laravel-pre-8c-$(date +%Y%m%d-%H%M%S)"
mkdir -m 700 "$BACKUP" || exit 1
: > "$BACKUP/ABSENT"
cp FILES "$BACKUP/FILES" || exit 1
while IFS= read -r rel; do
    parent="$APP/$(dirname "$rel")"
    test "$(realpath "$parent")" = "$parent" || exit 1
    test ! -L "$APP/$rel" && test ! -e "$APP/$rel.stage8c" || exit 1
    if test -f "$APP/$rel"; then
        mkdir -p "$BACKUP/$(dirname "$rel")" || exit 1
        cp -p "$APP/$rel" "$BACKUP/$rel" || exit 1
    else
        test ! -e "$APP/$rel" || exit 1
        printf '%s\n' "$rel" >> "$BACKUP/ABSENT"
    fi
done < FILES
printf 'Respaldo privado: %s\n' "$BACKUP"
while IFS= read -r rel; do
    test -d "$APP/$(dirname "$rel")" || exit 1
    install -m 600 "payload/$rel" "$APP/$rel.stage8c" || exit 1
    mv "$APP/$rel.stage8c" "$APP/$rel" || exit 1
    cmp "payload/$rel" "$APP/$rel" || exit 1
done < FILES
```

Revisar que PHP efectivo pueda leer los archivos privados con los permisos indicados. Si el hosting usa otro grupo para PHP, confirmar el propietario y el grupo con el administrador y limitar la lectura a ese grupo. Conservar el `.env` actual y revisar la configuración MariaDB indicada arriba. Limpiar cachés después de copiar los archivos para que el proveedor anterior no siga exigiendo la cuenta lectora. Ejecutar, con el marcador todavía ausente:

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" artisan config:clear && "$PHP83" artisan route:clear || exit 1
"$PHP83" deploy/iwinds/platform.php && "$PHP83" deploy/iwinds/schema-check.php || exit 1
"$PHP83" artisan config:cache && "$PHP83" artisan route:cache || exit 1
"$PHP83" artisan route:list --path=api/noticias && "$PHP83" deploy/iwinds/schema-check.php
```

Mantener `bootstrap/cache` privado. La actualización no toca el esquema, el trigger, el sitio publicado, `.htaccess`, `vendor`, `.env` ni los archivos históricos. El verificador es de solo lectura. Las operaciones de grants son un paso separado y autorizado.

## Reversión

Mantener la API cerrada y el `.htaccess` actual. Limpiar `config:clear` y `route:clear` mientras la versión 8C todavía está instalada. Si Artisan queda bloqueado por configuración, apartar únicamente sus archivos de caché generados a la carpeta privada de respaldo antes de restaurar código. Luego restituir las rutas de `FILES` desde el respaldo y apartar las rutas registradas en `ABSENT`:

```sh
APP=/home/ensenada/backend-laravel
BACKUP=/home/ensenada/backend-laravel-pre-8c-FECHA-HORA
test -f "$BACKUP/FILES" && test ! -e "$APP/.iwinds-api-enabled" && test ! -L "$APP/.iwinds-api-enabled" || exit 1
while IFS= read -r rel; do
    if test -f "$BACKUP/$rel"; then
        cp -p "$BACKUP/$rel" "$APP/$rel.stage8c-rollback" || exit 1
        mv "$APP/$rel.stage8c-rollback" "$APP/$rel" || exit 1
    elif grep -Fxq "$rel" "$BACKUP/ABSENT" && test -f "$APP/$rel"; then
        mkdir -p "$BACKUP/retirados/$(dirname "$rel")" || exit 1
        mv "$APP/$rel" "$BACKUP/retirados/$rel" || exit 1
    fi
done < "$BACKUP/FILES"
```

Si la versión anterior exigía un lector inexistente, volverá a bloquear su arranque. La reversión recupera los archivos anteriores con el sitio público intacto. Conservar los grants mínimos salvo decisión separada del DBA basada en el registro previo. Mantener el trigger y su definidor. Antes de habilitar Laravel quedan la verificación real de permisos y definidor, el aprovisionamiento y la restauración autorizados, y las pruebas en el subdominio real.

## Evidencia local

| Entorno | Resultado |
| --- | --- |
| MariaDB 10.11.19, sin cuenta lectora | 78 pruebas HTTP y 4 de esquema, permisos y restauración. Aprobó `schema-check.php` con los grants por tabla. |
| Transacción pública | MariaDB rechazó el intento de escritura durante la transacción de solo lectura. La conexión conservó capacidad de escritura para operaciones autorizadas posteriores. |
| Angular y Chromium con HTTPS | 38 pruebas. Incluye Noticias públicas y administración, autenticación, Hacienda y Proveedores. |
| SQLite | 97 pruebas de aplicación y 19 HTTP focalizadas. Conserva su frontera de archivo de solo lectura. |
| Formato | Pint aprobó 107 archivos. |

Registros en `output/stage8c/` y Playwright en `output/angular/stage8c/playwright.json`. Las pruebas usan los Compose de 8B y `AUTH_STAGE8_RUNTIME_PASSWORD` sintética. Las verificaciones del paquete privado usan el ZIP de instalación anterior en un contenedor sin red.

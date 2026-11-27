# Aprovisionamiento inicial privado de Web Ensenada

El incremento agrega dos operaciones CLI independientes para staging en iWinds y un estado de solo lectura. Usa la instalación privada existente, PHP 8.3, MariaDB 10.11 y la identidad ya configurada `ensenada_web_app` con el perfil `cpanel-dml`. La base debe ser `ensenada_web`, con `utf8mb4_unicode_520_ci`, las 12 tablas InnoDB y la marca `schema-ready`. Durante todo el procedimiento el marcador `.iwinds-api-enabled` debe estar ausente. Mantener detenidos los demás escritores de esa base durante el aprovisionamiento.

## Contenido institucional

Laravel devuelve en `GET /api/site-content` el objeto JSON guardado en `portal_site_content.contenido_json` con `id=1`. Su ausencia produce `404`. `GET` y `PUT /api/admin/site-content` requieren `SUPER_ADMIN`. `PUT` actualiza el singleton existente. La herramienta reutiliza `SiteContentDocument`, que valida los tipos conocidos y las URLs, y aplica el máximo de 65.535 bytes de `TEXT`. Guarda `updated_at` en UTC.

Angular consume `topbar`, `navbar`, `hero`, `accesosTitulo`, `accesos`, `tramites`, `noticias`, `areas`, `intendencia`, `agenda` y `footer`. El servicio mezcla el contenido recibido con `defaultPortalContent`. Accesos, secretarías e imágenes incompletos pueden recuperar valores del frontend. La URL del Boletín siempre sale de la configuración de Angular. El JSON sintético rellena la estructura y usa listas no vacías donde se produciría ese respaldo. Las imágenes de `assets.example.test` son referencias deliberadamente no operativas.

Flyway `backend/src/main/resources/db/migration/V4__portal_site_content.sql` contiene una carga inicial histórica. Angular también tiene `frontend/src/app/config/site-content.ts` y `secretarias-municipales.ts` como valores de respaldo. Son referencias versionadas con datos y campos que difieren entre sí. Para una restauración oficial, pedir una exportación autorizada del singleton real con su fecha e inventario de imágenes. Validar el JSON y sus referencias antes de decidir la importación. Este paquete usa exclusivamente `site-content.staging.json` y se detiene si ya existe cualquier fila institucional, incluso si el ID es distinto de `1`.

## Primer administrador

`first-admin` pide el nombre y correo de la persona responsable y solicita dos veces la contraseña sin eco. Ejecutarlo desde una terminal interactiva privada, sin grabar la sesión. Usar el generador del gestor de contraseñas y una contraseña aleatoria de 32 caracteres ASCII. La herramienta exige entre 20 y 72 bytes, coincidencia exacta y ausencia de caracteres de control o espacios en los extremos. El secreto se ingresa solo en las dos solicitudes ocultas. Si la terminal no permite ocultar la entrada, el alta falla. Si una interrupción deja la terminal sin eco, ejecutar `stty sane` antes de continuar.

El correo se valida y se normaliza. Si coincide con una cuenta existente, se detiene, sin cambiar su contraseña ni promoverla. También se detiene ante una identidad administrativa activa o inactiva, incluidos los roles históricos `ADMIN`, `ROLE_ADMIN` y `ROLE_SUPER_ADMIN` tanto en `usuarios` como en `usuario_roles`. Se crea una cuenta activa, con BCrypt compatible con Java, `token_version=0` y `SUPER_ADMIN` en ambas tablas. El alta no crea sesiones y no imprime el correo, el hash ni la contraseña como parte del resultado.

Cada operación usa una transacción y vuelve a comprobar la ausencia de datos después de bloquear `schema-ready` en `auth_mutexes`. En la misma transacción inserta `provision-site-content` o `provision-first-admin`. Así, una operación confirmada queda registrada aunque alguien retire posteriormente su dato, y un fallo parcial no consume la oportunidad de aprovisionar. Conservar ambas marcas junto con los datos y no usarlas como autorización de acceso. El bloqueo coordina invocaciones de esta herramienta. El procedimiento requiere que Java, otras herramientas SQL y la API permanezcan fuera de escritura. [MariaDB, SELECT FOR UPDATE](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update)

## Instalación y comandos en cPanel

Subir `web-ensenada-iwinds-provision-staging.zip` y su `.sha256` a un directorio privado y vacío, por ejemplo `/home/ensenada/iwinds-provision-staging`. Antes de escribir en la base, conservar una exportación privada del staging y verificar su alcance. Ejecutar desde la Terminal de cPanel, con el usuario que administra la instalación privada.

```sh
umask 077
cd /home/ensenada/iwinds-provision-staging || exit 1
sha256sum -c web-ensenada-iwinds-provision-staging.zip.sha256 || exit 1
unzip -n web-ensenada-iwinds-provision-staging.zip || exit 1
sha256sum -c SHA256SUMS || exit 1
cd /home/ensenada/backend-laravel || exit 1
test ! -e .iwinds-api-enabled && test ! -L .iwinds-api-enabled || exit 1
release=/home/ensenada/iwinds-provision-staging
for rel in deploy/iwinds/provision-staging.php deploy/iwinds/site-content.staging.json deploy/iwinds/PROVISION-STAGING.md; do
  test ! -e "$rel" && test ! -L "$rel" || exit 1
done
for rel in deploy/iwinds/provision-staging.php deploy/iwinds/site-content.staging.json deploy/iwinds/PROVISION-STAGING.md; do
  install -m 600 "$release/payload/$rel" "$rel" || exit 1
done
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" -l deploy/iwinds/provision-staging.php || exit 1
"$PHP83" deploy/iwinds/schema-check.php --privilege-profile=cpanel-dml || exit 1
"$PHP83" deploy/iwinds/provision-staging.php status
```

Si el contenido está pendiente y corresponde usar datos sintéticos, ejecutar el primer bloque. Ejecutar el segundo cuando la persona responsable esté disponible para ingresar su credencial. Si hay una marca registrada, un registro existente o una identidad histórica, detenerse y revisar con el responsable de los datos.

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/provision-staging.php site-content
```

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/provision-staging.php first-admin
```

Comprobar el estado al finalizar. Si las dos operaciones se hicieron con esta herramienta, el resultado esperado es `VÁLIDO`, identidad administrativa `SÍ` y dos marcas `REGISTRADO`. `schema-check.php` debe dejar de informar el pendiente institucional. La lista de rutas comprueba el registro de las rutas en caché. Las respuestas HTTP de Laravel en iWinds se validarán en una etapa web posterior autorizada.

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/provision-staging.php status || exit 1
"$PHP83" deploy/iwinds/schema-check.php --privilege-profile=cpanel-dml || exit 1
"$PHP83" artisan route:list --path=api || exit 1
test ! -e .iwinds-api-enabled && test ! -L .iwinds-api-enabled || exit 1
```

## Reversión y alcance

Si una operación falla, comprobar `status` antes de repetir. Para retirar el incremento, mover los tres archivos instalados a una carpeta privada nueva y conservar los datos y las marcas. El paquete solo agrega estos archivos y no requiere regenerar cachés. El retiro de archivos deja intactos los registros confirmados. Si hubo un alta equivocada, mantener el sitio cerrado y coordinar la desactivación controlada de esa cuenta con la identidad de esquema existente. Verificar la revocación de sesiones. La sustitución del contenido sintético por una versión oficial requiere una aprobación y una copia previa. La herramienta carece de un comando de borrado, reemplazo o restablecimiento.

```sh
cd /home/ensenada/backend-laravel || exit 1
test ! -e .iwinds-api-enabled && test ! -L .iwinds-api-enabled || exit 1
retired=$(mktemp -d /home/ensenada/provision-retirado.XXXXXXXX) || exit 1
mv deploy/iwinds/provision-staging.php deploy/iwinds/site-content.staging.json deploy/iwinds/PROVISION-STAGING.md "$retired/"
```

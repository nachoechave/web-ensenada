# Cierre de Web Ensenada en iWinds

El incremento se instala sobre la versión con Public Probe en `prueba.ensenada.gov.ar`. Conserva Angular, las rutas de Laravel, el esquema de MariaDB y la cuenta de ejecución. El ZIP incluye siete archivos privados, el puente Apache, el fragmento de reescritura y esta guía. `FILES` y `SHA256SUMS` identifican cada componente. El repositorio incluye el generador local `php deploy/iwinds/build-closure.php`, que usa PHP 8.3 con `ext-zip` y fija el inventario y las fechas del ZIP.

## Autenticación y modos

Apache autentica a cada visitante con Basic y entrega a PHP la identidad de servidor `REMOTE_USER`. El puente exige esa identidad, HTTPS real, el host de staging y la concordancia con la línea de solicitud original. Separa las credenciales Basic antes de cargar Laravel. `Authorization: Bearer` se rechaza en staging. El JWT se entrega en la cookie `WEB_ENSENADA_SESSION`, `HttpOnly`, `Secure`, `SameSite=Strict` y con `Path=/api`. Login y actualización de perfil conservan los campos JSON que consume Angular. `token` se devuelve como cadena vacía en staging. El servidor vuelve a consultar estado, rol y revocación. Fetch Metadata y `Origin` siguen aplicándose a escrituras. La petición de staging se construye sin activar sustitución de método. [Apache, autenticación y autorización](https://httpd.apache.org/docs/2.4/howto/auth.html) · [Laravel 13, protección de origen](https://laravel.com/docs/13.x/csrf)

| Modo | Cómo se abre | Alcance |
| --- | --- | --- |
| Cerrado | Ausencia de los tres marcadores | `503` del puente después de Basic. |
| Público | Procedimiento de Public Probe | Consultas GET y HEAD autorizadas, sin sesión Laravel. |
| Administrativo sintético | `open-admin` | Rutas de staging con Basic, sesión Laravel y roles. Admite permisos por tabla o el perfil `cpanel-dml` verificado para datos sintéticos. |
| Backend completo en staging | `open-full` | Exige la verificación de permisos mínimos por tabla. Conserva Basic, sesión Laravel, autorización y almacenamiento privado. |

Las aperturas administrativas duran 30 minutos. Los marcadores distinguen el modo y el vencimiento mediante una firma basada en el comprobante privado de rotación. Un marcador manual vacío, una fecha sin firma, una firma de otro modo, un enlace simbólico, un vencimiento o varios marcadores simultáneos cierran la API. Mantener el comprobante y los marcadores con permisos privados. Si cambia el hash de la contraseña, la identidad o el secreto JWT, volver a verificar la rotación antes de abrir otra ventana. Retirar el marcador antes de modificar los permisos de MariaDB o restaurar una copia. La firma protege el procedimiento frente a cambios accidentales. Una cuenta del sistema que pueda escribir el código y sus archivos privados ya controla el despliegue.

La alternativa evaluada es un control de acceso de red o un proveedor de identidad delante del subdominio completo, con verificación de identidad en el servidor y sin confiar en cabeceras aportadas por el cliente. Depende de capacidades que iWinds aún debe confirmar. Para el cierre se usa el Basic ya instalado, junto con la sesión y los roles de Laravel.

## Instalación y respaldo

Subir `web-ensenada-iwinds-closure.zip` y `web-ensenada-iwinds-closure.zip.sha256` a `/home/ensenada/iwinds-closure`, un directorio privado nuevo. Conservar una copia privada y verificable de la base sintética y su almacenamiento para poder recuperar los datos de prueba. Ejecutar en la Terminal de cPanel.

```sh
umask 077
cd /home/ensenada/iwinds-closure || exit 1
sha256sum -c web-ensenada-iwinds-closure.zip.sha256 || exit 1
unzip -n web-ensenada-iwinds-closure.zip || exit 1
sha256sum -c SHA256SUMS || exit 1
release=$PWD
backend=/home/ensenada/backend-laravel
docroot=/home/ensenada/public_html/prueba.ensenada.gov.ar
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
for marker in .iwinds-api-public-probe .iwinds-api-admin-probe .iwinds-api-enabled; do
  test ! -e "$backend/$marker" && test ! -L "$backend/$marker" || exit 1
done
backup=$(mktemp -d /home/ensenada/iwinds-closure-backup.XXXXXXXX) || exit 1
printf 'Guardar la ruta de respaldo: %s\n' "$backup"
mkdir "$backup/apache" || exit 1
cp -p "$docroot/.htaccess" "$docroot/ensenada-api.php" "$backup/apache/" || exit 1
for component in app/Auth/JwtSessions.php app/Content/SiteImageEncoder.php app/Http/Controllers/AuthController.php app/Support/IwindsPublicProbe.php app/Support/IwindsStaging.php deploy/iwinds/provision-staging.php public/index.php; do
  mkdir -p "$backup/$(dirname "$component")" || exit 1
  cp -p "$backend/$component" "$backup/$component" || exit 1
  "$PHP83" -l "$release/private/$component" || exit 1
done
"$PHP83" -l "$release/apache/ensenada-api.php" || exit 1

for component in app/Auth/JwtSessions.php app/Content/SiteImageEncoder.php app/Http/Controllers/AuthController.php app/Support/IwindsPublicProbe.php app/Support/IwindsStaging.php deploy/iwinds/provision-staging.php public/index.php; do
  cp -p "$backup/$component" "$backend/$component.closure-next" || exit 1
  cat "$release/private/$component" > "$backend/$component.closure-next" || exit 1
  mv "$backend/$component.closure-next" "$backend/$component" || exit 1
done
cp -p "$backup/apache/ensenada-api.php" "$backup/ensenada-api.closure-next" || exit 1
cat "$release/apache/ensenada-api.php" > "$backup/ensenada-api.closure-next" || exit 1
mv "$backup/ensenada-api.closure-next" "$docroot/ensenada-api.php" || exit 1
```

En el `.htaccess` del document root, reemplazar el bloque de reescritura del puente por `apache/htaccess.fragment`, conservando las reglas de autenticación de cPanel, el handler de PHP y el fallback de Angular. La regla nueva deja de copiar `Authorization` al entorno de PHP. Mantener las denegaciones directas de `.env` y `ensenada-api.php` y el enrutamiento de `/api` y `/uploads` al puente. Confirmar que el document root carece de aliases o copias de almacenamiento privado. Si el hosting mantiene bytecode antiguo, aplicar el mecanismo de actualización de OPcache del hosting y volver a comprobar con ambos extremos cerrados.

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/platform.php || exit 1
"$PHP83" deploy/iwinds/schema-check.php --privilege-profile=cpanel-dml || exit 1
"$PHP83" artisan route:list --path=api || exit 1
"$PHP83" deploy/iwinds/provision-staging.php status || exit 1
```

Con permisos mínimos por tabla, ejecutar `schema-check.php` sin opción. Los comandos usan las cachés de Laravel existentes. Confirmar por HTTPS `401` sin Basic, `200` en Angular autenticado y `503` en `/api/site-content` autenticado. El estado institucional debe ser `VÁLIDO` y el primer administrador debe existir. Los límites de `platform.php` son de CLI. El criterio web se comprueba con cargas desde el formulario real.

## Rotación y apertura administrativa sintética

La contraseña expuesta del SUPER_ADMIN se rota por decisión del responsable desde la Terminal de cPanel, con la API cerrada. Usar una contraseña nueva generada por el gestor, de al menos 20 bytes y hasta 72. La herramienta pide el correo de la cuenta existente, la confirmación `ROTAR` y dos entradas ocultas de la nueva contraseña. Conserva el ID, el correo, el rol y el estado. Solo confirma si el trigger incrementó la versión y retiró sus sesiones. El comprobante privado queda ligado al hash vigente sin guardar la contraseña ni el hash BCrypt en el archivo. Si también se expuso la contraseña de Basic, rotarla en cPanel antes de abrir la prueba y comprobar que la anterior recibe `401`.

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/provision-staging.php rotate-admin || exit 1
"$PHP83" deploy/iwinds/provision-staging.php open-admin || exit 1
```

La rotación se ejecuta una vez para la contraseña expuesta. Para reabrir una ventana posterior se usa `open-admin` con el comprobante vigente y los demás marcadores ausentes. La apertura verifica el perfil de grants y el contenido institucional. Las pruebas operativas usan identidades y registros sintéticos. La cuenta SUPER_ADMIN se usa para la autenticación inicial y para crear operadores sintéticos por el panel. Las pruebas de cambio de contraseña y desactivación se hacen con esos operadores.

## Aceptación por HTTPS

Usar una ventana privada nueva para distinguir la protección del subdominio de la sesión de Laravel. `curl --user "$BASIC_USER"` solicita la contraseña básica por terminal. En DevTools, verificar HTTPS, `/api` en el mismo origen y las cabeceras de la cookie. Evitar exportar cabeceras de credenciales, cookies o cargas privadas a capturas o informes.

```sh
base=https://prueba.ensenada.gov.ar
read -r -p 'Usuario de la protección básica: ' BASIC_USER
curl -sS -o /dev/null -w '%{http_code}\n' "$base/api/auth/me"
curl -sS --user "$BASIC_USER" -o /dev/null -w '%{http_code}\n' "$base/"
curl -sS --user "$BASIC_USER" -i "$base/api/auth/me"
curl -sS --user "$BASIC_USER" -i "$base/api/site-content"
curl -sS --user "$BASIC_USER" -i "$base/api/admin/usuarios"
curl -sS --user "$BASIC_USER" -i -H 'Origin: https://outside.invalid' -H 'Sec-Fetch-Site: cross-site' \
  -H 'Content-Type: application/json' --data '{}' "$base/api/auth/login"
curl -sS --user "$BASIC_USER" --path-as-is -o /dev/null -w '%{http_code}\n' "$base/api/%61uth/me"
```

Los resultados esperados son `401`, `200`, `401` JSON sin desafío Basic, `200` JSON, `401` JSON, `403` y `404`, respectivamente. Un `503` con Basic y ventana vigente indica un problema del puente, del marcador o de la instalación. Un `404` en una ruta permitida obliga a comprobar `REMOTE_USER`, la línea original y el entorno FastCGI del vhost. Conservar el marcador ausente mientras se resuelve el problema.

| Módulo | Aceptar cuando se pueda demostrar desde Angular |
| --- | --- |
| Sesiones y usuarios | El login válido crea la cookie indicada, `token` es vacío, `/me` reconoce al usuario y logout invalida la sesión anterior. Login inválido muestra el error. PRENSA y HACIENDA reciben `403` para operaciones ajenas. Crear y actualizar un operador sintético, probar cambio de contraseña, desactivación y reactivación, y comprobar que el acceso anterior se revoca. Confirmar expiración usando una sesión de prueba ya vencida. |
| Noticias | Crear un borrador con JPEG o PNG, editar, publicar, abrir el detalle público y ver la imagen. Despublicar y archivar. Confirmar desaparición de listados públicos y registro en la auditoría. Cargar una imagen inválida y comprobar error de validación y ausencia de archivo publicado. |
| Hacienda | Crear un borrador con dos documentos, reordenar y descargar por administración. Comprobar `404` público en borrador, publicar y verificar los bytes, despublicar, retirar y archivar. |
| Proveedores | Subir documentos, cambiar el orden, alternar visibilidad, comprobar la descarga pública y verificar `404` después de ocultar o retirar. |
| Contenido institucional | Editar hero, navbar, áreas, intendencia, accesos, agenda y footer. Guardar, recargar y comprobar persistencia y campos públicos. Probar imagen de hero o galería y confirmar que la descarga coincide. |
| Límites y errores | Confirmar JPEG/PNG válidos, PDF representativo de 20 MiB, formatos de Proveedores admitidos e imagen institucional representativa cercana a 30 MiB. Rechazar tamaños y formatos inválidos con el error del contrato. Confirmar límites web equivalentes a los requeridos por `platform.php`, espacio disponible y ausencia de respuestas 5xx, archivos truncados o excepciones JavaScript inesperadas. |

Registrar resultado, código, operación y un identificador sintético por caso. Bloquear la aprobación si aparece un bypass de Basic, login sin rotación, una operación fuera de rol, una descarga privada pública, un fallo de revocación, datos incorrectos después de recargar, errores web de PHP, o un contrato que impide completar un flujo. El ensayo del alojamiento requiere sus límites efectivos de Apache, WAF y PHP-FPM, que pueden diferir de los locales.

El procesador institucional convierte el JPEG habitual en una sola pasada. Si el primer resultado excede 5 MiB, conserva la reducción de calidad y tamaño con el tiempo restante del mismo límite de 20 segundos. Las demás entradas conservan su recorrido de detección de transparencia y los límites existentes.

## Apertura completa y reversión

La apertura completa se autoriza después de aprobar la matriz HTTPS, validar el trigger y su definidor, confirmar respaldo y restauración de base y almacenamiento, y verificar que la cuenta de Laravel tiene permisos mínimos por tabla sin DDL, roles ni delegación. `cpanel-dml` solo se acepta para la prueba administrativa con datos sintéticos. La comprobación local no acredita esas condiciones en iWinds.

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/schema-check.php || exit 1
"$PHP83" deploy/iwinds/provision-staging.php open-full || exit 1
```

`open-full` abre las funciones completas en el subdominio protegido durante 30 minutos. La configuración `production` continúa bloqueada por Laravel. Un corte con datos reales necesita permisos por tabla aplicables en el hosting, configuración de producción revisada, plan de migración autorizado, restauración probada, validación real de HTTPS, cargas, logs y un responsable de reversión. Si cPanel solo concede DML de base, mantener la apertura limitada a la ventana sintética.

Para cerrar, mover el marcador activo al respaldo privado guardado durante la instalación. Si se detectó un conflicto, mover todos los marcadores para que ninguno quede activo. Comprobar `503` con Basic en `/api/site-content`, `401` sin Basic y `200` para Angular autenticado.

```sh
backend=/home/ensenada/backend-laravel
docroot=/home/ensenada/public_html/prueba.ensenada.gov.ar
test -d "$backup/apache" || exit 1
closed=$(mktemp -d "$backup/closed.XXXXXXXX") || exit 1
for marker in .iwinds-api-public-probe .iwinds-api-admin-probe .iwinds-api-enabled; do
  if test -e "$backend/$marker" || test -L "$backend/$marker"; then
    mv "$backend/$marker" "$closed/" || exit 1
  fi
done
```

Para retirar el incremento después de cerrar, revisar si cPanel cambió `.htaccess` desde el respaldo y conservar sus directivas de autenticación vigentes. Restaurar únicamente los archivos del incremento. Conservar la contraseña rotada y las sesiones revocadas. La recuperación de datos sintéticos desde la copia es una operación separada.

```sh
cp -p "$backup/apache/.htaccess" "$backup/apache/ensenada-api.php" "$docroot/" || exit 1
for component in app/Auth/JwtSessions.php app/Content/SiteImageEncoder.php app/Http/Controllers/AuthController.php app/Support/IwindsPublicProbe.php app/Support/IwindsStaging.php deploy/iwinds/provision-staging.php public/index.php; do
  cp -p "$backup/$component" "$backend/$component" || exit 1
done
```

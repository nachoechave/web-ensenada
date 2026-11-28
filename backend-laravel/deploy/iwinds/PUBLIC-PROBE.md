# Prueba temporal de la API pública en iWinds

Este procedimiento se aplica a `prueba.ensenada.gov.ar` con autenticación básica de cPanel, HTTPS y datos sintéticos. La instalación privada está en `/home/ensenada/backend-laravel`. El marcador temporal `.iwinds-api-public-probe` se lee en cada solicitud y vence automáticamente. El intervalo máximo aceptado es de 60 minutos y los comandos de esta guía usan 30. Ambos marcadores juntos cierran la API. Durante esta prueba, `.iwinds-api-enabled` debe estar ausente.

El puente valida HTTPS, el host y la identidad que Apache entrega después de autenticar al visitante. Compara la línea HTTP original de Apache con el método y destino recibidos por PHP. Antes de cargar Laravel rechaza cuerpos, encabezados de sustitución de método o ruta y destinos fuera de la lista. Laravel vuelve a verificar el modo de staging y exige que la solicitud aprobada venga del puente. Apache conserva su autenticación básica para todo el subdominio. [Apache, mod_rewrite y THE_REQUEST](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html)

## Rutas permitidas

| GET y HEAD | Destinos permitidos |
| --- | --- |
| Noticias | `/api/noticias`, `/api/noticias/destacadas`, `/api/noticias/{slug-o-id}`. Slug canónico ASCII en minúsculas, con números y guiones. El listado acepta `page` y `size` decimales, cada uno una vez. |
| Hacienda | `/api/hacienda` y `/api/hacienda/{id}`. ID decimal positivo. |
| Proveedores | `/api/proveedores/documentos`. |
| Contenido institucional | `/api/site-content`. |
| Archivo sintético opcional | `/uploads/noticias/00000000-0000-4000-8000-000000000001.png` solo con `synthetic-image` en el marcador. PNG fijo de 1 × 1, generado para esta prueba y servido desde el puente, sin consultar almacenamiento. |

Se exige la ruta canónica, sin barra final, escapes ni segmentos que cambien al normalizar. Fuera del listado de Noticias se omite la query. La prueba devuelve `404` al pedir una ruta o método fuera de alcance, incluidos `/api/auth`, `/api/admin` y las descargas almacenadas. Apache puede rechazar solicitudes malformadas antes de PHP. El `503` indica que el puente carece de un modo válido. Los controladores públicos mantienen sus filtros y pueden devolver su propio `404` si no existe una publicación. Un listado vacío es válido para staging recién aprovisionado.

## Instalación privada

Subir el ZIP y el archivo `.sha256` a un directorio privado nuevo. Usar la Terminal de cPanel para verificar y extraer el incremento. Conservar la copia de seguridad fuera del document root.

```sh
umask 077
cd /home/ensenada/iwinds-public-probe || exit 1
sha256sum -c web-ensenada-iwinds-public-probe.zip.sha256 || exit 1
unzip -n web-ensenada-iwinds-public-probe.zip || exit 1
sha256sum -c SHA256SUMS || exit 1

release=$PWD
backend=/home/ensenada/backend-laravel
docroot=/home/ensenada/public_html/prueba.ensenada.gov.ar
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
test ! -e "$backend/.iwinds-api-enabled" && test ! -L "$backend/.iwinds-api-enabled" || exit 1
test ! -e "$backend/.iwinds-api-public-probe" && test ! -L "$backend/.iwinds-api-public-probe" || exit 1
test ! -e "$backend/app/Support/IwindsPublicProbe.php" && test ! -L "$backend/app/Support/IwindsPublicProbe.php" || exit 1

backup=$(mktemp -d /home/ensenada/public-probe-backup.XXXXXXXX) || exit 1
printf 'Guardar esta ruta de copia: %s\n' "$backup"
cp -p "$docroot/.htaccess" "$backup/.htaccess" || exit 1
cp -p "$docroot/ensenada-api.php" "$backup/ensenada-api.php" || exit 1
cp -p "$backend/app/Support/IwindsStaging.php" "$backup/IwindsStaging.php" || exit 1

install -m 600 "$release/private/app/Support/IwindsPublicProbe.php" "$backend/app/Support/IwindsPublicProbe.php" || exit 1
"$PHP83" -l "$backend/app/Support/IwindsPublicProbe.php" || exit 1
cp -p "$backup/IwindsStaging.php" "$backend/app/Support/IwindsStaging.php.next" || exit 1
cat "$release/private/app/Support/IwindsStaging.php" > "$backend/app/Support/IwindsStaging.php.next" || exit 1
"$PHP83" -l "$backend/app/Support/IwindsStaging.php.next" || exit 1
mv "$backend/app/Support/IwindsStaging.php.next" "$backend/app/Support/IwindsStaging.php" || exit 1

"$PHP83" -l "$release/apache/ensenada-api.php" || exit 1
cp -p "$backup/ensenada-api.php" "$backup/ensenada-api.next" || exit 1
cat "$release/apache/ensenada-api.php" > "$backup/ensenada-api.next" || exit 1
mv "$backup/ensenada-api.next" "$docroot/ensenada-api.php" || exit 1
```

En `.htaccess`, reemplazar únicamente el bloque anterior de `RewriteRule` del puente por las reglas de `apache/htaccess.fragment`, antes del fallback de Angular. Conservar las directivas de autenticación básica de cPanel, el handler PHP y las demás reglas del sitio. El fragmento reserva todo `/uploads` para impedir que Apache entregue un archivo estático por fuera del puente. Confirmar que el document root carece de copias o aliases de almacenamiento privado y que `MultiViews` e índices de directorio están desactivados. Conservar también cualquier denegación existente para `.env` y el directorio privado.

El incremento no cambia rutas ni configuración cacheada. Comprobar el acceso por HTTPS con ambos marcadores ausentes. Angular autenticado debe devolver `200`, `/api/site-content` autenticado `503` y el subdominio sin credenciales `401`. Si un cambio de PHP parece no reflejarse, comprobar la configuración de OPcache del hosting antes de continuar.

## Abrir la ventana de prueba

Usar el marcador privado por 30 minutos. El valor predeterminado conserva todas las descargas bloqueadas. Para probar el PNG fijo, agregar exactamente ` synthetic-image` después de la fecha en la misma línea. El ejemplo usa la prueba sin archivos.

```sh
umask 077
cd /home/ensenada/backend-laravel || exit 1
test ! -e .iwinds-api-enabled && test ! -L .iwinds-api-enabled || exit 1
test ! -e .iwinds-api-public-probe && test ! -L .iwinds-api-public-probe || exit 1
marker=$(mktemp .iwinds-public-probe.XXXXXXXX) || exit 1
printf '%s\n' "$(( $(date +%s) + 1800 ))" > "$marker" || exit 1
mv "$marker" .iwinds-api-public-probe || exit 1
```

Los comandos siguientes solicitan la contraseña básica en la terminal. `CPANEL_USER` se refiere al usuario de la protección del subdominio. Usar la URL HTTPS con validación de certificado.

```sh
base=https://prueba.ensenada.gov.ar
read -r -p 'Usuario de la protección básica: ' CPANEL_USER
curl -sS -o /dev/null -w '%{http_code}\n' "$base/api/site-content"
for route in '/api/noticias?page=0&size=12' /api/noticias/destacadas /api/hacienda /api/proveedores/documentos /api/site-content; do
  curl -sS --user "$CPANEL_USER" -i "$base$route"
done
curl -sS --user "$CPANEL_USER" -I "$base/api/site-content"
curl -sS --user "$CPANEL_USER" -o /dev/null -w '%{http_code}\n' -X POST "$base/api/auth/login"
curl -sS --user "$CPANEL_USER" -o /dev/null -w '%{http_code}\n' "$base/api/admin/usuarios"
curl -sS --user "$CPANEL_USER" -o /dev/null -w '%{http_code}\n' "$base/uploads/hacienda/archivo-de-prueba.pdf"
curl -sS --user "$CPANEL_USER" --path-as-is -o /dev/null -w '%{http_code}\n' "$base/api/%6eoticias"
curl -sS --user "$CPANEL_USER" -H 'X-Original-URL: /api/admin/usuarios' -o /dev/null -w '%{http_code}\n' "$base/api/site-content"
```

Esperar `401` en la primera solicitud, `200` con JSON y `Cache-Control: no-store` en los listados y contenido, `200` sin cuerpo para `HEAD`, y `404` en los cinco rechazos finales. Para probar detalles, usar el slug o ID de un registro sintético publicado si está disponible. Si se autorizó el PNG opcional, comprobar `image/png` y el SHA-256 `194c89992426f1265d380ac6df595d54fa6cec6ef07329d5ccfebdf60e1af2a9`. La prueba del PNG confirma el puente y el bloqueo de las demás descargas. La entrega de archivos guardados en Laravel queda fuera de esta ventana.

## Cierre y reversión

Cerrar la ventana manualmente al terminar, aunque todavía quede tiempo. `backup` es el directorio privado impreso durante la instalación.

```sh
backend=/home/ensenada/backend-laravel
docroot=/home/ensenada/public_html/prueba.ensenada.gov.ar
test -d "$backup" || exit 1
test ! -e "$backend/.iwinds-api-enabled" && test ! -L "$backend/.iwinds-api-enabled" || exit 1
mv "$backend/.iwinds-api-public-probe" "$backup/marker-retired" || exit 1
```

Con el marcador apartado, comprobar `503` autenticado en `/api/site-content`, `401` sin credenciales y `200` en Angular autenticado. Si se necesita retirar el incremento, restaurar los archivos respaldados y apartar la política nueva. Revisar si cPanel editó `.htaccess` desde la copia antes de restaurarlo para conservar cualquier cambio de autenticación más reciente.

```sh
test ! -e "$backend/.iwinds-api-public-probe" && test ! -L "$backend/.iwinds-api-public-probe" || exit 1
cp -p "$backup/.htaccess" "$docroot/.htaccess" || exit 1
cp -p "$backup/ensenada-api.php" "$docroot/ensenada-api.php" || exit 1
cp -p "$backup/IwindsStaging.php" "$backend/app/Support/IwindsStaging.php" || exit 1
mv "$backend/app/Support/IwindsPublicProbe.php" "$backup/IwindsPublicProbe.php" || exit 1
```

## Alcance y verificación pendiente

El vhost real debe transmitir a PHP la línea original de Apache y el usuario autenticado. El entorno local comprueba el flujo con Apache y PHP local. Si cPanel o PHP-FPM no transmiten esos valores, la solicitud se bloquea. Confirmar los estados anteriores en iWinds antes de dar la prueba por aprobada. El perfil `cpanel-dml` sigue limitado a la base de staging con datos sintéticos. Los permisos de la cuenta de MariaDB conservan el alcance documentado para staging y esta prueba no autoriza un corte a producción.

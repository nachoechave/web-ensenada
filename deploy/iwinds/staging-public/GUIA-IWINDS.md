# Apertura pública de prueba.ensenada.gov.ar

La lectura pública usa `.iwinds-staging-public-web`, firmado y privado. Admite solo las rutas y métodos de lectura declarados. Las rutas de login, sesión y administración requieren además el período prolongado vigente. El marcador administrativo conserva firma y vencimiento. Un marcador público ausente devuelve el entorno al régimen anterior con Basic. Un marcador público inválido o combinado con activación completa o Public Probe cierra la API. Si el período administrativo vence o se retira, siguen disponibles las lecturas públicas.

El puente exige HTTPS real, el host exacto y la señal interna de reescritura de Apache. Rechaza orígenes ajenos, cabeceras reenviadas, métodos alternativos y Bearer. Las escrituras requieren `Origin` exacto y `Sec-Fetch-Site: same-origin` también en Laravel. El login conserva el límite por IP efectiva de PHP. Las sesiones existentes siguen sujetas a expiración, revocación y roles. Un login nuevo permite también previsualizar imágenes privadas con una cookie cifrada, host-only, `HttpOnly`, `Secure`, `SameSite=Strict` y limitada a `/uploads`. Esa cookie reutiliza la vigencia y revocación de la sesión y sirve exclusivamente para imágenes. [Apache, reescritura por directorio](https://httpd.apache.org/docs/current/en/rewrite/htaccess.html) · [Laravel, protección CSRF](https://laravel.com/framework/docs/csrf)

Noticias sirve portadas y galerías referenciadas por al menos una noticia publicada. Sitio sirve las fotografías referenciadas en el documento institucional público. PRENSA y SUPER_ADMIN pueden ver sus vistas previas de Noticias durante el período, y SUPER_ADMIN las de Sitio. Hacienda y Proveedores conservan sus controles de publicación. Si una foto se usa también en una noticia pública, esa referencia sigue autorizando su descarga. Los archivos locales históricos que usen otro dominio o parámetros en su URL requieren comprobación explícita antes de abrir.

## Instalar con Basic todavía activo

1. Confirmá que el document root de `prueba.ensenada.gov.ar` sea `/home/ensenada/public_html/prueba.ensenada.gov.ar`, que Laravel y su almacenamiento estén en `/home/ensenada/backend-laravel` y que el sitio y los archivos sean exclusivamente sintéticos. Confirmá que ninguna copia ni enlace publicado sirva directamente `/uploads`, la configuración o el almacenamiento privado. Conservá el comprobante de rotación y el marcador administrativo vigente. Confirmá que el hosting entrega HTTPS y la IP efectiva directamente a PHP. Si inyecta cabeceras reenviadas, falla el acceso con esta configuración hasta que se revise el vhost.
2. Guardá el ZIP y su SHA-256 fuera de `public_html`. En Terminal de cPanel ejecutá:

```sh
umask 077
cd /home/ensenada/iwinds-staging-publico &&
sha256sum -c web-ensenada-iwinds-staging-publico.zip.sha256 &&
unzip -n web-ensenada-iwinds-staging-publico.zip &&
sha256sum --strict -c SHA256SUMS &&
bash install.sh install
```

El instalador compara los archivos instalados con los hashes de los incrementos de cierre, fotos y período administrativo. Si difieren, conservá Basic y revisá la versión. Guarda los archivos originales y el `.htaccess` en `/home/ensenada/iwinds-public-web-state/original`. Conserva todos los marcadores y las contraseñas. Se puede repetir antes de la activación.

3. Editá únicamente el `.htaccess` del subdominio de prueba. Reemplazá el bloque anterior de enrutamiento de `/api` y `/uploads` por `apache/htaccess.fragment`, antes del fallback de Angular. Conservá el bloque de Directory Privacy, el handler de PHP y las demás reglas del vhost. Confirmá que el enrutamiento vence a archivos físicos y que el puente directo, los dotfiles y `backend-laravel` devuelven 404. Apache rechaza Bearer antes de ejecutar PHP, incluso si el handler no transmite `Authorization`. Si la señal interna `REDIRECT_ENSENADA_STAGING_ROUTE` o la denegación de Bearer no funciona, mantené Basic.
4. Verificá el perfil de MariaDB y activá la lectura pública **con Basic todavía activo**:

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/schema-check.php --privilege-profile=cpanel-dml &&
"$PHP83" deploy/iwinds/provision-staging.php status-admin-extended &&
"$PHP83" deploy/iwinds/provision-staging.php open-public-web --synthetic-only &&
"$PHP83" deploy/iwinds/provision-staging.php status-public-web
```

Para un perfil de grants por tabla, ejecutar `schema-check.php` sin opción. Si el período informado estaba vigente, debe conservar la misma fecha. No se escribe en las tablas para abrir, renovar o cerrar los marcadores. Antes de retirar Basic, comprobá HTTPS con Basic y repetí los casos administrativos de la matriz con cuentas sintéticas. El formulario de Angular debe enviar los dos encabezados requeridos con el origen de staging. Si falla cualquiera de estas comprobaciones, mantené Basic y cerrá el acceso público.

5. Retirá Basic **manualmente** en cPanel → Privacidad del directorio, solo sobre `public_html/prueba.ensenada.gov.ar`. Confirmá que cPanel conservó el bloque del puente y el fallback y repetí de inmediato la siguiente matriz en una ventana sin credenciales Basic. Si la protección es heredada desde un directorio superior, no modifiques el padre. La publicación queda bloqueada hasta aislar el vhost. [cPanel, privacidad del directorio](https://docs.cpanel.net/cpanel/files/directory-privacy/)

## Matriz HTTPS antes de dar por abierta la prueba

| Verificación | Resultado requerido |
| --- | --- |
| Portada y rutas públicas de Angular | HTTP 200 en Inicio, Noticias, detalle, Áreas, Intendencia, Hacienda y Proveedores. La navegación debe mostrar contenido público del CMS. |
| API y documentos públicos | JSON HTTP 200 en las consultas públicas, más GET y HEAD de un archivo publicado de cada módulo. Una foto de borrador o sin referencia, un PDF archivado y un documento inactivo devuelven 404 sin sesión. |
| Administración sin sesión | 401 en rutas administrativas declaradas mientras el período está vigente. Al retirar o vencer el período, login y administración devuelven 404 y las lecturas públicas conservan HTTP 200. |
| Inicio de sesión y roles | PRENSA puede gestionar Noticias, HACIENDA sus publicaciones y documentos, y SUPER_ADMIN Usuarios y Sitio. Los roles ajenos reciben 403. Verificá cookies, vista previa, logout y rechazo de la sesión anterior. |
| Entrada | URL directa del puente, otro host, POST público, orígenes ajenos, falta de Fetch Metadata, Bearer y cabeceras reenviadas quedan rechazados. El preflight ajeno carece de permisos CORS. |

```sh
base=https://prueba.ensenada.gov.ar
curl -fsS "$base/api/site-content" >/dev/null
curl -sS -o /dev/null -w '%{http_code}\n' "$base/api/admin/usuarios"
curl -sS -o /dev/null -w '%{http_code}\n' "$base/ensenada-api.php"
```

En iWinds quedan pendientes la señal de reescritura real tras quitar Basic, posibles proxies, el alcance de Directory Privacy, la inexistencia de rutas estáticas alternas, el contenido sintético y la configuración efectiva de PHP, TLS y WAF. Si alguna condición no se puede garantizar, mantené Basic. El perfil `cpanel-dml` sigue circunscrito a datos sintéticos. El acceso abierto aumenta el tráfico y los intentos de login. El límite por IP no sustituye los controles del hosting contra tráfico distribuido o agotamiento de recursos.

## Cerrar y revertir

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/provision-staging.php close-admin-extended
# Para suspender también la lectura pública y cualquier modo heredado:
"$PHP83" deploy/iwinds/provision-staging.php suspend-staging-api
```

Ambos cierres operan sin MariaDB. El segundo cierra la API para solicitudes nuevas, pero Angular es estático y puede seguir visible hasta restablecer Basic. Una solicitud ya admitida puede terminar. Para cerrar solo la lectura y conservar el marcador administrativo, usá `close-public-web`; las nuevas solicitudes administrativas vuelven a requerir Basic. Para reabrir la administración con el modo público vigente, después de retirar un marcador vencido, usá `open-admin-extended 7 --synthetic-only`. Para extender uno vigente, usá `renew-admin-extended 7 --synthetic-only`.

Para volver a la versión previa, suspendé la API o cerrá solo el modo público si querés conservar el vencimiento administrativo. Restablecé Directory Privacy en cPanel y comprobá 401 sin Basic. Desde `/home/ensenada/iwinds-staging-publico`, ejecutá `bash install.sh rollback`. Restaura el puente, los PHP previos y el `.htaccess` protegido respaldado. Revisá antes los cambios legítimos posteriores en ese archivo. Conservá la base, los archivos privados y las cuentas. Si el hosting no revalida OPcache, volvé a probar bajo Basic después de la actualización de caché del hosting.

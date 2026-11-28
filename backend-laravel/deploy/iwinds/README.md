# Web Ensenada en iWinds

Para la instalación privada existente, aplicar la [actualización incremental de la Etapa 8C](UPDATE-8C.md). Laravel usa `ensenada_web_app` también para Noticias. La cuenta `ensenada_web_schema` permanece reservada para el esquema y el trigger.

El perfil preferido de permisos sigue siendo `schema-grants.sql` por tabla. Si cPanel solo permite configurar permisos de base, el [incremental de permisos cPanel](UPDATE-CPANEL-DML.md) permite comprobar explícitamente el perfil alternativo en staging y explica sus límites antes de cualquier corte.

## Alcance

El paquete corresponde a la **instalación privada de staging** para `prueba.ensenada.gov.ar`. El perfil `APP_ENV=staging` se limita a ese origen HTTPS, al directorio `/home/ensenada/backend-laravel` y a MySQL local con la base `ensenada_web` y el usuario `ensenada_web_app`. El perfil `production` sigue bloqueado. El ZIP mantiene la API cerrada y separa los archivos candidatos de Apache del backend privado. La instalación privada no cambia Angular ni las respuestas actuales del subdominio.

| Requisito | Estado |
| --- | --- |
| PHP 8.3.36 web y CLI, PDO MySQL y `ext-zip` | Informados como confirmados en iWinds. El `vendor` se instala y se comprueba localmente con el lock de producción en PHP 8.3.36. BCMath queda fuera de los requisitos. |
| `memory_limit=256M`, `upload_max_filesize=32M`, `post_max_size=40M`, `max_execution_time=120` | Perfil informado. Cubre los límites de entrada de 30 MiB de Sitio y 20 MiB de documentos. Falta confirmar el límite efectivo del vhost, proxy y WAF. |
| GD con JPEG y PNG, `proc_open`, `/usr/bin/prlimit` e ImageMagick `/usr/bin/convert` | El comprobador valida su presencia y que ImageMagick cargue la política incluida. Confirmar desde PHP web y con una carga representativa en el hosting. |
| Base de datos | iWinds informó MariaDB 10.11.19. Para esa instalación se usa `DB_COLLATION=utf8mb4_unicode_520_ci` y una sola conexión MySQL de ejecución. La configuración por defecto de MySQL 8 conserva `utf8mb4_0900_ai_ci`. |
| Composer, Node y procesos de fondo | El paquete contiene `vendor`. La instalación en cPanel usa PHP CLI. Angular ya está publicado. La API no requiere workers persistentes ni cron. El encoder institucional usa un subproceso limitado por solicitud. |

## Contenido y verificación

El artefacto es `output/iwinds-release/web-ensenada-iwinds-staging.zip`, junto a `web-ensenada-iwinds-staging.zip.sha256`. `MANIFEST.json` contiene el SHA-256 de cada archivo. El ZIP se construye con una lista explícita de directorios y desde `composer.lock`, con `--no-dev`, autoloader optimizado, comprobación de plataforma y auditoría de dependencias. Solo incluye el código de ejecución, `vendor`, directorios vacíos de almacenamiento y caché, herramientas privadas de verificación, una plantilla de entorno y los candidatos Apache. La base, migraciones de pruebas, logs, credenciales, `.env` y documentos históricos quedan fuera. [Composer CLI](https://getcomposer.org/doc/03-cli.md), [Laravel 13, despliegue](https://laravel.com/framework/docs/13.x/deployment).

Para reconstruir desde `backend-laravel` con Docker local:

```sh
docker buildx build --target iwinds-package --output type=local,dest=output/iwinds-release .
```

## Instalación privada en cPanel

1. Confirmar que `/home/ensenada/public_html/prueba.ensenada.gov.ar` sigue siendo el document root. Registrar el estado HTTP actual y guardar una copia privada de su `.htaccess`. Mantener el `index.html`, JavaScript, CSS y assets de Angular.
2. Subir el ZIP y el `.sha256` a un directorio **privado y vacío** de la cuenta. Desde allí ejecutar `sha256sum -c web-ensenada-iwinds-staging.zip.sha256` antes de extraer. Si ya existe `/home/ensenada/backend-laravel`, preservar esa instalación y resolver el reemplazo en una ventana aparte. Mover `private/backend-laravel` al destino indicado. Mantener `apache/` y el ZIP fuera de `public_html` en esta instalación.
3. Dejar ausente el marcador privado `.iwinds-api-enabled`. Dejar también sin aplicar el fragmento Apache. Así Angular y el servicio que ya atienda `/api` conservan su enrutamiento actual.
4. Ajustar propietario y permisos al usuario efectivo de PHP. Dar `0700` a `storage/app/private` y sus subdirectorios `noticias`, `sitio`, `hacienda`, `proveedores` y `ensenada-site-scratch`. Dar escritura privada al usuario efectivo en `storage` y `bootstrap/cache`. Usar `0600` para `.env`, logs o archivos privados cuando corresponda. Los documentos nuevos ya se escriben con `0600`. Evitar `777`, `storage:link`, aliases y enlaces simbólicos hacia los archivos. Verificar cuotas, `open_basedir`, temporal de PHP y soporte de `link` dentro del mismo filesystem.
5. Crear `.env` únicamente en el backend privado a partir de `deploy/iwinds/production.env.example`. Conservar `APP_ENV=staging`, `APP_DEBUG=false` y el origen exacto. Cargar claves `APP_KEY` y `AUTH_JWT_SECRET` independientes y la contraseña de `ensenada_web_app` por el mecanismo autorizado. Verificar el perfil de permisos elegido con `schema-check.php` y conservar el definidor del trigger. El comando sin opciones exige los permisos por tabla. Si el servidor requiere un host distinto del local o una collation diferente, detener la habilitación y validar el perfil.
6. Ejecutar primero `platform.php`. El comprobador de esquema se ejecuta después de que el responsable confirme que `ensenada_web` corresponde al staging autorizado. El script hace consultas de lectura y no muestra filas ni contraseñas. La auditoría independiente de grants, restricciones y trigger de revocación sigue siendo necesaria.

```sh
cd /home/ensenada/backend-laravel
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/platform.php
"$PHP83" deploy/iwinds/schema-check.php
"$PHP83" artisan route:list
"$PHP83" artisan config:cache
"$PHP83" artisan route:cache
"$PHP83" artisan route:list
```

El caché de configuración incorpora secretos. Guardarlo en privado y regenerarlo tras editar `.env`. `config:clear` y `route:clear` permiten revertir los cachés. El perfil de staging limita Artisan a comandos de inspección y caché. No se debe ejecutar `migrate`, `db:seed` ni `migrate:fresh` en iWinds.

## Esquema histórico y archivos

Para la base vacía de iWinds con MariaDB 10.11.19, seguir [Etapa 8B — MariaDB](SCHEMA-MARIADB.md). El procedimiento se entrega por separado del ZIP instalado. [Etapa 8 — MySQL](SCHEMA.md) queda disponible para instalaciones MySQL 8.

- Conservar IDs, nombres de columnas, índices y filas del esquema Flyway, incluida `documentos_proveedores` de V7. El runtime usa también `auth_sessions`, `auth_login_windows`, `auth_mutexes`, las filas `login`, `news-slug` y `proveedores`, y el mecanismo de revocación. Verificar el trigger con el responsable de la base. `schema-check.php` comprueba disponibilidad de tablas, columnas clave y accesos de lectura. La preparación de tablas auxiliares requiere un procedimiento de base separado y aprobado.
- Las migraciones locales incluyen auxiliares de autenticación y preparación de fixtures. Sus operaciones de reversión pueden eliminar tablas. Se excluyen del ZIP. El ensayo de staging debe utilizar datos sintéticos aislados. Para un corte autorizado posterior, hacer inventario de documentos históricos con ID, UUID, tamaño y SHA-256. Restaurar a los cuatro directorios privados preservando nombres y asociaciones.
- Los tokens JWT de Java no se trasladan a Laravel. El corte requiere volver a iniciar sesión. Evitar escrituras simultáneas con Java y Laravel sobre las mismas tablas.

## Apache y validación web posterior

El candidato `apache/ensenada-api.php` es el único archivo del ZIP previsto para el document root en una futura habilitación. `apache/htaccess.fragment` se integra manualmente **antes del fallback de Angular**, conservando el handler PHP de cPanel. El fragmento reserva `/api` y `/uploads/{noticias,sitio,hacienda,proveedores}` para Laravel, bloquea acceso directo al puente y a `.env` y evita servir archivos estáticos bajo esos prefijos. Revisar `mod_rewrite`, `AllowOverride FileInfo`, `MultiViews` apagado, herencia y ejecución PHP efectiva. Con marcador ausente, el puente devuelve `503` a las rutas reservadas, por eso se deja el fragmento fuera durante la instalación privada. [Apache 2.4, reglas por directorio](https://httpd.apache.org/docs/2.4/rewrite/htaccess.html).

Antes de cualquier habilitación pública, ejecutar una prueba web autorizada con acceso acotado. Confirmar PHP web y logs privados, IP `REMOTE_ADDR` efectiva, HTTPS sin confiar en encabezados reenviados del cliente, cookies `Secure`, `HttpOnly`, `SameSite=Strict`, `Path=/api`, CSRF de mismo origen, Angular en la raíz y recarga en rutas profundas. Probar un PDF de 20 MiB, XLSX, PNG de 20 MP, una imagen institucional de 30 MiB, rechazos y concurrencia. Verificar `404` para Hacienda privada y Proveedores inactivos, y descargas históricas. Confirmar que `/ensenada-api.php`, `/.env`, `vendor` y las ubicaciones privadas no expongan bytes. Evaluar el impacto SEO del Angular estático si el corte reemplaza algún SSR previo.

## Reversión

En una instalación solo privada, conservar o apartar la carpeta nueva sin tocar el document root ni la base. Si en otra etapa se aplican las reglas, desactivar primero el marcador privado, restaurar el `.htaccess` anterior desde la copia privada y retirar únicamente el puente candidato del docroot. Verificar por HTTPS la raíz, navegación directa, assets y el comportamiento anterior de `/api` y `/uploads`. Mantener el backend privado y logs para diagnóstico. Conciliar cualquier escritura de staging antes de otro corte.

## Evidencia y pendientes

El ZIP se prueba extraído en un contenedor sin red y en el path privado esperado. Las pruebas Apache locales validan la reescritura y la convivencia con Angular. Las suites de API, paridad y Chromium usan bases descartables. El informe local `output/iwinds-release-checks/` registra las verificaciones ejecutadas. Estas pruebas no sustituyen la prueba del vhost de iWinds.

Los pendientes para habilitar `/api` son los grants efectivos y el definidor del trigger en iWinds, el aprovisionamiento y la restauración autorizados, la validación web de PHP e ImageMagick, cuotas y permisos y la aprobación del corte con reversión probada. La actualización privada y el diagnóstico por CLI pueden preceder esas pruebas sin cambiar el sitio publicado.

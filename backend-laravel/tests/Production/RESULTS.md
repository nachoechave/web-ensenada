# Validación de publicación, 27 de noviembre de 2026

Se trabajó en `feat/iwinds-production-cutover`, a partir de `origin/main` `fb59b08`. Laravel existente estaba disponible localmente, sin un snapshot completo en el historial de main; `web-ensenada-publicacion` es otro checkout del mismo repositorio con Java y Angular. Esta rama incorpora el backend previo y las correcciones del perfil de producción. No se conectó al hosting.

## Resultados ejecutados

| Comprobación | Resultado real |
|---|---|
| PHPUnit SQLite con Symfony actualizado | 167 tests, 504 assertions, aprobados |
| Regresión del período administrativo de staging | 3 tests, 31 assertions, aprobados |
| Angular | 74 tests aprobados en 8 archivos, incluidos 8 casos nuevos de imágenes |
| Build Angular original | Aprobado, main-AGXYZRPY.js / styles-5MXFBPYB.css |
| Build de la corrección indispensable de imágenes | Aprobado, main-5AVB2EQE.js; CSS sin cambios |
| HTTP real Apache 2.4.68 + PHP 8.3.36 + MariaDB 10.11.19 | 131 comprobaciones aprobadas |
| Aislamiento, cierre, preview y rollback | 23 comprobaciones aprobadas |
| EXIF, transparencia, límites y galería | 16 comprobaciones aprobadas |
| Angular real en Chromium con HTTPS | 18 comprobaciones aprobadas, cero errores JavaScript |
| ZIP real, instalación repetida y reversión | 15 comprobaciones aprobadas, incluidas actualización Angular idempotente y restitución exacta de su índice |
| Schema-check | Doce tablas InnoDB/utf8mb4_unicode_520_ci, mutexes y grants por tabla aprobados |
| Inicialización no destructiva e integridad | Segunda inicialización rechazada antes del primer CREATE; sesión huérfana rechazada por FK con error 1452 y rollback |
| Bloqueo de cpanel-dml | Agregar DML a nivel de base en el runtime sintético produjo HTTP 500 cerrado; restaurar grants mínimos recuperó HTTP 200 |
| Backup y restauración | Script real de backup aprobado con SHA de SQL y dos TAR; SQL restaurado en segunda MariaDB 10.11.19 sin red; 12 tablas y trigger presentes; checksums iguales en usuarios, noticias, contenido, Hacienda y Proveedores |
| Dependencias PHP | 76 paquetes de producción, PHP 8.3 compatible; cinco avisos corregidos con cuatro actualizaciones Symfony 7.4.24; auditoría sin avisos |

Las pruebas HTTP verifican login con cookies, CSRF, Fetch Metadata y cabeceras manipuladas, rechazo de Bearer, roles, usuarios no autenticados, logout/revocación/expiración/rate limit, CMS persistido, noticias borrador/publicación/archivado, imágenes públicas y previews, Hacienda sin documentos, documentos privados y publicados, retiro del último documento, Proveedores activos/inactivos y cabeceras de descarga. Prueban también rutas profundas, colisión física de Hacienda, canonicalización HTTPS/www, turno prioritario, archivos históricos, ACME y dos subdominios representativos.

Las pruebas de operaciones comprueban independencia de los archivos de staging después de la atestación inicial, espera de operaciones en curso, cierre sin extensiones ni base, aprobación explícita para abrir, rechazo de marcadores falsificados, grants que impiden borrar usuarios/modificar auditoría/borrar mutexes/crear estructura, preview por IP real, rechazo de IP simulada mediante X-Forwarded-For, rollback con SHA obsoleto rechazado y restitución byte por byte del `.htaccess` anterior.

Chromium utilizó el build real corregido. Ingresó como SUPER_ADMIN sintético, consultó Usuarios/Noticias/Hacienda/Proveedores/Sitio, subió JPEG 4000×2250, PNG y WebP desde el formulario, vio las tres vistas previas 1920×1080, publicó y abrió el detalle por slug en 1920/1366/390 px, cargó WebP al Hero y guardó el CMS. Las capturas y el resultado quedaron en `output/browser-production`, fuera de Git y del ZIP de despliegue.

## Errores encontrados y corregidos

- El formulario Angular de Noticias aún limitaba JPEG/PNG a 5 MiB. Se admiten JPEG/PNG/WebP de hasta 30 MiB y se conserva el máximo de seis, sin modificar estilos ni contratos. Se agregó prueba de límites, MIME, portada y error 413.
- Apache conservaba la redirección de directorio físico `/hacienda` aunque reescribiera al índice Angular. Se acotó DirectorySlash al host y a rutas Angular concretas; los PDFs históricos continúan accesibles.
- Un `.htaccess` hijo puede sustituir las reglas del padre. Se exige la marca interna de reescritura también en autorización del directorio hijo, bloqueando URLs directas y codificadas.
- Se duplicaban nosniff y Cache-Control entre puente y Laravel. El puente conserva esas cabeceras en sus rechazos y Laravel las gestiona al atender la solicitud.
- La ruta privada y política de imágenes contemplaban solo staging. Se incorporó producción conservando el comportamiento anterior.
- Cinco avisos en dependencias Composer: http-kernel, routing y mailer. Se actualizaron además sus dependencias mínimas http-foundation, sin salto de versión mayor ni requisitos ignorados.

Los fallos de montaje del vendor, carreras del observador Playwright y selector duplicado de guardar se corrigieron en el arnés de pruebas. No representan cambios de producto.

## Reproducir localmente

Usar Docker local, nunca estos tests en iWinds. Las bases Compose usan tmpfs, red interna y ningún puerto publicado; la restauración se ejecutó en otro contenedor sin red. No reutilizar volúmenes ni contenedores ajenos.

1. Construir la imagen de herramientas/aplicación con `backend-laravel/Dockerfile`, contexto Flyway del backend Java intacto y target `application`, etiqueta `ensenada-auth-stage2-app:local`.
2. Preparar `output/dependencies` desde composer.lock con `composer install --no-dev --no-scripts --no-plugins`; ejecutar check-platform-reqs y audit. Preparar por separado vendor de desarrollo para PHPUnit. No instalar herramientas globales.
3. En frontend: `npm ci --ignore-scripts`, `npm test`, `npm run build -- --output-path=../backend-laravel/output/angular-production`. El delta esperado se verifica contra `deploy/production/angular-release.json`; conservar el V13 original para ensayar rollback. La descarga de tipografías requiere red durante el build.
4. `docker build -t ensenada-production-apache:local -f tests/Production/Dockerfile .`.
5. `docker compose -f tests/Production/compose.yaml up -d` y revisar que el arranque complete schema-check, atestación, cachés y habilitación **solo del contenedor**.
6. `docker compose -f tests/Production/compose.yaml exec -T apache php /source/tests/Production/http.php`.
7. `docker compose -f tests/Production/compose.yaml exec -T --user www-data apache php /source/tests/Production/operations.php`.
8. Chromium se ejecuta con la imagen Playwright de `tests/Angular`, compartiendo únicamente la red del Apache descartable, `tests/Production` montado en `/production-tests` y un directorio vacío de resultados en `/results`. Generar las fotos con `tests/Production/fixtures.php`; ejecutar `node /production-tests/browser.cjs`.
9. `docker compose -f tests/Production/compose.yaml exec -T apache php /source/tests/Production/photos.php`.
10. Ejecutar `build-local.sh` en el contenedor con el vendor de producción, fuente y ruta nueva de salida; validar dos ZIP con igual SHA. `tests/Production/package.php` prueba el ZIP en otro contenedor con `/home/ensenada` vacío.
11. `docker compose -f tests/Production/compose.yaml down` elimina exclusivamente este entorno descartable. Retirar también el contenedor de restauración identificado por nombre propio.

La fixture Apache representa WordPress con un PHP testigo y subdominios de prueba. No es una instalación completa de WordPress ni cPanel. Se ejercitó PHP 8.3 real y un mapeo local del handler; el handler ea-php83, PHP 8.2 existente, AllowOverride y todas las aplicaciones municipales necesitan aceptación real en el hosting. TLS local usa un certificado de prueba; el verificador entregado para iWinds exige TLS válido.

## Bloqueos que siguen impidiendo aprobar producción

Grants mínimos reales y base independiente autorizada; datos oficiales aprobados y su importación; cuota efectiva; respaldo municipal restaurable; validación efectiva del handler y recursos web; circuito administrativo en el dominio definitivo y comparación funcional de servicios; decisión sobre SSR/SEO del browser V13. El ZIP no habilita tráfico ni contiene marcadores de activación.

El build mantiene avisos de presupuesto de tamaños preexistentes. `npm audit --omit=dev` informa body-parser y qs del servidor Node de SSR, que no se instala en este paquete; una futura habilitación de ese servidor exige corregirlos. No se modifican WordPress ni su PHP 8.2. Mantenerlos accesibles preserva también sus riesgos anteriores; no se presenta esta entrega como saneamiento de WordPress.

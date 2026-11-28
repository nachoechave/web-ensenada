# Validación local de la Etapa 8C

El ZIP incremental publicado en `output/iwinds-stage8c-release/web-ensenada-iwinds-stage8c.zip` tiene SHA-256 `86885ac9fe7617203b2f2e56db4690b9e8a761dca69a9f3e1581b9a8f2ff2763`. `FILES` enumera las 11 rutas del paquete y `SHA256SUMS` permite verificar cada archivo. La [guía privada](UPDATE-8C.md) describe la instalación, los permisos y la reversión.

| Verificación | Resultado |
| --- | --- |
| MariaDB 10.11.19, sin usuario lector | 78 pruebas HTTP y 1198 aserciones. Las rutas de Noticias conservaron los DTO, la paginación, el ocultamiento de borradores y archivadas y los permisos de escritura. Se probaron también la autenticación, el trigger, Hacienda y Proveedores. |
| Esquema y permisos en MariaDB | 4 pruebas y 47 aserciones. La cuenta de ejecución pudo consultar ambas tablas de Noticias, careció de permiso para borrar usuarios y no pudo consultar el esquema de referencia. |
| Verificador de despliegue | Pasó con los permisos por tabla y rechazó un `ALL PRIVILEGES` añadido al esquema descartable. Pasó nuevamente tras retirar ese grant. Se comprobó directamente que la instancia carecía de una cuenta lectora. |
| Angular en Chromium por HTTPS | 38 pruebas aprobadas sin omisiones ni resultados inestables. |
| SQLite | 97 pruebas de aplicación, 421 aserciones. Pasaron además 19 pruebas HTTP de Noticias y edición, con 192 aserciones. |
| Paquete incremental | Se comprobó el SHA-256 del ZIP y de sus entradas. Se aplicó sobre el ZIP privado anterior en un contenedor sin red. Pasaron el arranque sin lector, la creación y limpieza de cachés y la restauración byte a byte del respaldo. El marcador web siguió ausente. |
| Estilo | Pint aprobó 107 archivos y `git diff --check` pasó. |

Los registros locales están en `output/stage8c/` y `output/angular/stage8c/playwright.json`.

## Archivos

| Grupo | Rutas relativas a `backend-laravel` |
| --- | --- |
| Laravel, en el ZIP | `app/Http/Controllers/NoticiasPublicasController.php`, `app/Providers/AppServiceProvider.php`, `app/Support/IwindsStaging.php`, `config/database.php` |
| iWinds, en el ZIP | `deploy/iwinds/schema-check.php`, `deploy/iwinds/schema-grants.sql`, `deploy/iwinds/production.env.example`, `deploy/iwinds/README.md`, `deploy/iwinds/SCHEMA.md`, `deploy/iwinds/SCHEMA-MARIADB.md`, `deploy/iwinds/UPDATE-8C.md` |
| Construcción y entorno descartable | `AGENTS.md`, `Dockerfile`, `compose.stage2.yaml`, `compose.stage4a.yaml`, `compose.stage8.yaml`, `docker/mysql-ready.php`, `docker/mysql-stage8-accounts.sh`, `docker/mysql/20-test-users.sql`, `docker/mysql/25-spring-news-oracle.sql` |
| Pruebas y evidencia | `deploy/iwinds/test-package.sh`, `tests/Http/NewsHttpTest.php`, `tests/Stage8/SchemaInitTest.php`, `tests/Stage8/update-8c-smoke.sh`, `deploy/iwinds/VALIDACION-8C.md` |

La cuenta `news_reader` de pruebas históricas se conserva únicamente en el overlay del oráculo Spring Boot. Laravel y el entorno MariaDB de 8C carecen de esa dependencia. Los archivos de Angular, Spring Boot, `.htaccess` y el DDL de inicialización quedaron intactos en esta etapa.

La validación en iWinds requiere comprobar el otorgante, aplicar y verificar los permisos mínimos reales y conservar el definidor `ensenada_web_schema`. La prueba local mantiene la API deshabilitada y no acredita los grants del hosting.

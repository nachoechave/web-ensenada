# Validación local del paquete iWinds

Paquete de Laravel 13.40.0 para instalación privada de staging, en la rama `migration/laravel-backend`. El artefacto está en `backend-laravel/output/iwinds-release/web-ensenada-iwinds-staging.zip`. El SHA-256 del ZIP verificado es `c1f635e6a96e74776434fdf0da3f8c23feb4d87855469379f03f84eb70643e64`.

| Verificación | Resultado |
| --- | --- |
| Composer en PHP 8.3.36 | 76 dependencias de producción en las mismas versiones del lock. Pasaron la validación, `check-platform-reqs --no-dev` y la auditoría sin avisos de vulnerabilidades. |
| ZIP y manifiesto | 5.938 archivos comprobados por SHA-256. La instalación extraída carece de `.env`, marcador web, migraciones, fixtures, dependencias de desarrollo y contenido de almacenamiento. |
| Instalación privada simulada | Extracción sin red en el path privado previsto, arranque CLI de staging, creación y limpieza de cachés, rechazo de producción, de un host MySQL fuera de alcance y de `artisan migrate`. El puente con marcador ausente quedó cerrado. |
| PHP y archivos | Pasó el comprobador con 256M, 32M, 40M y `max_execution_time=120`. La simulación del paquete cargó su política privada de ImageMagick, procesó un JPEG sintético de 30 MiB y comprobó el almacenamiento fuera del docroot. Las pruebas HTTP incluyen PDFs de 20 MiB y PNG de 20 MP. |
| Proxies | El perfil staging ignoró `X-Forwarded-*`, incluso con un nombre de host que activa autodetección de proxy en Laravel. El perfil local pasó las 15 pruebas HTTP de cookies y autenticación tras el ajuste. |
| SQLite | 97 pruebas de aplicación y 73 HTTP. |
| MySQL 8.4 | 102 pruebas de aplicación y 78 HTTP, incluida verificación de las definiciones Flyway. |
| Paridad con Spring Boot | 36 pruebas de API, 13 editoriales y 2 de imágenes. |
| Chromium y Angular real | 41 pruebas HTTPS aprobadas, sin omisiones ni resultados inestables. |
| Apache 2.4 y PHP 8.3 | El fixture HTTPS conservó la raíz Angular, la entrega de assets y el enrutamiento API. El PHP del fixture controló las rutas de archivos incluso cuando existía un centinela en el docroot. |
| Formato | Pint aprobó 106 archivos. `git diff --check` pasó. |

Los registros están en `backend-laravel/output/iwinds-release-checks/`. El detalle de Chromium queda en `backend-laravel/output/angular/iwinds-package/playwright.json`.

**Listo para instalación privada controlada.** La validación del PHP web, Apache, MySQL, grants, permisos, archivos históricos y carga concurrente de iWinds queda pendiente. Las pruebas MySQL se ejecutaron sobre las bases descartables del proyecto. El comprobador de esquema distribuido se reserva para el staging autorizado. La [guía de instalación y reversión](README.md) separa la instalación privada de la futura habilitación de `/api`.

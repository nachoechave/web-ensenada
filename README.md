# Web Ensenada — portal editorial

Home institucional, noticias y publicaciones de Hacienda, con administración por roles. Angular 21 + Spring Boot 4 / Java 21. Boletín Oficial sigue siendo externo y Proveedores mantiene su acceso pendiente. Hacienda tiene un dominio propio de publicaciones y documentos; no es un CMS municipal genérico.

## Desarrollo

En `backend`, definir `JWT_SECRET` (aleatoria, al menos 32 bytes); opcionalmente `APP_ADMIN_EMAIL` y `APP_ADMIN_PASSWORD` (12–72 caracteres) para crear el primer administrador. No hay credenciales predeterminadas. La cuenta inicial se crea solo si el email no existe; reiniciar no reactiva ni cambia roles de cuentas existentes.

```powershell
./mvnw.cmd spring-boot:run -Dspring-boot.run.profiles=dev
```

El perfil `dev` usa H2 en memoria, Flyway y validación del esquema: los datos se pierden al reiniciar. Para persistencia usar MySQL con `DB_URL`, `DB_USERNAME` y `DB_PASSWORD` sin perfil `dev`.

En `frontend`:

```sh
npm ci
npm start
```

La API se centraliza en `src/environments/environment*.ts` (`/api`). El proxy de desarrollo redirige `/api` y `/uploads` al backend en 8080. Producción debe publicar ambos detrás del mismo origen. `CORS_ALLOWED_ORIGINS` acepta orígenes separados por coma; está vacío por defecto en producción.

## Validación

```sh
cd frontend
npm ci
npm test -- --run
npm run build
cd ../backend
./mvnw test
./mvnw package
```

En Windows usar `mvnw.cmd`. El adaptador de tests traduce `--run` a `ng test --watch=false`. GitHub Actions ejecuta ambas suites y ambos builds. Tras retirar clases Java de una copia usada anteriormente, ejecutar `./mvnw clean test` una vez para eliminar `.class` antiguos.

## Configuración institucional

- Textos de Home/footer: `frontend/src/app/config/site-content.ts`. Se quitó el CMS general que no alimentaba el hero.
- Sistemas externos: `frontend/src/app/config/external-links.ts`. Completar la URL oficial de Proveedores antes de habilitar ese acceso. Los enlaces externos abren otra pestaña con `noopener noreferrer`.
- Hacienda es un acceso interno a `/hacienda`, con detalle en `/hacienda/:id`. `/registro-proveedores` conserva la página informativa de acceso pendiente.
- `Conocé más` lleva a la información institucional de Home.

## Roles y publicación

`PRENSA` administra noticias y su propia cuenta. `HACIENDA` administra publicaciones de Hacienda y su propia cuenta. `SUPER_ADMIN` administra ambos dominios y usuarios. Las altas y los cambios de rol admiten exactamente un rol operativo: PRENSA o HACIENDA. No se pueden crear, degradar ni desactivar superadministradores desde el panel. Las respuestas de usuarios nunca contienen hashes. El cambio de contraseña propio pide la contraseña actual; no hay infraestructura de recuperación por correo ni reset administrativo.

La noticia se guarda con `BORRADOR`, `PUBLICADA` o `ARCHIVADA`. Publicar/despublicar y destacar se guardan en la API. `DELETE /api/admin/noticias/{id}` archiva, no destruye. Para recuperar una archivada se cambia su estado en el formulario. Solo `PUBLICADA` aparece en endpoints públicos y solo `PUBLICADA + destacada` en Home. La fecha es editorial, no programa publicación automática.

Imágenes: JPEG/PNG, máximo 5 MB y 20 megapíxeles. Se decodifican, verifican y vuelven a codificar; nombres UUID generados por servidor. SVG/WebP no se aceptan. Configurar `UPLOADS_DIR` con almacenamiento persistente y backup. Hacienda admite además PDF hasta 20 MB. Sus documentos se sirven mediante el backend, que verifica el estado PUBLICADA: no configurar una ruta estática del proxy para `/uploads/hacienda`. Ver [operación y API de Hacienda](docs/hacienda.md).

## Endpoints

| Método | Ruta (prefijo `/api`) | Acceso |
| --- | --- | --- |
| POST | `/auth/login` | Público |
| GET / PUT | `/auth/me` | Autenticado |
| PUT | `/auth/me/password` | Autenticado, contraseña actual |
| GET | `/noticias`, `/noticias/destacadas`, `/noticias/{id}` | Público; solo publicadas |
| GET / POST | `/admin/noticias` | PRENSA / SUPER_ADMIN |
| GET / PUT / DELETE | `/admin/noticias/{id}` | PRENSA / SUPER_ADMIN; DELETE archiva |
| POST multipart (`archivo`) | `/admin/archivos/noticias` | PRENSA / SUPER_ADMIN |
| GET / POST | `/admin/usuarios` | SUPER_ADMIN; altas PRENSA o HACIENDA |
| PUT | `/admin/usuarios/{id}/roles` | SUPER_ADMIN; un rol operativo, sin modificar administradores |
| PUT | `/admin/usuarios/{id}/activo` | SUPER_ADMIN; sin modificar superadministradores |
| GET | `/hacienda`, `/hacienda/{id}` | Público; solo publicadas |
| GET / POST | `/admin/hacienda` | HACIENDA / SUPER_ADMIN |
| GET / PUT / DELETE | `/admin/hacienda/{id}` | HACIENDA / SUPER_ADMIN; DELETE archiva |
| POST multipart (`archivo`) | `/admin/hacienda/{id}/archivos` | HACIENDA / SUPER_ADMIN |
| DELETE | `/admin/hacienda/{id}/archivos/{archivoId}` | HACIENDA / SUPER_ADMIN |
| PUT | `/admin/hacienda/{id}/archivos/orden` | HACIENDA / SUPER_ADMIN; body `{ "ids": [1, 2] }` |
| GET | `/admin/hacienda/{id}/archivos/{archivoId}/contenido` | HACIENDA / SUPER_ADMIN; descarga privada |

Rutas públicas: `/`, `/noticias`, `/noticias/:id`, `/hacienda`, `/hacienda/:id`, `/registro-proveedores`.
Rutas administrativas: `/admin/login`, `/admin` (dashboard), `/admin/noticias`, `/admin/noticias/nueva`, `/admin/noticias/editar/:id`, `/admin/hacienda`, `/admin/hacienda/nueva`, `/admin/hacienda/editar/:id`, `/admin/mi-cuenta`, `/admin/usuarios`.

## Base de datos y producción

Flyway V1 crea usuarios, roles y noticias y permanece sin cambios. V2 agrega publicaciones y archivos de Hacienda. `ddl-auto=validate` en todos los perfiles; no hay `update` ni baseline automático. Leer [la transición de bases existentes](docs/base-de-datos.md) antes de usar una base anterior. CI incluye una validación separada de V1 + V2 y arranque Spring sobre MySQL 8.4 efímero.

Antes de desplegar: configurar secretos nuevos, cuenta inicial, MySQL, backup y almacenamiento; completar URLs pendientes; validar migración en copia de la base real; configurar HTTPS, reverse proxy SPA (`index.html` para rutas frontend), límites de upload y orígenes permitidos. No usar el perfil de memoria `dev` en producción. El login tiene rate limiting configurable por IP (10 intentos / 300 segundos por defecto); revisar la política de proxy y los límites por instancia en [la guía operativa](docs/hacienda.md).

Deuda: paginación para noticias, auditoría editorial, limpieza programada de imágenes huérfanas, revocación de tokens al cambiar contraseña, recuperación de cuentas, y eventual uso de cookies HttpOnly. Actualmente tokens tienen 120 minutos de vida; desactivar una cuenta revoca acceso inmediatamente porque el backend consulta sus roles/estado en cada petición. Cambiar contraseña cierra la sesión del navegador pero no revoca otros JWT ya emitidos.

SEO inicial en español argentino, títulos y metadatos de noticia. Se mantienen IDs como identificador estable; una futura columna `slug` única, endpoint de búsqueda por slug y redirecciones desde IDs pueden añadirse sin cambiar el dominio editorial. SSR/prerender de noticias queda para una segunda etapa: los crawlers sociales que no ejecutan JavaScript ven solo metadata inicial.

El historial contiene antiguas configuraciones de contraseña/JWT (`5424a10`, `c6742d6`, retiradas parcialmente en `de1bedf`). Si se usaron fuera del desarrollo, rotarlas. No se reescribió historia Git.

# Web Ensenada — portal de prensa

Home institucional, noticias públicas y panel de prensa. Angular 21 + Spring Boot 4 / Java 21. Hacienda, Boletín Oficial y Proveedores son sistemas externos: este repositorio no administra sus datos.

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
- Sistemas externos: `frontend/src/app/config/external-links.ts`. Hacienda y Proveedores están explícitamente pendientes: completar sus URLs oficiales antes de publicar. Todos los enlaces externos abren otra pestaña con `noopener noreferrer`.
- Las rutas antiguas `/hacienda` y `/registro-proveedores` conservan una página informativa de acceso externo, sin documentos ni CRUD.
- `Conocé más` lleva a la información institucional de Home.

## Roles y publicación

`PRENSA` administra noticias y su propia cuenta. `SUPER_ADMIN` además lista, crea, activa/desactiva periodistas. No se pueden crear, degradar ni desactivar superadministradores desde el panel, evitando bloqueos o escalamiento accidental. Las respuestas de usuarios nunca contienen hashes. El cambio de contraseña propio pide la contraseña actual; no hay infraestructura de recuperación por correo ni reset administrativo.

La noticia se guarda con `BORRADOR`, `PUBLICADA` o `ARCHIVADA`. Publicar/despublicar y destacar se guardan en la API. `DELETE /api/admin/noticias/{id}` archiva, no destruye. Para recuperar una archivada se cambia su estado en el formulario. Solo `PUBLICADA` aparece en endpoints públicos y solo `PUBLICADA + destacada` en Home. La fecha es editorial, no programa publicación automática.

Imágenes: JPEG/PNG, máximo 5 MB y 20 megapíxeles. Se decodifican, verifican y vuelven a codificar; nombres UUID generados por servidor. SVG/WebP no se aceptan (WebP requiere incorporar un decodificador y pruebas). Configurar `UPLOADS_DIR` con almacenamiento persistente y backup; publicar únicamente `/uploads/noticias/*.jpg` y `*.png`.

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
| GET / POST | `/admin/usuarios` | SUPER_ADMIN; altas solo PRENSA |
| PUT | `/admin/usuarios/{id}/roles` | SUPER_ADMIN; solo PRENSA, sin modificar administradores |
| PUT | `/admin/usuarios/{id}/activo` | SUPER_ADMIN; solo cuentas de prensa |

Rutas públicas: `/`, `/noticias`, `/noticias/:id`, `/hacienda`, `/registro-proveedores`.
Rutas administrativas: `/admin/login`, `/admin` (dashboard), `/admin/noticias`, `/admin/noticias/nueva`, `/admin/noticias/editar/:id`, `/admin/mi-cuenta`, `/admin/usuarios`.

## Base de datos y producción

Flyway V1 crea únicamente usuarios, roles y noticias. `ddl-auto=validate` en todos los perfiles; no hay `update` ni baseline automático. Leer [la transición de bases existentes](docs/base-de-datos.md) antes de usar una base anterior.

Antes de desplegar: configurar secretos nuevos, cuenta inicial, MySQL, backup y almacenamiento de imágenes; completar URLs pendientes; validar migración en copia de la base real; configurar HTTPS, reverse proxy SPA (`index.html` para rutas frontend), límites de upload, rate limiting del login y orígenes permitidos. No usar el perfil de memoria `dev` en producción.

Deuda: paginación para noticias, auditoría editorial, limpieza programada de imágenes huérfanas, revocación de tokens al cambiar contraseña, recuperación de cuentas, y eventual uso de cookies HttpOnly. Actualmente tokens tienen 120 minutos de vida; desactivar una cuenta revoca acceso inmediatamente porque el backend consulta sus roles/estado en cada petición. Cambiar contraseña cierra la sesión del navegador pero no revoca otros JWT ya emitidos.

SEO inicial en español argentino, títulos y metadatos de noticia. Se mantienen IDs como identificador estable; una futura columna `slug` única, endpoint de búsqueda por slug y redirecciones desde IDs pueden añadirse sin cambiar el dominio editorial. SSR/prerender de noticias queda para una segunda etapa: los crawlers sociales que no ejecutan JavaScript ven solo metadata inicial.

El historial contiene antiguas configuraciones de contraseña/JWT (`5424a10`, `c6742d6`, retiradas parcialmente en `de1bedf`). Si se usaron fuera del desarrollo, rotarlas. No se reescribió historia Git.

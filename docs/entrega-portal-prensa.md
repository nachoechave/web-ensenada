# Entrega — portal de prensa

Validación local del 22 de septiembre de 2026. No se hizo deploy, push, merge a main ni reescritura del historial.

## 1. Rama

`refactor/portal-prensa-scope`, creada desde `feature/frontend-backend-admin` (`de1bedf`). Se inspeccionaron `main` y feature locales y las referencias remotas disponibles. Se conservaron los siete commits exclusivos de feature. [Auditoría previa](auditoria-alcance.md).

## 2. Commits

| Commit | Descripción |
| --- | --- |
| `0bdc1af` | refactor: consolidate press backend and enforce news permissions |
| `425f67c` | feat: add Flyway schema and environment-based configuration |
| `edee01a` | refactor: simplify public portal and press administration |
| `e1cb510` | fix: restrict uploaded assets and refresh account authorization |
| `869d4b6` | security: update compatible frontend dependencies |
| `a6ba707` | test: cover news lifecycle and authorization in CI |
| `f662ec5` | chore: remove legacy documents and generated artifacts |

Un commit posterior de documentación contiene este informe, la auditoría y las instrucciones de operación. `git log` muestra su identificador definitivo.

## 3. Módulos eliminados

AdminHacienda y CRUD de documentos; entidades, repositorios y controladores de Hacienda, áreas y agenda; CMS general ContenidoSitio; upload de documentos; servicios y modelos duplicados bajo `core`; noticias/documentos/contenido en localStorage; datos demo; documentos de proveedores alojados en assets. Se retiraron las páginas de agenda, áreas, historia, teléfonos y contacto del árbol público simplificado; la información institucional de Home se conservó. Se eliminaron `error-backend.txt` y `.gitignore_old`.

## 4. Módulos conservados

Home, noticias públicas y detalle, noticias destacadas, accesos externos, navbar/footer; autenticación JWT, cuenta propia, dashboard de noticias, lista/formulario de noticias, usuarios de prensa y upload de imágenes. Se reutilizó Angular/Spring y el CRUD existente.

## 5. Rutas públicas

`/`, `/noticias`, `/noticias/:id`, `/hacienda`, `/registro-proveedores`. Las dos últimas muestran el acceso a un sistema externo o el aviso de URL pendiente, sin almacenar documentos. Las rutas desconocidas vuelven a Home.

## 6. Rutas administrativas

`/admin/login`, `/admin` (dashboard), `/admin/noticias`, `/admin/noticias/nueva`, `/admin/noticias/editar/:id`, `/admin/mi-cuenta`, `/admin/usuarios` (solo SUPER_ADMIN). Guards de acceso, de hijos y por rol.

## 7. Endpoints finales

Todos con prefijo `/api`:

| Método | Endpoint |
| --- | --- |
| POST | `/auth/login` |
| GET, PUT | `/auth/me` |
| PUT | `/auth/me/password` |
| GET | `/noticias`, `/noticias/destacadas`, `/noticias/{id}` |
| GET, POST | `/admin/noticias` |
| GET, PUT, DELETE | `/admin/noticias/{id}` |
| POST multipart | `/admin/archivos/noticias` |
| GET, POST | `/admin/usuarios` |
| PUT | `/admin/usuarios/{id}/roles`, `/admin/usuarios/{id}/activo` |

Imágenes públicas en `/uploads/noticias/*.jpg` y `*.png`; no se publican uploads de otros módulos.

## 8. Roles y permisos

PRENSA: noticias, imágenes y cuenta propia. SUPER_ADMIN: lo anterior más lista, alta y activación/desactivación de periodistas. El backend exige roles; no confía en Angular ni en los roles guardados en el navegador. Los privilegios y el estado se consultan en base de datos en cada petición autenticada.

No se permite crear ni modificar superadministradores en el panel. El bootstrap por variables de entorno solo crea una cuenta inexistente. Cambiar contraseña propia requiere contraseña actual. No se implementó reset administrativo: no existía infraestructura para ello.

## 9. Flujo de publicación

Crear → completar título, bajada, contenido, categoría, imagen, fecha, estado y destacada → guardar en API. BORRADOR permanece privado; PUBLICADA aparece en el portal; PUBLICADA destacada aparece en Home. Despublicar vuelve a BORRADOR. Archivar conserva la fila y la oculta públicamente; se puede recuperar editando el estado. Fecha de publicación tipada como LocalDate: es fecha editorial, sin programación automática. El listado muestra miniatura, título, categoría, fecha, estado, destacada, editar, publicar/despublicar y archivar, con filtros.

## 10. Enlaces externos y Home

Configuración única en `frontend/src/app/config/external-links.ts`. Se reutilizan las URLs existentes de Boletín, turnos, tasas, boleta digital y redes. Hacienda y Proveedores están a `null`, marcados pendientes. Enlaces externos coherentes con `_blank` y `noopener noreferrer`. Textos de hero/footer en `site-content.ts`; se elimina el falso CMS editable. `Conocé más` navega a la sección institucional.

## 11. Seguridad

JWT sin secreto predeterminado, mínimo 32 bytes y validación de issuer. Desactivar un periodista invalida su acceso aun con un token emitido. Respuestas DTO sin hashes; contraseña nueva de 12–72 caracteres. CORS configurable. Upload exige PRENSA/SUPER_ADMIN, JPEG/PNG reales decodificados y recodificados, UUID, máximo 5 MB y 20 megapíxeles. No SVG; WebP se deja fuera por no incorporar otro decodificador. Interceptor restringe envío del token al origen API. Solo autenticación usa almacenamiento del navegador.

Se actualizaron dependencias dentro de los rangos existentes: de 27 vulnerabilidades reportadas inicialmente a **0** en la instalación limpia final. Hay antiguas configuraciones de contraseña/JWT en el historial: rotar cualquier valor usado en entornos reales; no se alteró la historia.

## 12. Base de datos

Flyway V1 para usuarios, usuario_roles y noticias, índice de estado/destacada y validación Hibernate en todos los perfiles. Sin `ddl-auto=update`. La base anterior requiere una transferencia revisada: [procedimiento y mapeo](base-de-datos.md). No se migraron ni borraron bases reales durante esta tarea. Se conserva `usuarios.rol` por compatibilidad; noticias cambia `fecha` por `fechaPublicacion` en API y `fecha_publicacion` en SQL, y normaliza estados a mayúsculas.

## 13. Tests y CI

Backend: login correcto/incorrecto, 401, roles PRENSA/SUPER_ADMIN, usuarios sin hashes, desactivación con token existente, protección de superadministradores, crear/editar/publicar/archivar, borradores/archivadas invisibles públicamente, destacadas, validación y uploads válidos/falsificados/sin permisos. Incluye arranque con migración y validación de esquema.

Frontend: guards y roles, rutas, menú de prensa, login, listado y filtros, errores API, crear/editar y validación de imagen obligatoria, datos públicos sin fallback local, destacadas y archivado. Se reemplazaron tests generados sin comportamiento por una suite funcional. GitHub Actions ejecuta instalación, tests y builds en jobs independientes de frontend/backend; aún no ejecutado en GitHub porque no se hizo push.

## 14. Resultado exacto backend

`./mvnw.cmd test`: **Tests run: 14, Failures: 0, Errors: 0, Skipped: 0 — BUILD SUCCESS**. Java 21 / Spring Boot 4.1.0, H2 en modo MySQL con Flyway V1. Se corrigió un fallo inicial causado por `.class` viejos mediante `clean test`. Flyway advierte que H2 2.4.240 es más reciente que su versión verificada; las pruebas y validación del esquema pasan.

## 15. Resultado exacto frontend

`npm ci`: **469 paquetes instalados, 470 auditados, 0 vulnerabilidades**, salida 0.
`npm test -- --run`: **Test Files 1 passed (1); Tests 14 passed (14)**, Vitest 4.1.11, salida 0. Se adaptó `--run` a `--watch=false` porque Angular no acepta directamente ese argumento. Se corrigieron los fallos iniciales de configuración de TestBed.

## 16. Builds

`./mvnw.cmd package`: **BUILD SUCCESS**, repite **14 tests, 0 fallos, 0 errores**, genera `backend/target/backend-0.0.1-SNAPSHOT.jar`.

`npm run build`: salida **0**, bundle inicial **395,73 kB**, transferencia estimada **95,09 kB**, en `frontend/dist/frontend`. Se corrigió una declaración duplicada detectada por compilación. Advertencia restante: navbar.css 4,85 kB frente al presupuesto preventivo de 4 kB; no supera el límite de error.

## 17. Deuda técnica

Reducir CSS residual del navbar; paginar noticias; auditoría editorial; limpieza de imágenes huérfanas; recuperación de cuentas y revocación de todos los JWT al cambiar contraseña (hoy expiran a las 2 horas). Evaluar cookies HttpOnly. La UI cierra la sesión local al cambiar contraseña. IDs públicos estables; futura migración a slugs únicos con redirecciones. SSR/prerender queda para segunda etapa, especialmente para previews sociales. No se realizó revisión visual manual en navegador ni pruebas contra MySQL real.

## 18. Antes de producción

Completar URLs oficiales de Hacienda/Proveedores; revisar textos institucionales; configurar secretos nuevos y cuenta inicial; ensayar transferencia de datos sobre copia MySQL con recuentos/IDs/permisos; validar imágenes heredadas; configurar HTTPS y reverse proxy común para SPA/API/uploads; almacenamiento persistente y backups; límites de upload y rate limiting de login; CORS del entorno. Ejecutar CI tras publicar la rama para revisión. No usar H2 en memoria de `dev` para producción.

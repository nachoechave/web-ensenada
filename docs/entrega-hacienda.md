# Entrega: publicaciones de Hacienda

Validación local realizada el 22 de septiembre de 2026 (Argentina). Implementación lista para revisión; sin deploy, merge ni modificaciones de datos reales.

1. **Rama creada.** `feature/hacienda-publications`, desde `origin/refactor/portal-prensa-scope` verificada mediante fetch en `168450a581feff6a59019613c05662a94bd653b4`. No se modificó main ni se reescribió historial. Esta rama nueva queda local, sin push ni ejecución remota de Actions en esta entrega.

2. **Commits.** `6a90dcf` dominio, V2 y documentos seguros; `02e4387` permisos HACIENDA y rate limiting; `7266ce0` cobertura del límite y rutas codificadas; `c24e88d` páginas públicas; `aae4657` panel y navegación por roles; `7ca4ef5` pruebas de publicación/documentos/permisos; `50a18c4` CI y validación MySQL. El commit final `docs: document Hacienda delivery and operations` contiene este informe, la auditoría y las guías actualizadas; su hash puede consultarse con `git log -1` sobre la entrega.

3. **Entidades nuevas.** `PublicacionHacienda` (título, descripción, tipo, fecha, estado y archivos) y `ArchivoHacienda` (publicación, nombre original, nombre almacenado, MIME, orden y tamaño), relación 1:N. La URL se deriva en el DTO; no se persiste una ruta local pública. `TipoPublicacionHacienda` centraliza seis tipos y se reutiliza `EstadoPublicacion`. Noticias solo comparte la utilidad de imágenes seguras.

4. **Flyway.** `V2__hacienda_publicaciones.sql` agrega `publicaciones_hacienda` y `archivos_hacienda`, FK, índices por estado/fecha y publicación/orden, restricciones de tipo/estado/MIME/tamaño/orden y nombre almacenado único. V1 sigue idéntica al commit base. No hay DROP ni ddl-auto update; sigue validate.

5. **Rutas públicas.** `/`, `/noticias`, `/noticias/:id`, `/hacienda`, `/hacienda/:id` y `/registro-proveedores`. Hacienda aparece como enlace interno en navbar, accesos y footer. Boletín Oficial sigue externo; Proveedores pendiente. Listado de Hacienda por fecha descendente, estados loading/error/empty y detalle con documentos ordenados. Título SEO de listado y título/description dinámicos de detalle, sin SSR.

6. **Rutas admin.** `/admin/login`, `/admin`, `/admin/noticias`, `/admin/noticias/nueva`, `/admin/noticias/editar/:id`, `/admin/hacienda`, `/admin/hacienda/nueva`, `/admin/hacienda/editar/:id`, `/admin/mi-cuenta`, `/admin/usuarios`. Menú y dashboard se adaptan al rol y no solicitan datos del otro dominio.

7. **Endpoints.** GET público `/api/hacienda` y `/api/hacienda/{id}`. GET/POST `/api/admin/hacienda`; GET/PUT/DELETE `/api/admin/hacienda/{id}`. POST multipart `archivo` en `/{id}/archivos`; DELETE `/{id}/archivos/{archivoId}`; PUT `/{id}/archivos/orden` con `{ "ids": [...] }`; GET `/{id}/archivos/{archivoId}/contenido` autenticado, todos bajo `/api/admin/hacienda`. GET público `/uploads/hacienda/{UUID.ext}` verifica PUBLICADA, con `?descargar=true` para descargar. Contratos completos en [guía operativa](hacienda.md).

8. **Roles.** PRENSA: Noticias y cuenta propia. HACIENDA: publicaciones/documentos de Hacienda y cuenta propia. SUPER_ADMIN: ambos dominios, usuarios y cuenta propia. Usuarios admite un único rol operativo PRENSA o HACIENDA; no permite crear ni modificar superadministradores. Spring Security conserva denyAll y consulta roles/activo en DB; los guards solo complementan esa autorización.

9. **Flujo editorial.** Guardar borrador, adjuntar documentos, ordenar/revisar y publicar. El listado permite filtrar por estado, editar, publicar/despublicar y archivar. El formulario conserva cambios de texto mientras guarda operaciones de archivos; bloquea la edición si falla la carga inicial. Se requiere al menos un archivo para publicar. No se introdujeron datos demo ni almacenamiento editorial en localStorage.

10. **Formatos.** Únicamente PDF, JPEG y PNG. Hasta 20 MiB para PDF; 5 MiB y 20 megapíxeles para imágenes; máximo 50 documentos por publicación. No se añadió librería PDF. El público abre/descarga PDFs y ve imágenes con carga diferida en su orden editorial.

11. **Uploads.** Firma PDF y terminador EOF; formato real de imagen, decodificación y recodificación; UUID y extensión del servidor; nombre original como metadata; normalización de ruta, escritura exclusiva y rechazo de archivo final simbólico. Storage separado noticias/hacienda. MIME, nosniff, CSP y no-store en documentos. No se confía en Content-Type del cliente. La validación PDF es mínima, no un antivirus ni un saneamiento completo.

12. **Archivado.** DELETE de publicación cambia a ARCHIVADA, preservando datos. Borradores y archivadas devuelven 404 tanto en detalle público como en sus documentos. Retirar un archivo borra su asociación y deshabilita su URL, manteniendo el archivo físico para mantenimiento posterior. No puede retirarse el último documento público sin despublicar antes. Una copia previamente descargada no puede revocarse.

13. **Rate limiting.** POST login, 10 intentos por IP/300 segundos por defecto; configurable mediante LOGIN_RATE_LIMIT_ATTEMPTS, LOGIN_RATE_LIMIT_WINDOW_SECONDS y LOGIN_RATE_LIMIT_MAX_IPS (10000). Respuesta 429 y Retry-After, mensaje específico en Angular. Ventanas por instancia, memoria acotada, sin confiar en X-Forwarded-For. Si se llena la tabla se rechazan IP nuevas hasta liberar ventanas vencidas. Ver límites de proxy/distribución en la guía.

14. **Tests backend.** `mvnw.cmd clean test`: **29 registrados, 0 fallos, 0 errores, 1 omitido**; 28 ejecutados correctamente. Desglose: 13 PortalIntegrationTests, 1 BackendApplicationTests, 10 HaciendaIntegrationTests, 3 LoginRateLimitTests, 1 LoginRateLimitIntegrationTests y 1 MySqlMigrationTests omitido por requerir entorno explícito. La prueba MySQL se ejecutó además por separado con éxito. Cobertura nueva: estados públicos y URLs privadas, CRUD/archivado, PDF/JPEG/PNG reales, falsos/SVG/excesos de tamaño, orden/asociación, aislamiento de roles, altas de usuarios y límite/expiración/IP/rutas codificadas. Se mantuvieron los tests de Noticias y seguridad existentes.

15. **Tests frontend.** `npm test -- --run`: **2 archivos, 36 tests aprobados**. Incluye rutas/guards/menús, dashboard según rol, listado/detalle, SEO, filtros, formulario, publicación/archivo/upload/orden, errores y 429 del login; preserva cobertura de Noticias. Labels asociados, botones nativos con texto, acciones de orden con aria-label, imágenes con alt y estados role=status/alert revisados en código y DOM. No se realizó una auditoría manual completa de navegador, teclado o lector de pantalla.

16. **Resultado local/CI.** Se ejecutaron localmente todos los comandos obligatorios: npm ci, npm test -- --run, npm run build, mvnw.cmd clean test, mvnw.cmd package y la prueba MySQL explícita. Todos terminaron con código 0. npm ci instaló 469 paquetes, auditó 470 y reportó 0 vulnerabilidades. Se amplió el mismo workflow existente, conservando sus dos jobs. No se afirma éxito de Actions remoto: esta rama no se publicó en esta entrega.

17. **MySQL real.** Contenedor efímero MySQL **8.4.11**, base vacía `web_ensenada_validation`, puerto local 13316, sin datos reales. Comando `mvnw.cmd -Dtest=MySqlMigrationTests test` con MYSQL_VALIDATION=true: **1 test, 0 fallos, 0 errores, 0 omitidos; BUILD SUCCESS** (19.831 s). Flyway aplicó V1 y V2; Hibernate validate y arranque Spring completaron. CI usa mysql:8.4 con base descartable y la misma prueba. Credenciales vacías exclusivamente de validación.

18. **Build frontend.** `npm run build` exitoso: tamaño inicial **417.56 kB**, transferencia estimada **99.33 kB**; salida `frontend/dist/frontend`. Advertencia preexistente: navbar.css 4.85 kB supera el presupuesto de 4 kB por 848 bytes. No se alteró ese presupuesto para ocultarla.

19. **Build backend.** `mvnw.cmd package`: **BUILD SUCCESS**, 29.086 s; repite 29 tests registrados, 0 fallos/errores, 1 omitido MySQL. JAR ejecutable `backend/target/backend-0.0.1-SNAPSHOT.jar`. `clean test` previo: BUILD SUCCESS, 30.928 s. H2 muestra advertencia de versión más nueva que la verificada por Flyway; la validación adicional de MySQL pasó.

20. **Deuda técnica.** Limpieza controlada de huérfanos, análisis antimalware de PDFs, auditoría editorial, paginación según volumen, contador de login compartido si hay varias instancias, revocación de JWT al cambiar contraseña y recuperación de cuentas. Slugs, SSR, full-text, WYSIWYG, object storage y analytics quedaron fuera del alcance solicitado.

21. **Riesgos detectados.** PDFs con firma válida aún pueden contener contenido malicioso; la protección no sustituye un antivirus. Proxy con una única IP puede agrupar visitantes en el rate limiter; reinicios y múltiples instancias fragmentan contadores. Servir uploads/hacienda estáticamente saltaría la privacidad de estados. El volumen de documentos necesita backups/cuotas y una política de huérfanos. JWT existentes duran hasta 120 minutos pese a cambiar contraseña; desactivar usuarios sí invalida el acceso por consulta a DB. Las credenciales históricas señaladas en README deben rotarse si alguna vez se usaron fuera de desarrollo.

22. **Antes de producción.** Revisar esta rama y ejecutar Actions al publicarla; ensayar V2 con copia respaldada de datos reales; configurar secretos, HTTPS, CORS, cuentas HACIENDA, almacenamiento persistente/backup y límites del proxy; verificar que documentos privados pasan siempre por el backend sin caché; definir política de IP y rate limiting para el despliegue; completar Proveedores; realizar revisión manual accesible del flujo editorial. No se desplegó, mergeó ni migraron datos reales.

Referencias: [auditoría inicial](auditoria-hacienda.md), [operación y API](hacienda.md), [base de datos](base-de-datos.md).

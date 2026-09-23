# Transición a Flyway

## Ampliación Hacienda (V2)

V1 permanece intacta. `V2__hacienda_publicaciones.sql` agrega únicamente `publicaciones_hacienda` y `archivos_hacienda`, con FK, índices y restricciones. Una instalación que ya tiene V1 aplica V2 automáticamente y Hibernate valida el esquema. No se modifican Noticias ni usuarios: la colección de roles admite HACIENDA sin alterar V1.

Se ejecutó V1 → V2 sobre una base MySQL 8.4.11 vacía y efímera, con `ddl-auto=validate` y arranque Spring exitoso. El job backend de CI incorpora la misma prueba `MySqlMigrationTests`. Esto no equivale a validar una migración de datos reales; ensayarla con backup antes de producción.

El procedimiento histórico que sigue corresponde a la retirada del CMS anterior. No importa automáticamente sus documentos de Hacienda al nuevo dominio: cualquier transferencia necesita un mapeo revisado, revalidación de archivos y asignación explícita de roles. Los nuevos usuarios operativos pueden tener PRENSA o HACIENDA, exactamente uno.

## Procedimiento histórico de transición a V1

V1 describe una base nueva. No ejecuta DROP ni intenta adivinar cómo transformar datos existentes. Flyway rechazará por diseño una base no vacía sin historial. No activar `baseline-on-migrate=true` para ocultar esa situación: la base antigua no coincide con V1.

Procedimiento para una instalación existente (requiere revisión operativa, no fue ejecutado en esta tarea):

1. Detener escrituras y obtener un backup verificable de base e imágenes.
2. Crear una base MySQL nueva y arrancar la versión nueva contra ella para aplicar V1, sin variables de bootstrap de administrador.
3. Inspeccionar esquema y datos de la base anterior; corregir fechas no ISO y valores desconocidos. Conservar IDs para no romper links. Transferir usuarios y hashes existentes; conservar solo roles autorizados. Los usuarios que solo tenían HACIENDA/CONTENIDO no deben convertirse automáticamente en periodistas: dejarlos desactivados y revisar individualmente.
4. Transferir noticias cambiando `fecha` (texto `YYYY-MM-DD`) a `fecha_publicacion` (DATE), `Borrador` a `BORRADOR`, `Publicada` a `PUBLICADA`; conservar título, bajada, contenido, categoría, imagen y destacada. No descartar silenciosamente filas inválidas. Los estados desconocidos requieren revisión.
5. Verificar recuentos, IDs, acceso, estados, fechas, imágenes y permisos antes de cambiar tráfico. Rotar JWT_SECRET para invalidar tokens del despliegue anterior.
6. No copiar tablas ni uploads de Hacienda, áreas, agenda o ContenidoSitio al nuevo portal. Conservarlos en el backup según la política del municipio; no se borran de la base anterior.

Mapeo de tablas finales:

| Tabla | Campos |
| --- | --- |
| usuarios | id, nombre, email, password (hash), rol (compatibilidad), activo |
| usuario_roles | usuario_id, rol (`PRENSA` o `SUPER_ADMIN`) |
| noticias | id, titulo, bajada, contenido, imagen, categoria, fecha_publicacion, estado, destacada |

El campo `rol` de usuarios se conserva por compatibilidad con cuentas anteriores; la colección `usuario_roles` determina permisos. Si hay administradores legados `ADMIN`/`ROLE_ADMIN`, revisar y convertir explícitamente a `SUPER_ADMIN` durante la transferencia. No conceder privilegios por inferencia.

Las URLs absolutas de imágenes antiguas pueden requerir ajuste al origen final; solo JPEG/PNG se publican en la nueva ruta de uploads. Revalidar y recodificar imágenes antiguas antes de copiarlas. Los archivos activos como SVG quedan fuera del servidor de uploads.

Las pruebas actuales ejecutan V1 y V2 en H2 modo MySQL y `ddl-auto=validate`, más una validación separada sobre MySQL real vacío. Falta ensayar este procedimiento con una copia de los datos reales antes de producción. Ninguna base externa fue modificada.

# Hacienda: operación y mantenimiento

Hacienda es un dominio editorial separado de Noticias. No incorpora DocumentoMunicipal, ContenidoSitio, AreaMunicipal ni EventoAgenda. Los tipos están centralizados en un enum backend y un catálogo tipado frontend.

## Permisos

| Operación | PRENSA | HACIENDA | SUPER_ADMIN |
| --- | --- | --- | --- |
| Noticias e imágenes de noticias | Sí | No | Sí |
| Publicaciones y documentos de Hacienda | No | Sí | Sí |
| Usuarios operativos | No | No | Sí |
| Cuenta propia | Sí | Sí | Sí |

Spring Security aplica los permisos y conserva denyAll para rutas no autorizadas. Los roles y el estado activo se consultan en DB en cada petición. El frontend adapta menú y dashboard, sin conceder permisos. Las altas y cambios de rol permiten exactamente PRENSA o HACIENDA; no crean ni modifican superadministradores.

## Flujo editorial

1. Abrir `/admin/hacienda/nueva`, completar título, tipo, fecha y descripción opcional. Guardar el borrador.
2. Adjuntar un PDF completo o varias imágenes en el formulario de edición. Se admiten hasta 50 documentos.
3. Usar Subir/Bajar para ordenar, Descargar para revisar y Eliminar para retirar. Estas operaciones se guardan al instante y conservan los cambios de texto pendientes.
4. Cambiar a Publicada y guardar, o publicar desde el listado. Es obligatorio conservar al menos un documento.
5. Despublicar devuelve a Borrador. Archivar cambia a ARCHIVADA sin borrar publicación ni documentos. Se puede recuperar desde el formulario.

La fecha es editorial, sin programación automática. El público ve solo PUBLICADA, ordenada por fecha e ID descendentes. Borradores, archivadas y sus URLs de documentos devuelven 404, aun con ID/nombre conocido. Un administrador puede descargar documentos privados autenticándose. Para retirar el último documento de una publicación pública se debe despublicar primero.

## API

| Método | Ruta | Resultado |
| --- | --- | --- |
| GET | `/api/hacienda` | Lista pública |
| GET | `/api/hacienda/{id}` | Detalle público |
| GET / POST | `/api/admin/hacienda` | Lista / alta |
| GET / PUT / DELETE | `/api/admin/hacienda/{id}` | Detalle / edición / archivado lógico |
| POST | `/api/admin/hacienda/{id}/archivos` | Multipart `archivo`, asociación inmediata |
| DELETE | `/api/admin/hacienda/{id}/archivos/{archivoId}` | Retiro de asociación |
| PUT | `/api/admin/hacienda/{id}/archivos/orden` | `{ "ids": [2, 1] }`, cada ID exactamente una vez |
| GET | `/api/admin/hacienda/{id}/archivos/{archivoId}/contenido` | Descarga autenticada |
| GET | `/uploads/hacienda/{UUID.ext}` | Documento de publicación pública |

`?descargar=true` solicita Content-Disposition attachment en la URL pública; por defecto es inline. Los DTO exponen URLs relativas y metadata, nunca rutas locales. Las modificaciones de documentos bloquean la publicación en DB para serializar asociación y orden.

## Archivos

- JPEG/PNG: máximo 5 MiB y 20 megapíxeles, detección del formato real, decodificación y recodificación. La misma utilidad protege Noticias.
- PDF: máximo 20 MiB, firma inicial `%PDF-` y terminador `%%EOF` en los últimos 1024 bytes. El servidor no interpreta ni ejecuta el PDF. Esto no es un antivirus ni una validación estructural completa: un PDF malicioso puede superar esas comprobaciones. La documentación debe proceder de operadores autorizados; considerar análisis antimalware como siguiente mejora.
- No se confía en MIME ni extensión del cliente. SVG, HTML, JavaScript, XML, Office, ZIP y ejecutables no son formatos admitidos.
- UUID y extensión determinados por el servidor, escritura exclusiva y lectura restringida al directorio, sin enlaces simbólicos como archivo final. El nombre original se limpia y guarda solo como metadata. La descarga utiliza una extensión acorde al formato comprobado.
- Storage separado: `${UPLOADS_DIR}/noticias` y `${UPLOADS_DIR}/hacienda`. Los archivos se sirven con MIME exacto, nosniff, no-store y CSP restrictiva.
- Eliminar una asociación deshabilita su URL, pero conserva el archivo físico para una política posterior de mantenimiento/backup. Un rollback intenta limpiar archivos recién creados; un fallo de filesystem puede dejar un huérfano.

El reverse proxy debe enviar `/uploads/hacienda` al backend. Servir ese directorio directamente permitiría saltarse la validación de estado. Ningún sistema puede retirar copias que un visitante ya haya descargado mientras un documento estuvo publicado.

## Rate limiting del login

`POST /api/auth/login` cuenta todos los intentos por IP en una ventana fija y responde 429 con `Retry-After` al superar el límite. El panel muestra un mensaje específico. No se agregó una dependencia de terceros.

| Variable | Default |
| --- | --- |
| `LOGIN_RATE_LIMIT_ATTEMPTS` | 10 |
| `LOGIN_RATE_LIMIT_WINDOW_SECONDS` | 300 |
| `LOGIN_RATE_LIMIT_MAX_IPS` | 10000 |

La memoria está acotada. Si se llena con ventanas activas, rechaza nuevas IP hasta liberar entradas vencidas, sin expulsar bloqueos vigentes. El contador es local a cada instancia y se pierde al reiniciar. Usa la IP de la conexión; `server.forward-headers-strategy=none` evita confiar en X-Forwarded-For del cliente. Detrás de un proxy todos los visitantes pueden compartir su IP: definir límites y una política de ingress confiable antes de producción. Para varias instancias se necesitará un contador compartido o rate limiting en el perímetro. No es una defensa completa contra ataques distribuidos.

## MySQL y CI

V1 no cambia; V2 solo agrega las dos tablas de Hacienda. Flyway y Hibernate validate siguen activos. CI conserva sus jobs frontend/backend y agrega MySQL 8.4 efímero al backend.

Para ejecutar la prueba aislada en Windows, usar una base MySQL vacía y descartable (nunca una base de producción):

```powershell
$env:MYSQL_VALIDATION='true'
$env:MYSQL_TEST_URL='jdbc:mysql://127.0.0.1:13316/web_ensenada_validation'
$env:MYSQL_TEST_USER='root'
$env:MYSQL_TEST_PASSWORD=''
.\mvnw.cmd '-Dtest=MySqlMigrationTests' test
```

La contraseña vacía corresponde únicamente al contenedor de validación local/CI; no configura credenciales productivas. La prueba comprueba V1/V2, tablas vacías, versión MySQL y EntityManager inicializado. Sin MYSQL_VALIDATION se omite en la suite habitual. No se importaron datos reales.

## Antes de producción

Revisar CI remoto después de publicar la rama; probar V2 en una copia respaldada de la base real; configurar JWT_SECRET sin default, HTTPS, CORS, cuentas operativas, volúmenes y backups de archivos/base; ajustar proxy SPA, límite multipart de 21 MiB y política de IP/rate limiting. Confirmar que el proxy no sirve documentos privados como archivos estáticos y que no usa caché para Hacienda. Completar la URL pendiente de Proveedores.

Deuda: limpieza de huérfanos, antivirus PDF, auditoría editorial, paginación cuando el volumen lo requiera, contador compartido, revocación de otros JWT al cambiar contraseña (vida actual 120 minutos), recuperación de cuentas y object storage. SEO sigue siendo cliente; slugs y SSR quedan fuera de esta etapa. Los documentos históricos de entrega de Prensa describen su alcance anterior.

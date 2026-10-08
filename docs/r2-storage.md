# Almacenamiento portable para Web Ensenada

El backend elige proveedor con `STORAGE_PROVIDER=local|r2`. De forma predeterminada utiliza
`local` con `UPLOADS_DIR`. Para staging configurar el bucket independiente
`ensenada-media-staging`, sin introducir secretos en Git.

Variables (solo backend):

- `STORAGE_PROVIDER=r2`
- `R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com`
- `R2_BUCKET=ensenada-media-staging`
- `R2_ACCESS_KEY_ID=<secret>`
- `R2_SECRET_ACCESS_KEY=<secret>`

Las subidas existentes `POST /api/admin/archivos/noticias` y `/sitio` mantienen el contrato.
Las URLs públicas continúan siendo rutas relativas `/uploads/noticias/<uuid>.jpg`
o `/uploads/sitio/<uuid>.png`; el backend las lee del proveedor configurado.
El proxy público debe dirigir `/api/**` y `/uploads/**` al backend.

**Importante:** los objetos del bucket permanecen privados; no es necesario habilitar
Public Access ni administrar otro dominio CDN para esta primera integración. Los documentos
protegidos de Hacienda y Proveedores continúan usando su flujo de acceso autorizado;
esta primera etapa integra imágenes de Noticias y Sitio solamente.

No se migran automáticamente archivos históricos. Antes de habilitar R2 en un entorno con
archivos locales vigentes, copiarlos al bucket conservando exactamente las claves
`noticias/<filename>` y `sitio/<filename>`.

## Hosting municipal posterior (cPanel)

El código no depende de Easypanel; solo requiere variables y conectividad a R2.
La infraestructura municipal debe proveer Java 21/Spring Boot (proceso persistente),
MySQL, HTTPS y reverse proxy para `/api` y `/uploads`.
Angular SSR también requiere Node.js y un proceso persistente; si el cPanel no los permite,
usar frontend estático con estrategia de prerenderizado/SPA ajustada y mantener la API
Spring Boot en otro servicio compatible. No asumir que un hosting cPanel compartido
ejecuta Spring Boot o SSR automáticamente.

Para producción, usar credenciales independientes y `ensenada-media-prod`.
No colocar Access Key ni Secret Key en el frontend, en el repositorio ni en capturas.
Probar en staging: subir, abrir la URL, reemplazar contenedor y volver a consultar.

# Auditoría de alcance (antes de modificar)

Base inspeccionada: `feature/frontend-backend-admin`, commit `de1bedf`.
`main` local: `c0872a0`; referencia local `origin/main`: `bff56ec`.
No había cambios pendientes. `main` no tiene commits exclusivos respecto de feature.
La feature añade siete commits: `69d1ca0`, `92bf4b9`, `995e9a1`, `7797a6f`, `5424a10`, `c6742d6`, `de1bedf`.
Se conserva esa base completa en `refactor/portal-prensa-scope`. No se hizo fetch, merge ni push.

| Dominio | Código encontrado | Decisión |
| --- | --- | --- |
| Noticias | Entidad, request, repository y controller; dos servicios/modelos Angular; CRUD local y API | Conservar arquitectura API, quitar duplicados y datos demo; estados y archivado |
| Autenticación | JWT, Spring Security, cuenta, bootstrap por entorno, guards e interceptor | Conservar y limitar a SUPER_ADMIN/PRENSA |
| Usuarios | Lista, creación y roles; DTO sin hashes | Conservar, añadir activar/desactivar; proteger superadministradores |
| Hacienda/documentos | DocumentoMunicipal, upload genérico, páginas y panel, servicio local/API | Retirar almacenamiento y CRUD; conservar acceso público como página de enlace externo |
| Agenda | Entidad, controlador, servicio, admin y componente consumido por Home | Retirar componente de Home junto con el módulo |
| Áreas | Entidad, inicializador, API, servicio con datos estáticos y admin | Retirar CMS y consumidores dependientes |
| ContenidoSitio | CRUD general, footer/contacto con caché local; hero no consumía datos editables | Simplificar a configuración institucional versionada, sin formulario administrativo |
| Navegación | Boletín duplicado en navbar/footer/accesos; Hacienda y proveedores internos | Centralizar destinos; no hay URL externa verificada para Hacienda/Proveedores |
| Dashboard | Consultaba Hacienda aun para periodistas | Resumen exclusivo de noticias |
| Repositorio | error-backend.txt trackeado; documentos municipales bajo assets; tests generados | Retirar artefactos fuera de alcance y sustituir tests por comportamiento |

Dependencias públicas revisadas antes de retirar módulos: Home → AgendaMunicipal → AgendaService; Footer y Contacto → ContenidoSitioService; dashboard → HaciendaService; navbar/accesos → páginas municipales. El hero y la información institucional de Home se conservan. Las rutas de Hacienda y Proveedores permanecen como accesos externos sin datos propios.

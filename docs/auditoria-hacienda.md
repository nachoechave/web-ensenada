# Auditoría previa: Hacienda

Base remota verificada con fetch: `origin/refactor/portal-prensa-scope`, `168450a581feff6a59019613c05662a94bd653b4`, idéntica a la copia local limpia. Rama nueva: `feature/hacienda-publications`.

Se revisaron SecurityConfig, DTO/controlador/entidad de usuarios, V1, upload y servidor de imágenes de Noticias, rutas/guards/interceptor Angular, dashboard, navegación, usuarios, tests y CI.

Se conserva: JWT sin default, issuer, roles/activo en DB, denyAll, contraseñas 12–72, CORS, API relativa, archivos UUID, Flyway validate y suite de Noticias. V1 no cambia.

Reutilización: EstadoPublicacion; infraestructura de decodificación/recodificación de imágenes extraída a una utilidad; layout, guards y configuración de API. El dominio Noticias no conoce Hacienda.

Nuevo dominio: PublicacionHacienda + ArchivoHacienda, servicios/DTO/API propios. Upload asociado directamente a una publicación; retiro y orden por IDs. Archivos públicos únicamente si la publicación está PUBLICADA; preview administrativo autenticado. Sin recuperar DocumentoMunicipal, ContenidoSitio, AreaMunicipal ni EventoAgenda.

Impacto: Hacienda deja de ser enlace externo, vuelve a tener rutas propias. Dashboard y usuarios reconocen un único rol operativo PRENSA/HACIENDA. Nueva V2 aditiva. Rate limiting configurable por IP antes del login, con memoria acotada. CI existente agrega validación MySQL con base efímera.

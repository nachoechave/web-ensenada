# Resultado local — 27/11/2026

| Validación | Resultado |
|---|---|
| PHPUnit con SQLite y dependencias actualizadas | 167 tests / 504 assertions aprobados |
| Regresión de autorización temporal de staging | 3 tests / 31 assertions aprobados |
| Angular | 74 tests aprobados; build correcto |
| HTTP Apache/PHP/MariaDB | 131 comprobaciones aprobadas |
| Aislamiento, cierre, revisión por IP y rollback | 23 comprobaciones aprobadas |
| EXIF, transparencia, corrupción, 30 MiB y galería de seis | 16 comprobaciones aprobadas |
| Chromium HTTPS con Angular real | 18 comprobaciones aprobadas; sin errores JavaScript |
| ZIP: instalación repetida, actualización y rollback Angular | 15 comprobaciones aprobadas |
| Dependencias de producción | 76 paquetes; auditoría Composer sin avisos |
| Plataforma local | PHP 8.3.36, Apache 2.4.68, MariaDB 10.11.19, 256M/32M/40M; platform.php aprobado |
| Esquema y mínimos por tabla | 12 tablas y trigger verificados; FK y rechazo de segunda inicialización comprobados |
| Perfil cpanel-dml en producción | Rechazado en HTTP; recuperación al restaurar mínimos por tabla |
| Respaldo sintético | SQL y dos TAR con SHA verificados; SQL restaurado en otra MariaDB sin red, checksums iguales; 6008 archivos privados extraídos con SHA idéntico, excluyendo logs activos de la comparación |

Los JPEG/PNG/WebP 4000×2250 cargados desde el formulario real quedaron en 1920×1080. Se publicó la noticia y se abrió su detalle en 1920/1366/390 px. También se cargó un WebP al Hero y se guardó el CMS. No se modificó su diseño.

La instalación final incorpora una corrección necesaria de Angular V13: su formulario de Noticias todavía rechazaba WebP y fotos mayores de 5 MiB. Solo cambian la validación, sus mensajes y el bundle correspondiente. Se conservan estilos, Hero, Navbar y API.

Las pruebas usan exclusivamente datos sintéticos y contenedores propios. WordPress y los subdominios se representan mediante fixtures; no se probaron las aplicaciones reales de iWinds. El TLS local usa un certificado de prueba. No se realizaron conexiones al hosting, publicación, cambios de usuarios reales o bases municipales.

**La publicación permanece bloqueada** hasta aprobar grants mínimos reales y base independiente, datos oficiales, respaldo municipal restaurable, cuota, handler PHP localizado y recursos web efectivos, aceptación administrativa y comparación funcional de servicios. Consultar README.md para instalación gradual y reversión. El build estático no resuelve la decisión pendiente de SSR/SEO dinámico.

# Transferencia de base existente: resultados locales — 27/11/2026

Rama `feat/iwinds-production-cutover`, PR 85. No hubo conexión al hosting, acceso a datos reales, activación remota ni cambios en Angular, Spring Boot, WordPress o el .htaccess principal del servidor.

| Validación ejecutada | Resultado |
|---|---|
| PHPUnit, SQLite, PHP 8.3.36 | 177 tests / 517 assertions aprobados |
| Regresión del período administrativo de staging | 3 tests / 31 assertions aprobados |
| Angular existente | 74 tests aprobados; build correcto, main-5AVB2EQE.js / styles-5MXFBPYB.css |
| Retiro de grant amplio, perfil por tabla y rechazo de cpanel-dml | Aprobado en MariaDB 10.11.19 descartable, cuentas locales sin privilegios globales |
| Pre-corte con base única | 26 comprobaciones aprobadas: autorización, aparcado, credencial anterior inválida, conexiones terminadas, rollback de transacción previa, claves conservadas, archivos y revisión incompleta bloqueada |
| Activación autorizada | 13 comprobaciones aprobadas: revisión, restauración, sesiones revocadas, passwords intactos, cachés/rutas y aislamiento de staging |
| HTTP real Apache 2.4.68 / PHP 8.3.36 / MariaDB 10.11.19 | 132 comprobaciones aprobadas de autenticación, roles, revocación, Usuarios, Noticias, Hacienda, Proveedores, CMS, documentos y errores |
| Cierre, permisos, preview y reescritura/rollback selectivos | 25 comprobaciones aprobadas; candidato obsoleto rechazado, cambio concurrente preservado, WordPress y subdominios conservados |
| Angular real / Chromium / HTTPS | 18 comprobaciones aprobadas, cero errores JavaScript; noticia completa con JPEG 4000×2250, PNG, WebP, CMS y 1920/1366/390 px |
| Optimización y seguridad de imágenes | 22 comprobaciones aprobadas: orientación EXIF, transparencia, 1920 px, 30 MiB, corrupción, bomba de dimensiones, ausencia de parciales, galería de seis y referencias CMS |
| Aceptación del script distribuido, con TLS validado | 120 comprobaciones aprobadas + 6 de preparación sintética; staging responde 503 con Basic válido para API y archivos, producción responde con contratos públicos, documentos privados 404 |
| Recuperación exclusiva de staging | 27 comprobaciones aprobadas: drenaje, respaldo reciente obligatorio, nueva rotación/revocación, conservación de escrituras y archivos nuevos, Basic + roles, Origin, expiración, cierre y nuevo corte sin reutilizar aprobaciones |
| Respaldo y restauración aislados | SHA de SQL y cuatro TAR; esquema completo de 12 tablas, huella HMAC de todas las filas/campos, archivos idénticos y trigger probado mediante transacción reversible |
| Restaurador reutilizable `restore-local.ps1` | Aprobado por segunda restauración del respaldo sintético, contenedor/volumen tmpfs y red interna propios, sin puertos publicados |
| Incremento real sobre el ZIP previamente instalado | Instalación repetida, rechazo de código distinto antes de sobrescribir, rollback de código/protección y reinstalación; .env y claves conservados |

Total: **180 tests PHP, 74 tests Angular y 390 comprobaciones de los scripts**, además de la verificación de archivos del instalador y las restauraciones. Los contadores son aserciones, no cantidad de endpoints distintos. Pint y `git diff --check` sin errores. La compilación conserva las advertencias previas de presupuesto de JS/CSS; no se cambió el frontend para este incremento.

## Reproducción

`tests/SharedDatabase/run.ps1` usa solo el proyecto Docker nombrado `ensenada-production-cutover-tests`, MariaDB 10.11.19 y almacenamiento tmpfs, sin puertos ni volúmenes de otros proyectos. Requiere el ZIP anterior con SHA 3d939939…24d68b, las imágenes PHP/Chromium locales verificadas, vendor obtenido del lock y el build Angular existente. Construye el incremento, instala sobre el baseline, transfiere una única base, restaura el respaldo en otro contenedor, ejecuta aceptación y termina con ambos backends cerrados después de un nuevo corte. Las cuentas/datos de `tests/` son exclusivamente sintéticos; no se distribuyen como aprovisionamiento.

El ensayo simula el transcurso de 125 segundos únicamente desde el harness excluido del ZIP; el comando real no ofrece ningún parámetro para saltar esa espera. El test Browser verifica la noticia persistida mediante el contexto autenticado después de la navegación para evitar una carrera de Chromium al descartar el cuerpo del POST.

## Límite de evidencia y bloqueos de publicación

La base real de aproximadamente 400 KB no se inspeccionó ni se descargó. Su contenido oficial debe aprobarse manualmente y **su propio** respaldo debe restaurarse localmente con `restore-local.ps1`; una prueba sintética no satisface esa condición. El activador exige prueba ligada al backup_id y a las huellas actuales. No se admiten cpanel-dml, credenciales compartidas con otros sistemas, escritores externos, datos públicos no revisados, referencias/archivos faltantes ni activación simultánea de los backends.

Confirmar en iWinds los grants por tabla, plugin que admita rotación propia, cliente dump compatible, cuota/espacio, PHP web 32M/40M/256M y HTTPS. Si el grantor disponible no permite retirar el grant amplio y conceder los permisos mínimos, **bloquear producción**; no elevar privilegios ni debilitar el perfil. Exigir staging 503 con Basic válido después del corte y aceptación administrativa real antes de abrir tráfico. La misma cuenta Unix no proporciona aislamiento frente a una ejecución remota de código que comprometa toda la cuenta cPanel.

Guía operativa y reversión: [SHARED-DATABASE.md](SHARED-DATABASE.md). Nada en estos resultados autoriza el despliegue automático.

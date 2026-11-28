# Segundo incremento: perfiles de producción — 27/11/2026

Rama `feat/iwinds-production-cutover`, PR 85. No se conectó a iWinds, no se cambiaron esquemas/datos reales ni Angular, Spring Boot, WordPress, DNS, correo, otros subdominios o bases. El ZIP se aplica sobre el primer incremento instalado SHA 7fde8cd1…f9183da y conserva claves, configuración, almacenamiento y puente preparado.

## Pruebas ejecutadas localmente

| Validación | Resultado |
|---|---|
| PHPUnit SQLite / PHP 8.3.36 | 193 tests, 536 assertions aprobados |
| Regresión del período administrativo de staging | 3 tests, 31 assertions aprobados |
| Angular sin cambios | 74 tests y build aprobados; main-5AVB2EQE.js / styles-5MXFBPYB.css; advertencias previas de presupuesto de JS/CSS |
| Perfil per-table predeterminado | Ensayo completo aprobado; cpanel-dml rechazado sin selección/aceptación |
| Perfil cpanel-dml | Ensayo completo con MariaDB 10.11.19, runtime con exactamente cuatro DML y esquema con ALL únicamente sobre la base literal; ninguna cuenta tiene GRANT OPTION |
| Cuentas efectivas | ensenada_web_app@localhost y ensenada_web_schema@localhost comprobadas por PDO sobre socket compartido únicamente entre los contenedores propios |
| Validación negativa de grants | Rechazo de privilegios globales, DDL, ALL en runtime, permisos faltantes/adicionales, delegación, roles SQL, otras bases, host comodín y guion bajo sin escapar; per-table conserva sus 12 alcances |
| Instalación | Baseline completo + primer incremento inmutable + segundo incremento; repetición, rechazo de archivos distintos, reversión y reinstalación, conservando .env y control.key |
| Autorización alternativa | Template sin aceptar y falta de bandera rechazados; recibo alterado rechazado; no hay activación sin respaldo restaurado ni copia externa declarada del checkpoint |
| Pre-corte | 26 comprobaciones aprobadas: aparcado, exclusión, credencial vieja rechazada, cierre de conexión/transacción anterior, conservación de claves, copia sin sobrescrituras y revisión de datos |
| Activación con cpanel-dml | 20 comprobaciones aprobadas, incluidas restauración obligatoria, checkpoint y bandera adicional; schema-check y cachés correctos |
| Contratos HTTP y módulos | 132 comprobaciones por perfil, en Apache 2.4.68/PHP 8.3.36/MariaDB 10.11.19; Autenticación, Usuarios, Noticias, Hacienda, Proveedores, CMS y documentos |
| Permisos/cierre/Apache | 25 comprobaciones con per-table; 27 con cpanel-dml, demostrando DML directo posible sobre usuarios/auditoría/mutexes y falta real de delegación en ambas cuentas |
| Auditoría compensatoria | 15 comprobaciones: alteración/borrado detectados y API cerrada, bloqueo persistido fuera de SQL, prohibición de retroceder checkpoints y de firmar datos alterados; reparación solo en el harness sintético excluido del ZIP |
| Imágenes | 22 comprobaciones por perfil: EXIF, transparencia, 1920 px, 30 MiB, corrupción, límites, seis imágenes y referencias CMS |
| Chromium real | 18 comprobaciones por perfil, HTTPS, formularios completos, JPEG 4000×2250/PNG/WebP, CMS y 1920/1366/390 px; cero errores JavaScript |
| Aceptación HTTPS | 120 comprobaciones + 6 de preparación por perfil, validando certificado de la CA local, contratos, archivos privados y staging 503 incluso con Basic válido |
| Rollback | 27 comprobaciones por perfil: conserva escrituras/archivos nuevos, exige respaldo actual, rota/revoca, Basic + sesión y roles, expiración/cierre, nuevo corte con nuevas aprobaciones; staging anterior sin deploy/production soportado |
| Restauración | SQL y cuatro TAR restaurados en MariaDB descartable independiente; doce tablas, todas las filas/campos, archivos y trigger reversible idénticos; sin importar SQL en el hosting |

El recorrido cpanel-dml suma **423 comprobaciones**; per-table suma **393**. Se agregan las comprobaciones del ZIP exacto y sus componentes. Sintaxis PHP, Pint y `git diff --check` verificados. Los contadores son aserciones, no cantidad de endpoints.

## Reproducibilidad y límites

Ejecutar `tests/SharedDatabase/run.ps1 -Profile per-table` y `-Profile cpanel-dml` de forma secuencial. Usan solo el proyecto `ensenada-production-cutover-tests`, sin puertos publicados, datos tmpfs y un volumen **nuevo y propio** de socket también tmpfs, retirado al terminar; no usan volúmenes ajenos. Se conserva una copia sintética de los respaldos/resultados en output ignorado. La espera de 125 segundos se simula exclusivamente desde el harness, nunca mediante una opción del comando desplegable.

Los tests representan una autorización ficticia del fixture. No son la aceptación institucional, no clasifican contenidos reales ni acreditan custodia externa del hosting. El validador solo admite el ámbito literal escapado: un grant `ensenada_web.*` sin escapar puede abarcar otros nombres y debe bloquearse. No se añaden permisos para hacerlo pasar.

El checkpoint verifica y captura una única lectura SQL consistente. El estado firmado retiene el último checkpoint, impide retroceder la observación y liga activación/riesgo/corte. La auditoría **sigue siendo modificable con DML**; los eventos introducidos/retirados entre observaciones y los eventos falsos nuevos pueden escapar a la detección. Si se compromete el UID o control.key, la integridad local deja de ser confiable: se necesita la copia externa y sus hashes custodiados independientemente. No se declara WORM ni equivalencia con mínimos por tabla.

Antes de publicar siguen siendo obligatorios: aceptación formal de [CPANEL-DML-RISK.md](CPANEL-DML-RISK.md), grants reales exactos, esquema/contenidos aprobados, copia externa efectiva, restauración del respaldo real, límites web/cuota y aceptación HTTPS/administrativa. La base real no fue inspeccionada ni descargada. Guía: [CPANEL-DML.md](CPANEL-DML.md). Nada se instala o activa remotamente.

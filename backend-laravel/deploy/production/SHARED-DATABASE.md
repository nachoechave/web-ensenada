# Transferencia exclusiva de ensenada_web — incremento PR 85

Este incremento reemplaza la estrategia de base separada. Solo usa `ensenada_web`, `ensenada_web_app` y el usuario de esquema existente `ensenada_web_schema`. No instala de nuevo Laravel, Angular ni vendor, no regenera APP_KEY/JWT, no importa SQL en iWinds, no modifica WordPress ni el .htaccess principal. El backend instalado debe coincidir con el ZIP anterior SHA-256 `3d9399393618022b89371cf6a98c08476f4406ea1c92bdc7e4915bc29624d68b`; una diferencia detiene el instalador antes de sobrescribir código. No forzar este control.

## Preparación sin tráfico

Mantener bloqueado el directorio web, los marcadores ausentes y la autenticación Basic de prueba. Confirmar que **ningún otro sistema** usa `ensenada_web_app`; la rotación de su contraseña invalidará todas sus conexiones. Desactivar cron, colas, herramientas administrativas y procesos manuales que puedan escribir en esa base. No cambiar PHP global, DNS, correo u otras bases. Las cuentas de esquema tampoco deben tener escritores concurrentes. La CLI requiere PHP 8.3, PDO MySQL, ZIP y las extensiones ya verificadas, `tar` y un cliente `mariadb-dump`/`mysqldump` compatible con las opciones usadas. Si el cliente no soporta exportación sin DROP, bloquear, no quitar el control.

Extraer **fuera** de public_html en `/home/ensenada/iwinds-shared-database`. Comprobar primero el SHA del ZIP contra el archivo adjunto y luego su manifiesto mediante el instalador.

```sh
umask 077
cd /home/ensenada
sha256sum -c web-ensenada-iwinds-shared-database.zip.sha256
mkdir -m 700 iwinds-shared-database
unzip -q web-ensenada-iwinds-shared-database.zip -d iwinds-shared-database
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
P=/home/ensenada/backend-laravel-produccion
H=$P/deploy/production/handover.php
S=/home/ensenada/respaldos_privados/schema.cnf
mkdir -p -m 700 /home/ensenada/respaldos_privados
"$PHP" /home/ensenada/iwinds-shared-database/update.php install-private
"$PHP" /home/ensenada/iwinds-shared-database/update.php prepare-web
"$PHP" "$H" status
```

`install-private` es idempotente y conserva .env byte por byte. `prepare-web` actualiza únicamente el puente PHP de producción y su protección interna: **no activa rutas**, no cambia el índice Angular ni el .htaccess principal. Antes de cualquier transferencia puede revertirse el código con `update.php rollback-code`; después del corte usar la recuperación de datos descrita abajo, nunca reinstalar un paquete viejo que desconozca la exclusión.

Crear `schema.cnf` con un editor privado, modo 0600, sección `[client]`: `host=localhost`, `port=3306`, `user=ensenada_web_schema`, `database=ensenada_web`, y la contraseña existente (sin mostrarla en comandos, historial o repositorio). No compartir este archivo ni incluirlo en el paquete. Se necesita SELECT y TRIGGER para inspeccionar/exportar; la cuenta de esquema no se usa por Laravel. Conservar el DEFINER existente del trigger y sus permisos de revocación; no recrearlo automáticamente.

Antes de ajustar permisos, cerrar el staging con el comando ya instalado (no modifica usuarios ni contraseñas):

```sh
"$PHP" /home/ensenada/backend-laravel/deploy/iwinds/provision-staging.php suspend-staging-api
```

Aplicar/revisar manualmente `grants-production.sql` sobre **el usuario existente**, sustituyendo privilegios amplios por los mínimos indicados, desde una sesión autorizada. El script no contiene contraseñas, no crea cuentas ni bases. Su primer REVOKE retira exclusivamente el grant amplio existente sobre ensenada_web.*; luego concede los permisos por tabla. Requiere una sesión autorizada con capacidad real de revocar/conceder esos privilegios. Cerrar staging con su herramienta instalada y confirmar la cuenta dedicada antes de aplicarlo; no se ejecuta desde el instalador. Si la cuenta ya coincide exactamente con el perfil mínimo, no repetir el REVOKE de un grant inexistente: verificar y continuar. Si ensenada_web_schema no tiene GRANT OPTION y cPanel no permite los grants por tabla, no elevar cuentas ni omitir verificaciones: la publicación queda bloqueada. Si cPanel no permite obtener exactamente ese perfil, **no hay aprobación de producción**: `cpanel-dml` sigue rechazado. No elevar a ALL, PROCESS, KILL global, GRANT OPTION ni DDL para facilitar el corte. Comprobar también que no haya roles SQL heredados. Staging sintético debe probarse nuevamente si se retiran sus permisos amplios antes del corte.

```sh
"$PHP" "$H" audit-live --schema-config="$S" --out=/home/ensenada/respaldos_privados/revision-provisional.json
```

La auditoría compara 12 tablas, columnas/tipos, collations, índices, FK, CHECK y el trigger con el esquema histórico verificado de MariaDB 10.11; solo ignora contadores AUTO_INCREMENT y el nombre DEFINER, no su funcionamiento. Revisa huérfanos y no repara diferencias. El informe privado incluye contenidos/relaciones y huellas, nunca hashes de contraseña. Los indicios de datos sintéticos **no son prueba** de que algo sea oficial. Una persona autorizada debe revisar cada noticia, usuario activo, singleton completo (hero, áreas, agenda, footer, contactos/enlaces), publicación, documento e imagen. No se inventan datos oficiales ni se borran fixtures automáticamente.

## Corte exclusivo, todavía sin publicación

Las autorizaciones siguientes son deliberadas: pausan staging, rotan **solo la contraseña MySQL de ejecución** y cierran sus conexiones anteriores. No cambian contraseñas de usuarios Laravel. Staging se conserva íntegro en `/home/ensenada/web-db-transfer/staging-backend`; su ruta original queda como placeholder sin artisan ni vendor, y el puente responde 503. Sus viejos marcadores no pueden volver a abrirlo. Las APP_KEY/JWT permanecen independientes. Se conservan contraseñas anteriores en respaldos privados para recuperación, nunca en el ZIP.

```sh
"$PHP" "$H" pause-stage --authorize-staging-pause --confirm-exclusive-account-use --confirm-no-background-writers
"$PHP" "$H" rotate-production --authorize-mysql-password-rotation
sleep 125
"$PHP" "$H" copy-storage --confirm-account-quota
"$PHP" "$H" audit --schema-config="$S" --out=/home/ensenada/respaldos_privados/revision-corte.json
"$PHP" "$H" backup --schema-config="$S" --out=/home/ensenada/respaldos_privados/corte-01
```

No ejecutar otros scripts del backend aparcado ni restaurar su antigua credencial. Comprobar por HTTPS con Basic válido que staging responde 503. Si OPcache conserva código antiguo sin revalidar, reiniciar únicamente el pool PHP de esta aplicación mediante la opción autorizada del hosting y volver a comprobar; nunca cambiar PHP global ni habilitar producción para probar. El bloqueo de propiedad se mantiene durante toda solicitud/control de producción y recuperación; el corte espera las solicitudes propias en curso. Para solicitudes del staging antiguo, además del aparcado, la rotación y KILL de conexiones de **la misma cuenta** impiden escrituras pendientes; se exige confirmar que no existen otros escritores. La rotación puede reanudarse tras interrupción con el mismo comando, sin imprimir el secreto pendiente. Se conservan archivos originales y se copian bytes con SHA, sin symlinks/hardlinks, sin sobrescribir colisiones ni usar 777. La cuota real debe verificarse además del espacio libre del sistema.

## Restauración aislada obligatoria

Para verificar el **respaldo real descargado**, desde el checkout local de esta rama, en `backend-laravel`, con Docker y la imagen PHP 8.3 local verificada usada en las pruebas (parámetro `-PhpImage`), ejecutar:

```powershell
pwsh -NoProfile -File deploy/production/restore-local.ps1 -Backup 'C:\Respaldos\corte-01' -Proof 'C:\Respaldos\restore-proof.json'
```

Se requieren el código confiable del repositorio y `output/dependencies/vendor` generado previamente del lock (o indicar `-VendorPath`). El helper valida SHA antes de importar, crea **solo en Docker local** una base vacía y cuentas temporales con contraseñas aleatorias, red interna sin puertos y almacenamiento tmpfs; elimina únicamente sus contenedores/red/archivo temporal al terminar. No usa la contraseña real del usuario de esquema. Conserva el respaldo y la prueba, no ejecuta PHP restaurado y nunca se conecta al hosting. `tests/SharedDatabase/run.ps1` es el ensayo sintético del desarrollo, no el restaurador de un dump real.

Descargar de forma privada `corte-01` completo (SQL, cuatro TAR y backup.json), custodiarlo cifrado fuera del hosting. **Contiene contraseñas, claves, hashes y datos personales**: nunca publicarlo ni adjuntarlo a PR. Comprobar todos los SHA de `backup.json`. Restaurar únicamente en Docker local sin puertos ni volumen persistente, MariaDB 10.11.19, hostname `ensenada-shared-restore-disposable`. Crear allí una base vacía `ensenada_web` y la cuenta sintética `ensenada_web_schema`; el DEFINER del trigger original debe existir en ese entorno aislado y poder revocar sesiones. Importar el SQL con el cliente de ese contenedor, nunca con el cliente del hosting. No usar `migrate`, `migrate:fresh`, `db:seed`, DROP ni TRUNCATE. El ensayo reproducible local está en `tests/SharedDatabase/run.ps1`; sus cuentas son exclusivamente sintéticas y no deben copiarse a iWinds.

Ejecutar en un contenedor PHP 8.3 con dependencias verificadas, conectado solo al contenedor descartable, montando el código actualizado en `/app`, el respaldo en `/backup:ro`, salida privada en `/proof`, y `/restore` como tmpfs vacío:

```sh
# Dentro del contenedor aislado; RESTORE_SCHEMA_PASSWORD se entrega por archivo/entorno privado local.
ENSENADA_ISOLATED_RESTORE=authorized-disposable php /app/deploy/production/verify-restore.php /backup /proof/restore-proof.json
```

El verificador exige Docker, hostname aislado, los 12 esquemas, huella de **todas las filas y campos** (incluidas sesiones y hashes, sin exponerlos), todos los archivos, claves de control recuperadas y trigger mediante transacción reversible. Extrae sin ejecutar PHP restaurado, rechaza rutas inseguras/enlaces y genera una prueba ligada al respaldo concreto. Subir solo `restore-proof.json` a respaldos_privados con modo 0600. La prueba local sintética del desarrollo **no sustituye este ensayo del respaldo real**.

Copiar `revision-corte.json` a `revision-aprobada.json` privado. En su objeto `review`, editar manualmente cada `classification` a `APPROVED`, `SYNTHETIC` o `PRIVATE-HISTORICAL` y completar `reason`, preservando huellas/epoch. Todo registro o archivo visible y usuario activo exige APPROVED. Para retirar datos sintéticos públicos, detenerse y preparar una corrección autorizada independiente; esta herramienta no la realiza. No aprobarlos masivamente. No cambiar rutas de imágenes/IDs. Referencias absolutas públicas a prueba bloquean el corte y requieren corrección autorizada. Las URLs relativas históricas siguen funcionando.

```sh
"$PHP" "$H" approve-data --schema-config="$S" --review=/home/ensenada/respaldos_privados/revision-aprobada.json --authorize-reviewed-data
"$PHP" "$H" attest-restore --proof=/home/ensenada/respaldos_privados/restore-proof.json --authorize-restore-proof
"$PHP" "$P/artisan" config:cache
"$PHP" "$P/artisan" route:cache
"$PHP" "$P/deploy/production/control.php" check
```

## Activación posterior: autorización humana, nunca automática

Requiere revisión oficial, respaldo/restauración reales, privilegios mínimos, independencia de claves, límites **PHP web** 32M/40M/256M, HTTPS/certificado y aceptación funcional. `activate` revoca todas las sesiones de staging mediante incremento de token_version y retiro de auth_sessions en una transacción **explícitamente autorizada**; no modifica passwords ni registros editoriales. Antiguas cookies quedan inválidas también por claves independientes. Cualquier diferencia del esquema/datos/archivos respecto de la revisión bloquea la activación.

```sh
"$PHP" "$H" activate --schema-config="$S" --authorize-session-revocation --authorize-production
```

El comando no toca Apache. Mantener el directorio web restringido hasta decidir el corte de tráfico. Conservar la estrategia selectiva de `cutover.php`: generar candidato sobre el .htaccess actual, revisar que solo incorpora el fragmento para los hosts exactos y rutas municipales, verificar SHA/recibo y aplicar **solo con autorización independiente**. No reemplazar todo el .htaccess ni afectar WordPress, turnos, subdominios o PHP global. No incluir el .htaccess principal en este incremento.

```sh
"$PHP" "$P/deploy/production/cutover.php" candidate /home/ensenada/public_html/.htaccess /home/ensenada/respaldos_privados/candidate.htaccess
sha256sum /home/ensenada/public_html/.htaccess
# Revisar diff; sustituir SHA_BASE por el SHA actual aprobado, sin usar un valor antiguo.
"$PHP" "$P/deploy/production/cutover.php" apply /home/ensenada/public_html/.htaccess /home/ensenada/respaldos_privados/candidate.htaccess SHA_BASE --authorized
"$PHP" "$P/deploy/production/acceptance.php" --news=SLUG_APROBADO --hacienda=ID_APROBADO --image=/uploads/noticias/UUID.webp --provider=/uploads/proveedores/UUID.pdf --private-document=/uploads/hacienda/UUID.pdf --staging-basic-file=/home/ensenada/respaldos_privados/staging-basic.txt
curl -I https://prueba.ensenada.gov.ar/api/noticias
```

Crear staging-basic.txt con editor privado (una línea usuario:contraseña de Basic, modo 0600, nunca en el repositorio o argumentos). El verificador exige el propietario de base correcto, el perfil mínimo y respuestas 503 de staging con Basic válido. Sin credenciales válidas no aprueba el aislamiento. Staging no debe devolver JSON productivo. Verificar además Noticias/Hacienda/Proveedores/CMS, login individual, roles, CSRF, carga JPEG/PNG/WebP alta resolución a 1920px, publicación, seis fotos y descarga privada/pública. Un HTTP 200 aislado no aprueba. La aceptación no hace cargas/escrituras sobre datos reales. Mantener cerrada la API si faltan recursos aprobados para probar.

## Cierre y rollback sin pérdida de escrituras

```sh
# Cierre inmediato, espera peticiones en curso, no necesita conexión MySQL:
"$PHP" -n "$P/deploy/production/control.php" close
# Para reversión de propietario:
"$PHP" "$H" pause-production --authorize-production-pause
"$PHP" "$H" backup --schema-config="$S" --out=/home/ensenada/respaldos_privados/reversion-01
```

Antes de recuperar staging, validar/custodiar este respaldo **nuevo**, incluyendo los archivos creados en producción. La recuperación no importa ningún dump: conserva la misma base con todas las escrituras confirmadas. Copia archivos nuevos de producción al almacenamiento conservado, sin borrar/reescribir históricos. Rota otra vez la contraseña MySQL, invalida conexiones y sesiones de producción, conserva APP_KEY/JWT originales de staging e integra el control de exclusión sin quitar Origin/Fetch Metadata/CSRF/roles. Exige Basic real de Apache en cada solicitud y tiene vencimiento de 1–60 minutos. No utiliza el antiguo modo sintético prolongado con datos productivos.

```sh
"$PHP" "$H" recover-stage --minutes=30 --authorize-recovery-with-current-data --authorize-mysql-password-rotation --authorize-session-revocation
"$PHP" "$H" status
# Cerrar recuperación inmediatamente:
"$PHP" "$H" close-recovery --authorize-production-pause
```

La rotación y el cierre de conexiones afectan solo la cuenta dedicada confirmada; no se requieren permisos de administración global. Ante un fallo, ambos backends permanecen cerrados; conservar estado, pendientes y respaldos y reejecutar la misma operación. No editar el estado firmado. No restaurar contraseñas antiguas, marcadores ni .env de un dump sobre un propietario activo. Para volver a producción desde recuperación, iniciar un corte nuevo con `pause-stage`, revisar datos, copiar, respaldar y repetir las aprobaciones. No hay renovación implícita de recuperación.

Para volver el tráfico a WordPress usar únicamente `cutover.php rollback /home/ensenada/public_html/.htaccess RESPALDO_ORIGINAL SHA_ACTUAL --authorized`, con el diario original intacto; rechaza cambios posteriores. No afecta los datos. El rollback de tráfico y el de propietario son independientes. El subdominio de prueba se conserva, pero solo recibe datos productivos durante una recuperación expresamente autorizada, exclusiva y protegida.

## Bloqueos y límites

No se accedió a la base real de 400 KB ni se puede certificar su contenido/restauración sin ejecutar los controles allí. Una diferencia de esquema, archivo faltante/colisión, dato público sin aprobación, cuota, permisos cPanel insuficientes o restauración no demostrada bloquean. El mismo UID Unix puede leer ambos directorios: una ejecución remota de código que comprometa toda la cuenta cPanel no se aísla con Laravel ni con esta exclusión; requeriría separación de cuentas/hosting. La cuenta de esquema puede modificar datos y debe permanecer fuera de procesos web y cerrada durante el corte. El perfil mínimo no protege contra todas las operaciones legales de un atacante que comprometa las credenciales runtime; mantiene auditoría sin UPDATE/DELETE, usuarios sin DELETE y mutex sin borrado. Mantener copias externas recuperables.

MariaDB permite [rotación de la contraseña propia](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/set-password) y [cierre de conexiones propias](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/kill) sin privilegios globales. Si el plugin de autenticación instalado no permite esas operaciones, detenerse; no elevar permisos para forzarlas.

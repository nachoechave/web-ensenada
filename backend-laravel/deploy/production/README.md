# Publicación controlada en iWinds

Estado: paquete para instalación privada y revisión restringida. La publicación pública requiere aprobar las fases siguientes. No se conectó al hosting ni se modificó WordPress, Angular, Spring Boot o staging.

## A. Procedencia y alcance

Se revisaron `origin/main` (`fb59b08`), las ramas locales, el historial de Laravel y `web-ensenada-publicacion`. Ambos proyectos apuntan a `nachoechave/web-ensenada`; `main` contiene Angular y Java, sin un backend Laravel completo versionado. El backend existente estaba disponible en el workspace de la migración. Esta rama incorpora ese código y el incremento de lectura pública del commit `f8e9680` (PR 70), sin reconstruir módulos. No se toman secretos, almacenamiento ni bases locales.

Se mantienen las 41 rutas, roles, DTO, bloqueo de cuentas, revocación por trigger, transacciones, validación documental e imágenes optimizadas. Producción usa cookies HttpOnly/Secure/Strict y rechaza Bearer. Devuelve `token: ""` como el staging instalado, compatible con Angular. El almacenamiento privado se resuelve por entorno, incluido el espacio temporal y la política privada de ImageMagick. Las imágenes no publicadas requieren la cookie cifrada de previsualización y permisos vigentes. Los archivos de Hacienda y Proveedores siempre pasan por sus controladores.

Producción valida origen HTTPS exacto, ruta/método explícitos, solicitud original, marca interna de Apache, Origin y Fetch Metadata para escrituras y ausencia de encabezados de proxy/manipulación. La cuenta de ejecución se verifica en cada arranque HTTP. No se acepta `cpanel-dml`, delegación, roles SQL, permisos globales o DDL. El cierre y los respaldos esperan las solicitudes en curso mediante un bloqueo compartido/exclusivo. No hay ventanas temporales de staging para la administración de producción.

## B. Instalación privada sin tráfico

Subir **solo el ZIP y su SHA-256** fuera de `public_html`, por ejemplo a `/home/ensenada/paquetes-produccion`. Angular V13 ya está copiado y se conserva. Se detectó una corrección indispensable: su formulario de Noticias todavía rechazaba WebP y fotos de más de 5 MiB. El paquete incluye únicamente el nuevo índice y `main-5AVB2EQE.js` para admitir JPEG/PNG/WebP hasta 30 MiB, conservar seis fotos y mostrar los mensajes correctos. No cambian Hero, estilos, Navbar ni API. Se conservaron `main-AGXYZRPY.js` y `styles-5MXFBPYB.css`; el instalador verifica sus SHA y el índice V13 antes de cambiarlo. Si no coinciden con `angular/release.json`, bloquear y revisar la diferencia, sin forzar la sobrescritura.

Desde la terminal de cPanel, como usuario `ensenada`, usar PHP localizado. Los ejemplos siguientes no incluyen contraseñas.

```sh
umask 077
cd /home/ensenada/paquetes-produccion
sha256sum -c web-ensenada-iwinds-production.zip.sha256
unzip web-ensenada-iwinds-production.zip -d revision-20261127
cd revision-20261127
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP install.php private
$PHP install.php private
$PHP /home/ensenada/backend-laravel-produccion/deploy/production/init-env.php
chmod 600 /home/ensenada/backend-laravel-produccion/.env
```

El instalador compara cada componente con `MANIFEST.json`, verifica la instalación repetida y rechaza diferencias, enlaces simbólicos y sobrescrituras. No actualiza instalaciones divergentes automáticamente. `.env`, aplicación, `vendor`, cachés, logs, documentos y espacio temporal quedan **todos fuera de `public_html`**, bajo `/home/ensenada/backend-laravel-produccion`. Directorios privados 0700, archivos privados 0600; nunca 0777 ni `storage:link`.

Completar **únicamente** `DB_PASSWORD` del nuevo `.env` en un editor privado. No copiar claves ni contraseña MySQL de staging, no ponerlas en comandos, Git, tickets o esta guía. La plantilla fija `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://www.ensenada.gov.ar`, `DB_DATABASE=ensenada_web_prod`, `DB_USERNAME=ensenada_web_prod_app`. Usar el transporte local configurado por iWinds: `127.0.0.1` o `localhost`, según la cuenta y socket reales. No cambiar el PHP global 8.2.

## C. Base y datos, con autorización separada

El operador debe autorizar expresamente crear la nueva base y cuenta. Ningún script crea bases, usuarios MySQL ni aplica privilegios automáticamente. Si cPanel no permite los grants mínimos por tabla, **bloquear producción**. Los cuatro DML sobre toda la base solo siguen admitidos en staging sintético.

1. Crear una base **independiente y vacía** `ensenada_web_prod`, con `utf8mb4_unicode_520_ci`, y la cuenta de ejecución `ensenada_web_prod_app` sin grants de base/globales. Verificar MariaDB 10.11 real y host efectivo de la cuenta. No reutilizar `ensenada_web`.
2. Usar una cuenta temporal de preparación con DDL solo en esa base y un archivo privado `/home/ensenada/respaldos_privados/prod-setup.cnf`, 0600. Formato `[client]` con `host`, `user`, `password`, `database=ensenada_web_prod`. No usar la cuenta de ejecución para DDL.
3. Inicializar **solo una base vacía**. El SQL comprueba versión, base, cuenta, collation, objetos existentes y bloqueo. La segunda ejecución falla antes del primer CREATE. No ejecutar con `--force` ni desactivar integridad.

```sh
mariadb --defaults-extra-file=/home/ensenada/respaldos_privados/prod-setup.cnf \
  --batch --skip-reconnect ensenada_web_prod \
  < /home/ensenada/backend-laravel-produccion/deploy/production/schema-init-production.sql
```

Las doce tablas y el trigger derivan del inicializador MariaDB existente y de Flyway. Solo cambia la base autorizada; no se alteran migraciones existentes. No se insertan contenidos ni usuarios sintéticos. Se preservan nombres, IDs, índices, relaciones y definiciones históricas. No hay `DROP`, `TRUNCATE`, migraciones automáticas ni regeneración de slugs.

4. Aplicar los GRANT de `grants-production.sql` mediante una cuenta autorizada con capacidad de delegarlos. Revisar `@localhost` frente a la conexión real; solo se admiten `localhost` y `127.0.0.1`, nunca `%`. El runtime carece de DELETE en usuarios/noticias, UPDATE/DELETE en auditoría y cambios estructurales. `auth_mutexes` solo permite SELECT y UPDATE de `name`. No conceder `ALL`, permisos de base, `GRANT OPTION`, rutinas ni administración. Si la cuenta nueva recibe grants generales desde cPanel, retirarlos antes de aplicar los de tabla y comprobarlos, sin afectar otras cuentas.
5. El DEFINER del trigger debe existir y conservar TRIGGER/SELECT sobre `usuarios` y DELETE sobre `auth_sessions`. Retirar sus capacidades de preparación que ya no se necesiten sin invalidar el trigger. La prueba reversible de `control.php check` verifica el efecto con el runtime real.
6. Preparar el contenido oficial aprobado en un JSON privado y el administrador **nuevo de producción**. Los comandos no sobrescriben contenido ni SUPER_ADMIN existentes. Si se restauran datos históricos, hacerlo con un plan revisado sobre esta base vacía en lugar del aprovisionamiento inicial correspondiente.

```sh
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
OPS=/home/ensenada/backend-laravel-produccion/deploy/production
$PHP "$OPS/provision.php" content --defaults-file=/home/ensenada/respaldos_privados/prod-setup.cnf \
  --file=/home/ensenada/respaldos_privados/contenido-aprobado.json
$PHP "$OPS/provision.php" admin --defaults-file=/home/ensenada/respaldos_privados/prod-setup.cnf \
  --email=CORREO_APROBADO --name=NOMBRE_APROBADO
```

Ingresar la contraseña mediante la entrada oculta del comando, mínimo 16 y máximo 72 bytes. No se imprime ni se guarda sin hash. No se modifica el SUPER_ADMIN de staging.

Para datos históricos: inventariar origen, IDs, recuentos y SHA de cada archivo; exportar bajo una instantánea coherente; revisar contenido activo, enlaces, usuarios y permisos; conservar IDs/slugs; excluir sesiones, ventanas de intentos y contenido sintético. Importar mediante cuenta de preparación solamente en la base independiente autorizada. Copiar documentos por módulo al almacenamiento privado manteniendo sus nombres/URLs, sin symlinks ni sobreescribir archivos diferentes. Verificar huérfanos, SHA, tamaños y respuestas públicas/privadas antes de habilitar. No importar en lote un dump de staging. El origen real y la aprobación de los datos siguen pendientes.

```sh
cd /home/ensenada/backend-laravel-produccion
$PHP deploy/production/control.php attest-isolation
$PHP -d memory_limit=256M -d upload_max_filesize=32M -d post_max_size=40M deploy/iwinds/platform.php
$PHP deploy/iwinds/schema-check.php
$PHP deploy/production/control.php check
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan route:list --path=api
```

La atestación inicial compara secretos con staging sin mostrarlos; su comprobante firmado queda privado. La ejecución posterior de producción no depende de leer archivos de staging. Los cambios de claves o credenciales invalidan la habilitación y exigen comprobar de nuevo. No ejecutar `migrate`, `migrate:fresh`, `db:seed`, ni `key:generate` sobre instalaciones existentes.

## D. Revisión web limitada por IP, manteniendo WordPress para el público

Respaldar primero el estado nuevo y comprobar cuota efectiva, espacio para temporales y restauración. Con el backend cerrado:

```sh
$PHP "$OPS/control.php" close
sh "$OPS/backup.sh" /home/ensenada/respaldos_privados/prod-backup.cnf \
  /home/ensenada/respaldos_privados/produccion-previo-FECHA_UNICA
```

`prod-backup.cnf` es privado y corresponde a una cuenta con capacidad de exportar esta base y su trigger. Usar un grupo `[client]` con `host=127.0.0.1` (o `localhost`), `user`, `password` y `port=3306` opcional, **sin `database` ni otras opciones**. El script rechaza servidores remotos e inclusiones. No reutilizar directamente el archivo de aprovisionamiento. El script no exporta WordPress ni bases ajenas. Conserva el rollback del enrutamiento y del nuevo directorio web. El WordPress original permanece en su lugar; verificar también su respaldo completo y restaurable con el procedimiento municipal existente. No restituir dumps en el servidor compartido para ensayar una restauración; ensayar en un destino aislado autorizado.

```sh
cd /home/ensenada/paquetes-produccion/revision-20261127
$PHP install.php angular
$PHP install.php web
$PHP "$OPS/cutover.php" candidate /home/ensenada/public_html/.htaccess \
  /home/ensenada/respaldos_privados/htaccess-preview IP_PUBLICA_DEL_REVISOR
diff -u /home/ensenada/public_html/.htaccess /home/ensenada/respaldos_privados/htaccess-preview
```

La carpeta web solo recibe el puente y su `.htaccess`, conservando cada archivo Angular. El candidato agrega un bloque después de `# END mudanza turnos`, preservando bytes y prioridad del bloque de turnos, WordPress y cPanel. Cada regla nueva valida el host principal y, en revisión, la IP real de conexión. No se aceptan X-Forwarded para simularla. Elegir una IP fija de revisión. La revisión requiere autorización explícita para aplicar este candidato al `.htaccess` principal; **no cambiarlo antes de esa aprobación**.

```sh
$PHP "$OPS/control.php" enable --approved-data-and-cutover
SHA_ACTUAL=$(sha256sum /home/ensenada/public_html/.htaccess | cut -d ' ' -f1)
$PHP "$OPS/cutover.php" apply /home/ensenada/public_html/.htaccess \
  /home/ensenada/respaldos_privados/htaccess-preview "$SHA_ACTUAL" --authorized
```

El cambio es atómico y comprueba el SHA inmediatamente antes. Ejecutar siempre como propietario de `.htaccess`. Fuera de la IP revisora se mantiene el enrutamiento original. La seguridad de Laravel sigue exigiendo sesión individual, permisos y CSRF. La revisión por IP no es un acceso administrativo alternativo. La actualización Angular requiere el backend cerrado, copia el bundle nuevo sin borrar el anterior y sustituye el índice atómicamente. Su respaldo queda en `deploy-state/angular-index.before`. Repetir `install.php angular` verifica el estado y no vuelve a modificarlo.

Verificar por HTTPS los valores **web**, sin `phpinfo` público. El puente devuelve 503 si PHP no es 8.3, memoria <256M, subida <32M o POST <40M. `-d` solo afecta CLI, no corrige PHP web. Configurar estos valores exclusivamente para el nuevo directorio/handler si iWinds lo permite y comprobarlos con cargas reales. Su `.htaccess` usa el handler cPanel `application/x-httpd-ea-php83` localizado; su disponibilidad y la herencia de opciones/handler deben confirmarse en iWinds. No cambiar el bloque PHP 8.2 del dominio principal.

El generador escribe también un comprobante `.receipt` que vincula el candidato al SHA de su original: un cambio posterior bloquea la instalación incluso si se proporciona un SHA actual nuevo. Conservar el candidato junto a su comprobante privado. Se registra el original y la versión aplicada en `deploy-state/cutover.json`; rollback solo acepta esa pareja exacta y se bloquea ante cambios posteriores. Evitar escrituras de WordPress/cPanel al `.htaccess` durante la ventana autorizada y verificar su SHA inmediatamente después. El bloqueo propio no sustituye la coordinación con esos gestores externos.

## E. Aceptación antes del corte

Comparar el inventario ya guardado sin repararlo ni modificarlo. El script acepta texto con hosts o URLs y columnas adicionales; falla si no reconoce dominios. Conserva status y Location, no sigue redirecciones. Guardar un baseline nuevo inmediatamente antes para evitar atribuir cambios antiguos al corte.

```sh
$PHP "$OPS/compare-domains.php" snapshot \
  /home/ensenada/respaldos_privados/migracion-web-20261127/subdominios-antes.txt \
  > /home/ensenada/respaldos_privados/servicios-pre-corte.tsv
$PHP "$OPS/compare-domains.php" compare /home/ensenada/respaldos_privados/servicios-pre-corte.tsv
$PHP "$OPS/acceptance.php" --news=SLUG_APROBADO --hacienda=ID_APROBADO \
  --image=/uploads/noticias/UUID.webp --provider=/uploads/proveedores/UUID.pdf \
  --private-document=/uploads/hacienda/UUID_PRIVADO.pdf
```

Ejecutar la aceptación desde la IP autorizada durante preview. `curl` verifica TLS y no sigue redirecciones silenciosas. Requiere JSON válido, contratos básicos, descargas y bloqueo de archivos privados. No basta HTTP 200. Las comprobaciones administrativas se realizan con cuentas autorizadas y contenido de aceptación aprobado: login/logout, roles, revocación, noticias y galería de seis fotos, JPEG/PNG/WebP grandes, hero/Intendencia, Hacienda con orden/retirada, Proveedores, CMS (incluye teléfonos/agenda), usuarios y auditoría. Descargar las imágenes optimizadas y medir lado mayor <=1920 sin ampliación, MIME y transparencia. Revisar consola y navegación móvil/escritorio. No ejecutar los scripts sintéticos de tests en el hosting.

Los 503/000 preexistentes de hpl, produccion, qrsprensa, sd3, transporteurbano, r2 y rafam2 se registran como baseline; no se reparan en esta tarea. Cualquier diferencia nueva de subdominios o de servicios críticos bloquea el corte hasta revisión funcional.

## F. Corte público autorizado

Solo después de aprobar respaldo restaurado, datos, grants, handler/recursos web, aceptación funcional y comparación de servicios. Restituir primero el `.htaccess` original del preview mediante el procedimiento G y cerrar el backend. Generar **otro** candidato sin IP a partir del `.htaccess` real vigente. Revisar el diff y registrar su SHA; conservar el original exacto en privado. Habilitar el backend explícitamente y aplicar el candidato completo con `cutover.php apply ... SHA_ACTUAL --authorized`, como en D. Ningún instalador lo hace por sí solo.

La canonicalización inicial usa 302 hacia HTTPS/www para permitir reversión sin cachear permanentemente. El bloque de turnos sigue teniendo prioridad. Los estáticos y rutas Angular se reescriben internamente con `END`, evitando reentrada al bloque WordPress. Se marca separadamente la reescritura confiable para autorización del directorio hijo. El `.htaccess` hijo exige esa marca, de modo que no depende solo de Host ni permite entradas directas/codificadas. `DirectorySlash Off` se aplica solo a rutas Angular exactas del host principal para resolver colisiones con directorios físicos. Los archivos `/hacienda/*.pdf`, `/pdf`, `/doc`, `/servicios`, ACME y las otras rutas históricas continúan en el enrutamiento original.

Los archivos históricos existentes en `/hacienda` permanecen accesibles tal como estaban antes; revisar su clasificación municipal por separado. El nuevo almacenamiento Laravel privado no se publica allí. Las rutas históricas de WordPress que no pertenecen a Angular siguen atendidas por WordPress, incluidos sus códigos de respuesta. No se elimina ni se convierte SSR en esta tarea: se utiliza el build browser V13 existente. Noticias servidas estáticamente siguen requiriendo la decisión ya pendiente sobre SSR/SEO dinámico; no se presenta HTML de API como prueba de SSR.

## G. Reversión inmediata

Primero cerrar el backend. Restaurar **solo el enrutamiento**, conservando los datos y documentos nuevos. No restaurar un dump antiguo sobre datos escritos después del corte.

```sh
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
OPS=/home/ensenada/backend-laravel-produccion/deploy/production
$PHP -n "$OPS/control.php" close
SHA_ACTUAL=$(sha256sum /home/ensenada/public_html/.htaccess | cut -d ' ' -f1)
$PHP "$OPS/cutover.php" rollback /home/ensenada/public_html/.htaccess \
  /home/ensenada/respaldos_privados/migracion-web-20261127/htaccess-original \
  "$SHA_ACTUAL" --authorized
$PHP /home/ensenada/paquetes-produccion/revision-20261127/install.php rollback-web
$PHP /home/ensenada/paquetes-produccion/revision-20261127/install.php rollback-angular
```

Usar como fuente el original **inmediatamente previo** si cambió desde el respaldo del 27/11. El SHA y el diff deben demostrarlo. El rollback de la carpeta web repone `Require all denied`; conserva Angular, backend, base y archivos. Si hubo cambios legítimos posteriores en `.htaccess`, el operador debe reconciliarlos antes, nunca forzar la sobrescritura. Repetir HTTPS, WordPress, turnos, PDFs históricos y comparación de subdominios. Restauraciones de datos o archivos requieren un incidente y autorización independientes.

## Condiciones de bloqueo pendientes de infraestructura

La cuenta Unix de cPanel es compartida por aplicaciones municipales. Los directorios 0700 y las bases separadas no aíslan frente a un proceso comprometido que ejecute con ese mismo usuario. WordPress permanece operativo por requisito: su seguridad y la aceptación de este riesgo corresponden al responsable de infraestructura. El aislamiento fuerte requiere cuentas o entornos de ejecución distintos; Laravel no puede proporcionarlo desde el código de esta entrega.

- Autorización para base y usuario independientes y capacidad real de conceder mínimos por tabla; `cpanel-dml` bloquea producción.
- Datos oficiales aprobados, importación revisada, inventario de archivos y administrador propio de producción.
- Cuota real, respaldo completo de WordPress y restauración probada del nuevo backend, datos y documentos.
- Handler PHP 8.3 localizado, AllowOverride/opciones, HTTPS/certificado, límites efectivos web, ImageMagick/política, `prlimit` y cargas reales en iWinds. Las pruebas locales no confirman estas condiciones del hosting.
- Aceptación administrativa en el dominio definitivo y pruebas funcionales de servicios municipales, además de status/Location.
- Decisión municipal sobre la continuidad de SSR/SEO para noticias del build estático V13.

Los avisos npm de `body-parser` y `qs` afectan dependencias del servidor Node de SSR; el paquete no instala ni ejecuta ese servidor. Si se habilita SSR posteriormente, actualizar y validar esas dependencias antes. Las cinco alertas Composer detectadas se corrigieron actualizando exclusivamente cuatro paquetes Symfony a 7.4.24; el paquete requiere auditoría y regresión satisfactorias antes de generarse.

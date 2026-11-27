# Etapa 8B — MariaDB 10.11 en iWinds

## Alcance y compatibilidad

La configuración informada para iWinds es MariaDB 10.11.19, base `ensenada_web` vacía con `latin1 / latin1_swedish_ci` y Laravel privado bajo `/home/ensenada/backend-laravel`. La validación se ejecutó exclusivamente en contenedores descartables. Durante una instalación futura se conserva el `.htaccess` actual y se mantiene ausente `.iwinds-api-enabled`.

| Aspecto | Decisión |
| --- | --- |
| Motor y DDL | `schema-init-mariadb.sql` verifica MariaDB 10.11. La variante MySQL sigue separada. Ambas tienen el mismo cuerpo DDL, comprobado en pruebas. |
| Charset y collation | Usar `utf8mb4_unicode_520_ci`. Las comparaciones de los índices históricos ignoran mayúsculas y acentos y admiten Unicode de cuatro bytes. Su comportamiento `PAD SPACE` puede producir colisiones al importar datos de una collation `NO PAD`. |
| Base vacía | El cambio explícito de default mediante `ALTER DATABASE` ocurre después de comprobar que la base está vacía, bajo el mismo bloqueo de inicialización. La configuración global del servidor puede seguir en `latin1`. |
| Tipos, fechas e IDs | Se preservan `BIGINT` firmado, tamaños de columnas, `DATE`, `DATETIME` y `TIMESTAMP(6)`. La sesión de inicialización activa `explicit_defaults_for_timestamp`. Las fechas históricas con zona se exportan e importan en UTC. Se verifica el rango de `TIMESTAMP` del motor al preparar la restauración. |
| Integridad | InnoDB conserva PK, índices únicos, FK, cascada de galería y `CHECK` de estados, MIME, tamaño y orden. La sesión de inicialización activa `foreign_key_checks`, `unique_checks` y `check_constraint_checks`. El comprobador verifica que sigan activos en la sesión de Laravel. MariaDB devuelve `4025` al violar `CHECK`, frente a `3819` en MySQL. |
| Trigger | Se conserva `revoke_auth_sessions` con comparación binaria de email y contraseña. Se requieren un definidor persistente y sus privilegios efectivos, además de resolver las restricciones de binlog en el hosting. |
| Laravel | Las consultas de ejecución usan PDO MySQL y `DB_COLLATION=utf8mb4_unicode_520_ci`. Desde 8C, Noticias públicas comparte la conexión principal y aplica una transacción de solo lectura para cada consulta pública. |

El esquema conserva las nueve tablas finales de Flyway y agrega tres auxiliares de autenticación. Solo inserta cuatro filas técnicas. La marca `schema-ready` se escribe después del trigger. El singleton institucional `id=1`, el primer administrador y los documentos históricos se preparan después, mediante una operación de datos autorizada. [Inventario de tablas y diferencias con Flyway](SCHEMA.md), [MariaDB, collations](https://mariadb.com/docs/server/reference/data-types/string-data-types/character-sets/supported-character-sets-and-collations), [MariaDB, TIMESTAMP](https://mariadb.com/docs/server/reference/data-types/date-and-time-data-types/timestamp), [MariaDB, restricciones](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint).

## Instalación controlada en cPanel

### 1. Identificar el servidor y respaldar

En Terminal cPanel, usar una cuenta de preparación separada, con visibilidad completa del esquema. `mariadb --version` identifica el cliente. Estas consultas identifican el servidor, la cuenta efectiva y los objetos de la base seleccionada:

```sh
mariadb --no-defaults --protocol=SOCKET --user=USUARIO_ADMIN_ESQUEMA --password --database=ensenada_web
```

```sql
SELECT DATABASE(), CURRENT_USER(), VERSION(), @@version_comment, @@collation_server,
       @@log_bin, @@log_bin_trust_function_creators;
SHOW CREATE DATABASE `ensenada_web`;
SHOW COLLATION LIKE 'utf8mb4_unicode_520_ci';
SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();
SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE();
SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE();
SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE();
```

Confirmar MariaDB 10.11.19, identidad y socket correctos, cero objetos y la collation informada. Guardar en privado la salida de `SHOW CREATE DATABASE` y los grants actuales de las cuentas. `SHOW GRANTS` en MariaDB puede incluir material de autenticación. Mantener esa salida fuera de logs públicos y Git. La copia previa se hace desde un directorio privado con permisos restrictivos:

```sh
umask 077
mariadb-dump --no-defaults --protocol=SOCKET --single-transaction --skip-lock-tables \
  --skip-add-drop-table --routines --events --triggers \
  --user=USUARIO_ADMIN_ESQUEMA --password ensenada_web > ensenada_web-pre-8b.sql
```

Comprobar el éxito y el hash de la copia antes de continuar. Si el dump falla por privilegios o por indisponibilidad de la herramienta, solicitar el respaldo al administrador del hosting. [MariaDB, mariadb-dump](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).

### 2. Convertir el default e inicializar

Subir `initialize-schema.sh`, `schema-init-mariadb.sql` y `schema-grants.sql` juntos a una carpeta privada. Mantener una ventana sin escritores ni administradores concurrentes. El siguiente comando solicita la contraseña e inicia una sesión que aborta al primer error. La conversión acepta solo el perfil de origen `latin1_swedish_ci` o la collation destino ya configurada.

```sh
sh initialize-schema.sh mariadb USUARIO_ADMIN_ESQUEMA --convert-empty-latin1
```

El SQL ejecuta `ALTER DATABASE ensenada_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci` solo si corresponde, sin convertir filas ni tocar tablas existentes. Si el DBA ya cambió la base vacía, ejecutar el mismo comando sin `--convert-empty-latin1`. Mantener el cliente sin `--force`. Verificar después el default, las 12 tablas, los cuatro mutexes y `SHOW CREATE TRIGGER revoke_auth_sessions`. La cuenta que figure como definidor debe mantenerse válida. Si el hosting impide crear el trigger, detener el procedimiento y solicitar su instalación al administrador. [MariaDB, CREATE TRIGGER](https://mariadb.com/docs/server/server-usage/triggers-events/triggers/create-trigger).

### 3. Limitar las cuentas y verificar Laravel

1. Aplicar la propuesta y la comprobación previa de `GRANT OPTION` de [Etapa 8C](UPDATE-8C.md). `ensenada_web_app` es la cuenta de ejecución, incluida Noticias. Conservar `ensenada_web_schema` como definidor de `revoke_auth_sessions`.
2. En el `.env` privado usar `DB_COLLATION=utf8mb4_unicode_520_ci`. La instalación existente debe recibir la actualización incremental 8C antes de ejecutar el comprobador. Ejecutar desde el directorio privado:

```sh
cd /home/ensenada/backend-laravel
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" artisan config:clear
"$PHP83" deploy/iwinds/schema-check.php
"$PHP83" artisan config:cache
```

El comprobador es de solo lectura. Valida versión, collation, motor InnoDB, columnas clave, grants de ejecución, controles de integridad y marca completa. El singleton institucional ausente aparece como pendiente. La comprobación del trigger y de su definidor corresponde al administrador.

## Reversión y bloqueos

MariaDB confirma DDL por sentencia. Si falla el trigger o una tabla, mantener la API cerrada y guardar versión, mensaje, inventario de tablas, trigger y marca. Si solo cambió el default y sigue sin objetos, el DBA puede restablecer `latin1 / latin1_swedish_ci` según el respaldo o completar la inicialización aprobada. Si hay objetos, conservarlos y pedir una reparación explícita o un esquema vacío de reemplazo bajo un plan separado. Una segunda ejecución sobre un esquema parcial se rechaza. Evitar `ROLLBACK` como estrategia de reversión del DDL, `DROP`, `TRUNCATE`, migraciones Artisan y restauraciones automáticas sobre una base existente. [MariaDB, sentencias con commit implícito](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).

Antes de habilitar `/api` quedan la instalación autorizada, la validación del trigger y permisos efectivos en iWinds, el primer administrador, el singleton `id=1`, la restauración histórica y las pruebas del vhost real. Para los históricos, conservar IDs y UUID, revisar colisiones de email, slug y nombres almacenados bajo la collation destino, importar padres antes de hijos con las FK activas y verificar los hashes de los archivos privados. [Procedimiento de restauración](SCHEMA.md#restauración-posterior).

## Resultados locales

| Comprobación | Resultado |
| --- | --- |
| MariaDB `10.11.19-MariaDB-ubu2204` | Se inició con servidor y base `latin1_swedish_ci`. La base vacía se convirtió a `utf8mb4_unicode_520_ci`, quedaron 12 tablas y cuatro filas técnicas antes de cargar fixtures. |
| `schema-check.php` | Aprobado en MariaDB 10.11.19 y MySQL 8.4.13. |
| HTTPS en MariaDB | 78 pruebas, 1197 aserciones. Incluye login, revocación, Noticias, Sitio, Hacienda y Proveedores. |
| Esquema y restauración en MariaDB | 4 pruebas, 51 aserciones. Se compararon las nueve definiciones finales de Flyway en el mismo motor, se probaron FK, `CHECK`, unicidad, trigger, grants y restauración de IDs, UUID y bytes. El título sintético con un carácter Unicode de cuatro bytes sobrevivió al recorrido HTTP. |
| Angular en Chromium con HTTPS y MariaDB | 38 pruebas aprobadas, sin omisiones ni resultados inestables. Se ejecutaron los escenarios Laravel, incluidos formularios y descargas de Hacienda y Proveedores. |
| Regresión MySQL 8.4.13 | 78 pruebas HTTP y 4 pruebas de esquema, con 1197 y 51 aserciones, respectivamente. |
| Falla parcial y reejecución | Se rechazaron el motor equivocado y la conversión sin autorización. Al negar `TRIGGER`, se conservaron las 12 tablas ya creadas, con cero filas técnicas y sin `schema-ready`. La reejecución se bloqueó en el esquema parcial y en el esquema poblado. En el poblado se conservaron IDs, UUID, tamaños y mutexes. |
| Reducción de permisos | El retiro de `ALL PRIVILEGES` a nivel de base conservó los permisos por tabla y bloqueó `CREATE TABLE` para una cuenta sintética. |
| Respaldo | Se ejecutó `mariadb-dump` con las opciones documentadas sobre la base sintética. El resultado incluyó el trigger y careció de sentencias `DROP TABLE`. |
| Calidad | Pint aprobó 107 archivos. Pasaron `git diff --check` y la revisión de sintaxis del ejecutor con `sh -n`. |

La reproducción combina `compose.stage2.yaml`, `compose.stage8.yaml` y `compose.stage8b.yaml` en ese orden. Desde `backend-laravel`, exportar `AUTH_STAGE8_COLLATION=utf8mb4_unicode_520_ci` y la contraseña **sintética** del Compose base mediante `AUTH_STAGE8_RUNTIME_PASSWORD`. El overlay fija el digest de MariaDB 10.11.19 y ejecuta el motor descartable sin puertos publicados y con datos en `tmpfs`. Los resultados de arriba corresponden a 8B. La guía 8C registra las verificaciones con la cuenta única.

```sh
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -f compose.stage8b.yaml -p ensenada-stage8b-maria up -d --build --wait app tls angular
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -f compose.stage8b.yaml -p ensenada-stage8b-maria exec -T app sh docker/local-env.sh php deploy/iwinds/schema-check.php --disposable
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -f compose.stage8b.yaml -p ensenada-stage8b-maria exec -T app vendor/bin/phpunit -c phpunit.http.mysql.xml
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -f compose.stage8b.yaml -p ensenada-stage8b-maria --profile browser build browser
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -f compose.stage8b.yaml -p ensenada-stage8b-maria --profile browser run --rm --no-deps -e ENSENADA_BROWSER_OUTPUT=/results/stage8b browser
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -f compose.stage8b.yaml -p ensenada-stage8b-maria exec -T app vendor/bin/phpunit -c phpunit.http.mysql.xml tests/Stage8/SchemaInitTest.php
```

Ejecutar la prueba de restauración después de las otras suites, porque deja registros sintéticos para la comprobación de reejecución. Los registros están en `output/stage8b/` y Playwright en `output/angular/stage8b/playwright.json`.

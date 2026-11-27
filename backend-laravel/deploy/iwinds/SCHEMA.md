# Etapa 8 — Esquema MySQL

Para el servidor de iWinds identificado como MariaDB 10.11.19, usar [Etapa 8B — MariaDB](SCHEMA-MARIADB.md). Esta guía conserva el procedimiento independiente para MySQL 8.

**Validación local. La instalación en iWinds sigue condicionada a identificar el motor, la versión y la collation reales.** La base informada `ensenada_web` está vacía. Esta etapa entrega el DDL, la propuesta de permisos y el procedimiento. La instalación privada existente y `/api` no cambian.

## Qué se crea

| Origen | Tablas | Claves, restricciones e índices |
| --- | --- | --- |
| Flyway V1 y V3 | `usuarios`, `usuario_roles` | ID `BIGINT` firmado autoincremental, email único y `token_version BIGINT DEFAULT 0`. PK de roles `(usuario_id, rol)` y FK a `usuarios`. |
| Flyway V1, V5 y V99 | `noticias`, `noticia_imagenes` | ID `BIGINT`, estado con `CHECK`, índice `(estado, destacada, id)`, slug único y obligatorio. Galería con PK `(noticia_id, orden)` y FK con `ON DELETE CASCADE`. |
| Flyway V2 | `publicaciones_hacienda`, `archivos_hacienda` | IDs `BIGINT`, controles de estado y tipo, índice público `(estado, fecha_publicacion, id)`. Archivos con UUID almacenado único, FK a publicación, índice `(publicacion_hacienda_id, orden)` y controles de tamaño positivo, MIME y orden. |
| Flyway V4 | `portal_site_content` | PK `BIGINT`, `TEXT` y `updated_at TIMESTAMP`. |
| Flyway V6 | `auditoria_noticias` | PK `BIGINT`, fecha `TIMESTAMP(6)` e índices por fecha y noticia. Se conserva el diseño histórico sin FK. |
| Flyway V7 | `documentos_proveedores` | PK `BIGINT`, nombre almacenado único e índice `(activo, orden, id)`. Se conserva el diseño histórico sin FK. |
| Laravel | `auth_sessions`, `auth_login_windows`, `auth_mutexes` | PK por hash de sesión, hash de IP o nombre del mutex. FK de sesiones a usuarios e índices de expiración. |

Se revisaron las ocho migraciones Flyway y las dos migraciones Laravel. El SQL declara InnoDB y conserva el estado final del DDL histórico. Integra `token_version` y `slug` en `CREATE TABLE`, sin ejecutar los `ALTER` ni backfills de Flyway sobre una base ya poblada. La carga V4 del singleton, el backfill de slugs V5 y la copia de imágenes V99 quedan fuera. Se excluyen `flyway_schema_history`, `auth_migration_history`, `auth_schema_baseline`, usuarios sintéticos y datos de la Municipalidad. La única carga inicial agrega `login`, `news-slug`, `proveedores` y `schema-ready` a `auth_mutexes`. La última marca solo se inserta después del trigger.

`revoke_auth_sessions` se ejecuta `BEFORE UPDATE` sobre `usuarios`. Compara el email y el hash de contraseña en binario. Cuando cambian, o cuando una cuenta activa pasa a inactiva, incrementa `token_version` sin duplicar un incremento ya aplicado por la operación y elimina las sesiones del usuario. Su `DEFINER` es la cuenta que lo crea y debe conservar los privilegios necesarios. Con binlog activo, iWinds puede exigir que el administrador instale el trigger. [MySQL, seguridad de objetos](https://dev.mysql.com/doc/refman/8.0/en/stored-objects-security.html), [MySQL, binlog de programas almacenados](https://dev.mysql.com/doc/refman/8.0/en/stored-programs-logging.html).

## Motor y collation

El inicializador admite MySQL 8.0.16 o posterior de la serie 8.0 y MySQL 8.4. El piso 8.0.16 se debe a la aplicación de `CHECK`. MariaDB, MySQL 5.7 y otras series quedan fuera del alcance. Identificar el motor con `VERSION()` y `@@version_comment` antes de cualquier instalación. [MySQL, implementación de CHECK en 8.0.16](https://dev.mysql.com/blog-archive/mysql-8-0-16-introducing-check-constraint/).

- `utf8mb4_0900_ai_ci` es la opción preferida y el valor predeterminado de Laravel. El SQL hereda la collation de la base vacía.
- `utf8mb4_unicode_520_ci` es una alternativa comprobada en MySQL 8.0. Difiere en las reglas Unicode y considera equivalentes cadenas con espacios finales. Antes de importar históricos, revisar colisiones en `usuarios.email`, `noticias.slug` y nombres almacenados bajo la collation destino. El comprobador y ambas conexiones Laravel deben usar el mismo `DB_COLLATION`. La alternativa requiere el código de esta etapa en el backend privado si el ZIP ya instalado conserva la collation fija. Limpiar y regenerar el caché de configuración cuando cambie. [MySQL, Unicode y atributos PAD SPACE y NO PAD](https://dev.mysql.com/doc/refman/8.0/en/charset-unicode-sets.html).

## Procedimiento controlado en iWinds

1. Mantener el `.htaccess` actual y ausente `.iwinds-api-enabled`. Confirmar que `ensenada_web` es la base vacía de staging autorizada. Usar una cuenta administradora separada con visibilidad del esquema completo y permisos de preparación. El usuario de ejecución se reserva para Laravel. Verificar también que no haya una instalación de esquema concurrente.
2. Desde la sesión MySQL autorizada, revisar identidad, motor, versión, parámetros y todos los tipos de objetos. Si la base no está vacía o su collation por defecto no está aprobada, detener el procedimiento. Para una base vacía con collation distinta, el DBA puede fijar su valor por defecto por separado, después de confirmar el alcance.

```sql
SELECT DATABASE(), CURRENT_USER(), VERSION(), @@version_comment,
       @@innodb_default_row_format, @@log_bin, @@log_bin_trust_function_creators;
SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE();
SHOW COLLATION WHERE Charset = 'utf8mb4'
  AND Collation IN ('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_520_ci');
SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();
SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE();
SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE();
SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE();
```

3. Subir `schema-init.sql` e `initialize-schema.sh` juntos a una carpeta privada. Usar Terminal cPanel con cliente `mysql` y confirmar el socket local. El wrapper solicita la contraseña de la cuenta administradora, usa `--no-defaults`, desactiva la reconexión y aborta al primer error. El SQL comprueba el nombre de la base, la versión, la cuenta, el bloqueo y el esquema vacío antes del DDL. No ejecutar con `--force` ni desde un cliente que continúe tras errores.

```sh
sh initialize-schema.sh USUARIO_ADMIN_ESQUEMA
```

4. Aplicar los permisos de la cuenta única de ejecución según la [Etapa 8C](UPDATE-8C.md). Conservar la cuenta del esquema y el definidor del trigger. El permiso `UPDATE (name)` en `auth_mutexes` permite las lecturas `FOR UPDATE`. [MySQL, lecturas con bloqueo](https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html).
5. Verificar con el administrador `SHOW CREATE TRIGGER revoke_auth_sessions`, el definidor, las 12 tablas, las FK e índices y la marca `schema-ready`. Ejecutar el comprobador actualizado con la cuenta de ejecución. Comprueba collation, InnoDB, columnas clave, grants propios y mutexes. El administrador comprueba el trigger y su definidor.

```sh
cd /home/ensenada/backend-laravel
/opt/cpanel/ea-php83/root/usr/bin/php deploy/iwinds/schema-check.php
```

El esquema vacío no incluye usuarios ni el singleton institucional `id=1`. La API institucional devuelve `404` hasta restaurarlo. El aprovisionamiento del primer administrador autorizado y la restauración del contenido requieren una operación de datos independiente. Mantener `/api` cerrada durante todas estas comprobaciones.

## Restauración posterior

1. Tomar un respaldo consistente de filas y archivos en una ventana autorizada. Elaborar un manifiesto con IDs, UUID, MIME, tamaños y SHA-256. Comparar la collation de origen y destino y resolver colisiones antes de importar. Conservar los IDs explícitos y comprobar que los siguientes autoincrementos queden por encima de los máximos importados.
2. Cargar filas con columnas explícitas en transacciones por lote, con las FK activas y tablas padre antes de hijas. Importar usuarios antes de roles, noticias antes de galerías y publicaciones Hacienda antes de archivos. Preservar fechas, slugs, orden, estados y el singleton `id=1`. Proveedores no tiene una dependencia relacional. Excluir sesiones, contadores de login e historiales de migración. La reanudación tras un lote fallido se decide a partir del respaldo y del recuento validado.
3. Copiar bytes a los directorios privados de Noticias, Sitio, Hacienda y Proveedores sin cambiar nombres. Comparar el manifiesto, verificar correspondencias filas–archivos, ausencia de enlaces y permisos. Probar con acceso acotado las URLs históricas, el orden, la descarga administrativa de Hacienda y los `404` de documentos privados o retirados. Los usuarios deben volver a iniciar sesión tras el corte.

## Reversión y bloqueos

El DDL de MySQL puede confirmar cada sentencia por separado. Si aparece un error, mantener el acceso cerrado, guardar diagnóstico y conservar cualquier objeto ya creado para revisión. El inicializador rechazará una segunda ejecución sobre ese esquema. Si todavía está vacío, se puede repetir tras resolver el error. Si hay una aplicación parcial, preparar con el DBA una finalización explícita o una instalación vacía de reemplazo debidamente autorizada. Este procedimiento no elimina ni trunca tablas y no ejecuta rollbacks de Artisan. [MySQL, sentencias con commit implícito](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html).

Quedan pendientes la comprobación real de versión y collation, privilegios para instalar y mantener el trigger, verificación de grants, aprovisionamiento del administrador, restauración de contenido y documentos y validación del vhost de staging antes de habilitar. No hubo conexión a iWinds.

## Resultados locales

| Entorno descartable | `schema-check.php` | HTTPS sobre esquema generado | Casos específicos |
| --- | --- | --- | --- |
| MySQL 8.4.13, `utf8mb4_0900_ai_ci` | Aprobado | 78 pruebas, 1196 aserciones | 4 pruebas, 49 aserciones |
| MySQL 8.0.46, `utf8mb4_unicode_520_ci` | Aprobado | 78 pruebas, 1198 aserciones | 4 pruebas, 49 aserciones |

En cada entorno, `SHOW CREATE TABLE` coincidió con las nueve definiciones finales obtenidas al ejecutar el SQL de Flyway sobre otro esquema sintético con la misma collation. Las pruebas rechazaron filas huérfanas, duplicados y violaciones `CHECK`, comprobaron la revocación ante un cambio de mayúsculas del email y verificaron que el lector de Noticias carece de acceso a usuarios y de escritura. Con IDs y UUID explícitos se restauraron publicaciones y documentos sintéticos y se comprobaron sus URLs y bytes por HTTPS. La segunda inicialización fue rechazada y conservó las filas. Una prueba adicional con el SQL final en una instancia sin red confirmó 12 tablas, cuatro filas técnicas y cero filas de aplicación. Se verificó también el rechazo de una collation configurada que difería de la base. Pint aprobó 107 archivos.

En la primera regresión, cuatro rutas inválidas devolvieron `502` en el proxy local de pruebas. Se limitó el proxy del overlay a los prefijos de la API y archivos. En MySQL 8.0 quedó un `502` transitorio en la prueba de límite de imágenes durante la ejecución paralela. Al repetir la suite completa en serie pasó. Los registros quedan en `backend-laravel/output/stage8/`.

El entorno reproducible usa `compose.stage2.yaml` con `compose.stage8.yaml`, proyecto Docker exclusivo y MySQL en `tmpfs`. El overlay lee `AUTH_STAGE8_RUNTIME_PASSWORD` del entorno. Debe corresponder a la contraseña **sintética** ya configurada en el Compose base. Solo se aplican los datos sintéticos después de ejecutar el SQL y los grants. La cuenta de ejecución recibe un permiso adicional sobre la tabla de comparación de esquema, que existe únicamente en el entorno de pruebas.

```sh
# Desde backend-laravel y con la contraseña sintética de ejecución exportada.
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -p ensenada-stage8-primary up -d --build --wait app tls
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -p ensenada-stage8-primary exec -T app sh docker/local-env.sh php deploy/iwinds/schema-check.php --disposable
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -p ensenada-stage8-primary exec -T app vendor/bin/phpunit -c phpunit.http.mysql.xml
docker compose -f compose.stage2.yaml -f compose.stage8.yaml -p ensenada-stage8-primary exec -T app vendor/bin/phpunit -c phpunit.http.mysql.xml tests/Stage8/SchemaInitTest.php
```

Para reproducir la alternativa en otro proyecto, usar `AUTH_STAGE8_COLLATION=utf8mb4_unicode_520_ci` y `AUTH_STAGE8_MYSQL_IMAGE=mysql:8.0@sha256:7dcddc01f13bab2f15cde676d44d01f61fc9f99fe7785e86196dfc07d358ae2b`, y cambiar el nombre del proyecto a `ensenada-stage8-fallback`.

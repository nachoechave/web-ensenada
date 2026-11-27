# Perfil de permisos de base en cPanel

El perfil preferido sigue siendo `schema-grants.sql`. El comando `schema-check.php` sin opciones lo exige. Con `--privilege-profile=cpanel-dml`, el verificador admite en MariaDB 10.11 solo `SELECT`, `INSERT`, `UPDATE` y `DELETE` directos sobre el nombre literal de la base configurada. Rechaza la falta de cualquiera de los cuatro, permisos adicionales incluso por tabla, roles, grants heredados de `PUBLIC` y delegación. Comprueba también que la base contenga exclusivamente las 12 tablas previstas. No registra líneas de `SHOW GRANTS`, que pueden incluir material de autenticación. La opción afecta únicamente la comprobación por CLI y no modifica los permisos, la configuración de Laravel ni el estado de la API.

## Operaciones cubiertas

| Módulo | Operaciones SQL de ejecución |
| --- | --- |
| Autenticación y Usuarios | Lectura y bloqueo de filas, alta y actualización de usuarios, reemplazo de roles, alta y retiro de sesiones, y mantenimiento de ventanas de intentos. |
| Noticias | Lectura pública en transacciones de solo lectura, alta y actualización editorial, reemplazo de galería e inserción y consulta de auditoría. |
| Hacienda | Lectura, alta, edición y archivado de publicaciones, además de alta, ordenamiento y retiro de documentos. |
| Proveedores | Lectura, alta, edición, ordenamiento y retiro de documentos. |
| Contenido institucional | Lectura y actualización del registro existente. Su alta inicial forma parte del aprovisionamiento. |

Los bloqueos `SELECT … FOR UPDATE` de InnoDB se ejecutan en transacciones y no requieren `LOCK TABLES`. La revocación de sesiones usa el trigger existente `revoke_auth_sessions`, que actúa con su definidor. Conservar `ensenada_web_schema` y sus permisos efectivos para el trigger, las columnas que lee, `NEW.token_version` y el borrado de sesiones. La cuenta Laravel no requiere `TRIGGER`. Los archivos se procesan y guardan con permisos de sistema de archivos. Las migraciones, restauraciones y tareas de esquema quedan fuera del perfil de ejecución. [MariaDB, GRANT](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant) · [MariaDB, CREATE TRIGGER](https://mariadb.com/docs/server/server-usage/triggers-events/triggers/create-trigger)

## Riesgo y decisión

| Alcance | Consecuencia de una credencial de base o un proceso PHP comprometido |
| --- | --- |
| `usuarios`, `usuario_roles`, `auth_sessions` | El perfil por tabla ya permite lecturas sensibles y varias escrituras críticas. El perfil de base agrega el borrado directo de usuarios y escrituras adicionales sobre sesiones. Los controles de rol de Laravel se aplican a solicitudes HTTP, no a SQL directo. |
| `auth_mutexes`, `auth_login_windows` | Se pueden borrar o cambiar filas que serializan operaciones y certifican que el esquema está inicializado, así como alterar el control de intentos. |
| `auditoria_noticias` | Se pueden editar y borrar eventos que Laravel normalmente solo lee e inserta. La auditoría queda bajo la misma cuenta que las escrituras que debe registrar. |
| Noticias, Hacienda, Proveedores y Sitio | Se pueden borrar filas, cambiar estados o romper referencias entre documentos y archivos. Cambiar metadatos o estados en la base puede hacer que la aplicación entregue documentos que antes eran privados. La ausencia de `FILE` limita el acceso SQL directo al sistema de archivos. |
| Tablas presentes y futuras | El grant cubre todos los objetos de la base a los que se aplique. La verificación de objetos y las restricciones de integridad son comprobaciones puntuales. MariaDB permite al cliente cambiar controles de integridad en su propia sesión. |

Retirar privilegios estructurales limita operaciones como `ALTER`, `DROP` y `TRUNCATE`. El borrado masivo por DML sigue siendo posible. El filtro de publicación y la transacción de solo lectura de Noticias protegen el recorrido HTTP correspondiente. Ni esos controles, ni CSRF, ni el trigger, ni las validaciones de Laravel limitan una conexión directa que usa la misma cuenta. [MariaDB, GRANT](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant) · [MariaDB, SET TRANSACTION](https://mariadb.com/docs/server/reference/sql-statements/transactions/set-transaction)

**Decisión recomendada.** El perfil es funcional y reduce el alcance frente a `ALL PRIVILEGES`. Es admisible para validación privada con datos sintéticos. Para el portal municipal con datos reales, mantener como requisito el perfil por tabla. Si se evalúa una excepción institucional, requiere aceptación formal del riesgo y controles verificables fuera del alcance de la cuenta de ejecución. El resultado del verificador por sí solo no autoriza habilitar la API.

### Controles verificables

1. Mantener la base exclusiva de Web Ensenada y la cuenta asociada solo a esa base. Usar la conexión local configurada y revisar en cPanel que el servicio de acceso remoto a MySQL no autorice orígenes innecesarios. Volver a ejecutar el verificador después de cambios de privilegios o esquema.
2. Mantener `.env`, caché de configuración, logs y documentos privados fuera del document root, con los permisos del usuario efectivo de PHP. Evitar registrar credenciales, cookies o SQL que contenga datos personales. Preservar autorización en servidor, CSRF y consultas parametrizadas.
3. Guardar copias versionadas de base y archivos fuera del alcance de PHP y de `ensenada_web_app`, con acceso restringido y cifrado. Ensayar una restauración en un entorno aislado y comprobar IDs, hashes, asociaciones y tiempo de recuperación.
4. Para una excepción con datos reales, registrar cambios administrativos y alertas en un destino de solo anexado que el proceso PHP y la cuenta de base no puedan reescribir. Vigilar cambios de rol, actividad de cuentas, presencia de los cuatro mutexes, desvíos de permisos y borrados anómalos. Conciliar periódicamente el registro independiente, la base y el inventario de archivos. Una tabla o un archivo de log que el mismo proceso puede editar no aporta esa independencia. Estos controles ayudan a detectar y recuperar, pero no bloquean escrituras directas.

## Actualización privada

1. En un directorio privado nuevo de la cuenta, guardar el ZIP y su archivo `.sha256`. Verificar el ZIP con `sha256sum -c web-ensenada-iwinds-cpanel-dml.zip.sha256`. Extraer en ese directorio y ejecutar `sha256sum -c SHA256SUMS`. El archivo `FILES` lista los tres archivos del incremento. El ZIP va fuera de `public_html`.
2. En `/home/ensenada/backend-laravel`, confirmar que `.iwinds-api-enabled` está ausente. Guardar una copia privada de `deploy/iwinds/schema-check.php` y `deploy/iwinds/README.md`, y registrar el estado anterior de los permisos de la asociación de cPanel sin publicar la salida de `SHOW GRANTS`. Instalar únicamente los tres archivos del incremento, conservando propietario y permisos privados. `UPDATE-CPANEL-DML.md` es nuevo. No hace falta regenerar cachés por este incremento, que cambia solo un script CLI y documentación.
3. En **cPanel → Administrar mis bases de datos**, editar la asociación existente de `ensenada_web_app` con `ensenada_web`. Desmarcar **ALL PRIVILEGES** y dejar seleccionados únicamente **SELECT**, **INSERT**, **UPDATE** y **DELETE**. Guardar y abrir una conexión nueva para verificar el resultado. Mantener intacta la asociación de `ensenada_web_schema`. No hace falta delegación SQL ni crear otra cuenta. [cPanel, Manage My Databases](https://docs.cpanel.net/cpanel/databases/manage-my-databases/)
4. Ejecutar los comandos de verificación siguientes. Si la instalación dispone del perfil por tabla, ejecutar `schema-check.php` sin opción. En MariaDB el grant a nivel de base tiene que aparecer con el guion bajo escapado, como `` `ensenada\_web`.* ``. Si cPanel deja un comodín, permisos adicionales o el checker falla, mantener el marcador ausente. No usar la salida de la pantalla de selección como sustituto de los grants efectivos. Verificar el definidor y sus permisos por separado con la cuenta de esquema ya existente. [MariaDB, alcance y comodines de GRANT](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant)

```sh
cd /home/ensenada/backend-laravel || exit 1
test ! -e .iwinds-api-enabled || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/platform.php || exit 1
"$PHP83" deploy/iwinds/schema-check.php --privilege-profile=cpanel-dml || exit 1
"$PHP83" artisan route:list --path=api/noticias || exit 1
test ! -e .iwinds-api-enabled || exit 1
```

El chequeo es de lectura y no prueba las escrituras ni la ejecución del trigger en el hosting. Una prueba funcional posterior necesita staging autorizado, datos sintéticos y acceso web acotado. Angular, Apache y las rutas públicas quedan fuera de la actualización privada.

## Reversión

1. Mantener el marcador ausente. Restaurar desde la copia privada `schema-check.php` y `README.md` y apartar `UPDATE-CPANEL-DML.md`. Ejecutar `php -l` con el binario PHP 8.3 y comprobar `route:list`. No se requiere restaurar la base ni los cachés.
2. Si antes existía el perfil por tabla y se puede recuperar con una cuenta ya autorizada, verificar esos grants y retirar únicamente el grant de base agregado en este paso. Ejecutar el comprobador restaurado sin opciones. Si cPanel es la única vía y la situación previa era `ALL PRIVILEGES`, conservar el DML reducido para diagnóstico privado o retirar el acceso del usuario de ejecución mediante cPanel para aislarlo. Evitar restaurar permisos amplios para que pase el verificador. El comprobador anterior rechazará el DML de base aunque Laravel pueda arrancar. Conservar el definidor del trigger.

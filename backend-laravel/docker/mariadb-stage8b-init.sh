#!/bin/sh
(
    set -eu
    [ "${MYSQL_DATABASE:-}" = ensenada_auth_test ]
    [ "${AUTH_STAGE8_COLLATION:-}" = utf8mb4_unicode_520_ci ]
    export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
    db() { mariadb --no-defaults --protocol=SOCKET --skip-reconnect --batch -uroot -Densenada_auth_test "$@"; }
    [ "$(db -N -e 'SELECT @@collation_database')" = latin1_swedish_ci ]
    { printf '%s\n' 'SET @ensenada_convert_empty_latin1 = 1;'; cat /stage8/schema-mariadb.sql; } | db
    [ "$(db -N -e 'SELECT @@collation_database')" = utf8mb4_unicode_520_ci ]
    [ "$(db -N -e 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')" = 12 ]
    [ "$(db -N -e 'SELECT COUNT(*) FROM auth_mutexes')" = 4 ]
    for table in usuarios usuario_roles noticias noticia_imagenes auditoria_noticias portal_site_content publicaciones_hacienda archivos_hacienda documentos_proveedores auth_sessions auth_login_windows; do
        [ "$(db -N -e "SELECT COUNT(*) FROM $table")" = 0 ]
    done
    echo 'Conversión de la base vacía y DDL MariaDB comprobados antes de cargar fixtures.'
)

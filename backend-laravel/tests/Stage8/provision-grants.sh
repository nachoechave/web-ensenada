#!/bin/sh
(
    set -eu
    [ "${MYSQL_DATABASE:-}" = ensenada_auth_test ]
    [ "${AUTH_STAGE8_COLLATION:-}" = utf8mb4_unicode_520_ci ]
    export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
    mariadb --protocol=SOCKET --user=root --batch <<'SQL'
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'auth_runtime'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `ensenada\_auth\_test`.* TO 'auth_runtime'@'%';
GRANT TRIGGER ON ensenada_auth_test.usuario_roles TO 'auth_setup'@'%';
SQL
)

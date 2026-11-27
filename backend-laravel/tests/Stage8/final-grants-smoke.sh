#!/usr/bin/env bash
set -euo pipefail
[[ -f /.dockerenv && $MYSQL_DATABASE == ensenada_auth_test && $AUTH_STAGE8_COLLATION == utf8mb4_unicode_520_ci ]]
[[ $AUTH_STAGE8_RUNTIME_PASSWORD == synthetic-runtime-only-1234 ]]

root() {
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb --no-defaults --protocol=socket --user=root --batch "$@"
}
runtime() {
    MYSQL_PWD="$AUTH_STAGE8_RUNTIME_PASSWORD" mariadb --no-defaults --protocol=TCP --host=127.0.0.1 \
        --user=auth_runtime --batch --database=ensenada_auth_test "$@"
}

root -e "GRANT SELECT, INSERT, UPDATE, DELETE ON \`ensenada\\_auth\\_test\`.* TO 'auth_runtime'@'%';"
runtime -e 'DELETE FROM usuarios WHERE 1=0; UPDATE auditoria_noticias SET id=id WHERE 1=0;'
sed "s/ensenada_web_app/auth_runtime/g; s/ensenada_web/ensenada_auth_test/g; s/@'localhost'/@'%'/g" /stage8/grants.sql | root
sed "s/ensenada_web_app/auth_runtime/g; s/ensenada\\\\_web/ensenada\\\\_auth\\\\_test/g; s/@'localhost'/@'%'/g" \
    /tmp/final-revoke-dml.sql | root

for statement in 'DELETE FROM usuarios WHERE 1=0' 'UPDATE auditoria_noticias SET id=id WHERE 1=0' \
    'DELETE FROM auditoria_noticias WHERE 1=0' 'INSERT INTO auth_mutexes (name) SELECT name FROM auth_mutexes WHERE 1=0'; do
    if denial=$(runtime -e "$statement" 2>&1) || [[ $denial != *'command denied'* ]]; then
        echo 'Un permiso prohibido no quedó restringido.' >&2
        exit 1
    fi
done
runtime -e 'SELECT id FROM usuarios LIMIT 0; SELECT id FROM auditoria_noticias LIMIT 0; UPDATE auth_mutexes SET name=name WHERE 1=0; DELETE FROM auth_sessions WHERE 1=0;'
echo 'Migración de DML por esquema a permisos por tabla y cuatro denegaciones comprobadas.'

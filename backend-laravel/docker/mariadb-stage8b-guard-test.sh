#!/bin/sh
# Run inside a fresh MariaDB container with no schema init mounts and no published port.
set -eu
[ "${MYSQL_DATABASE:-}" = ensenada_auth_test ]
export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
root_db() { mariadb --no-defaults --protocol=SOCKET --skip-reconnect --batch -uroot -Densenada_auth_test "$@"; }
i=0
until [ "$(root_db -N -e 'SELECT @@skip_networking' 2>/dev/null || :)" = 0 ]; do
    i=$((i + 1)); [ "$i" -lt 65 ]; sleep 1
done
log=/tmp/ensenada-stage8b-guard.log
tables() { root_db -N -e 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()'; }
if root_db < /stage8/schema-init.sql > "$log" 2>&1; then exit 1; fi
grep -q ERROR_VERSION_BASE_USUARIO_O_BLOQUEO "$log"
if root_db < /stage8/schema-init-mariadb.sql > "$log" 2>&1; then exit 1; fi
grep -q ERROR_ESQUEMA_NO_VACIO_O_COLLATION "$log"
[ "$(tables)" = 0 ]
[ "$(root_db -N -e 'SELECT @@collation_database')" = latin1_swedish_ci ]
secret=$(cat /proc/sys/kernel/random/uuid)
{
    printf "CREATE USER 'partial_provisioner'@'localhost' IDENTIFIED BY '%s';\n" "$secret"
    printf '%s\n' "GRANT ALTER, CREATE, INDEX, SELECT, INSERT, REFERENCES ON ensenada_auth_test.* TO 'partial_provisioner'@'localhost';"
} | root_db
if { printf '%s\n' 'SET @ensenada_convert_empty_latin1 = 1;'; cat /stage8/schema-init-mariadb.sql; } |
    MYSQL_PWD="$secret" mariadb --no-defaults --protocol=SOCKET --skip-reconnect --batch -upartial_provisioner -Densenada_auth_test > "$log" 2>&1; then
    exit 1
fi
grep -qi 'TRIGGER command denied' "$log"
[ "$(tables)" = 12 ]
[ "$(root_db -N -e 'SELECT COUNT(*) FROM auth_mutexes')" = 0 ]
[ "$(root_db -N -e 'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')" = 0 ]
if root_db < /stage8/schema-init-mariadb.sql > "$log" 2>&1; then exit 1; fi
grep -q ERROR_ESQUEMA_NO_VACIO_O_COLLATION "$log"
[ "$(tables)" = 12 ]
[ "$(root_db -N -e 'SELECT COUNT(*) FROM auth_mutexes')" = 0 ]
echo 'MariaDB: motor equivocado y conversión sin autorización rechazados. Falla de TRIGGER conserva DDL parcial sin schema-ready y bloquea reejecución.'

{
    printf "CREATE USER 'grant_probe'@'localhost' IDENTIFIED BY '%s';\n" "$secret"
    printf '%s\n' "GRANT ALL PRIVILEGES ON ensenada_auth_test.* TO 'grant_probe'@'localhost';"
    grep "TO 'ensenada_web_app'" /stage8/schema-grants.sql \
        | sed 's/ensenada_web_app/grant_probe/g; s/ensenada_web/ensenada_auth_test/g'
    printf '%s\n' "REVOKE ALL PRIVILEGES ON ensenada_auth_test.* FROM 'grant_probe'@'localhost';"
} | root_db
grants=$(root_db -N -e "SHOW GRANTS FOR 'grant_probe'@'localhost'")
printf '%s\n' "$grants" | grep -Fq 'ON `ensenada_auth_test`.`usuarios`'
if printf '%s\n' "$grants" | grep -Fq 'ON `ensenada_auth_test`.*'; then exit 1; fi
probe() { MYSQL_PWD="$secret" mariadb --no-defaults --protocol=SOCKET --skip-reconnect --batch -ugrant_probe -Densenada_auth_test "$@"; }
[ "$(probe -N -e 'SELECT COUNT(*) FROM usuarios')" = 0 ]
if probe -e 'CREATE TABLE forbidden_stage8b_probe (id BIGINT)' > "$log" 2>&1; then exit 1; fi
grep -qi 'CREATE command denied' "$log"
echo 'MariaDB: el retiro del grant de base conserva el DML por tabla y bloquea DDL.'

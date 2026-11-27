#!/usr/bin/env bash
set -euo pipefail
[[ -f /.dockerenv && $MYSQL_DATABASE == ensenada_auth_test && $MYSQL_ROOT_PASSWORD == synthetic-restore-only ]]

root() {
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb --no-defaults --protocol=socket --user=root --batch --skip-column-names "$@"
}

[[ $(root --database=ensenada_auth_test -e 'SHOW TABLES' | wc -l) == 0 ]]
cd /tmp/final-restore
umask 077
mkdir -p files/storage/app/private/noticias recovered
printf 'Archivo de foto sintético para verificar los bytes restaurados.\n' > files/storage/app/private/noticias/fixture.webp
gzip -c source.sql > ensenada_web.sql.gz
tar -czf archivos.tar.gz -C files storage/app/private
sha256sum archivos.tar.gz ensenada_web.sql.gz > SHA256SUMS
bash backup.sh verify "$PWD"
gzip -dc ensenada_web.sql.gz | root --database=ensenada_auth_test
tar -xzf archivos.tar.gz -C recovered
cmp files/storage/app/private/noticias/fixture.webp recovered/storage/app/private/noticias/fixture.webp
[[ $(root --database=ensenada_auth_test -e 'SHOW TABLES' | wc -l) == 12 ]]
[[ $(root --database=ensenada_auth_test -e 'SELECT COUNT(*) FROM usuarios WHERE activo=1') -gt 0 ]]
[[ $(root --database=ensenada_auth_test -e 'SELECT COUNT(*) FROM portal_site_content WHERE id=1') == 1 ]]
[[ $(root --database=ensenada_auth_test -e "SELECT DEFINER FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='revoke_auth_sessions'") == root@localhost ]]
root --database=ensenada_auth_test <<'SQL'
START TRANSACTION;
SET @test_user = (SELECT id FROM usuarios WHERE activo=1 LIMIT 1);
INSERT INTO auth_sessions (token_hash, usuario_id, token_version, created_at, expires_at)
SELECT REPEAT('f', 64), id, token_version, UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 3600 FROM usuarios WHERE id=@test_user;
UPDATE usuarios SET activo=0 WHERE id=@test_user;
SELECT COUNT(*) INTO @leftover FROM auth_sessions WHERE token_hash=REPEAT('f', 64);
ROLLBACK;
CREATE TEMPORARY TABLE test_trigger_check (sessions INT CHECK (sessions=0));
INSERT INTO test_trigger_check VALUES (@leftover);
SQL
echo 'Restauración de 12 tablas, datos, trigger operativo y archivo privado aprobada.'

#!/bin/sh
# Run privately with a schema-backup account; never pass a password in argv.
set -eu
umask 077
test "$#" -eq 2 || { echo 'Uso: backup.sh ARCHIVO_CLIENTE_0600 DIRECTORIO_NUEVO_PRIVADO' >&2; exit 1; }
config=$(realpath "$1")
case "$2" in /home/ensenada/respaldos_privados/*) ;; *) exit 1;; esac
test ! -L "$1" && test "$(stat -c %a "$config")" = 600
/opt/cpanel/ea-php83/root/usr/bin/php "$(dirname "$0")/validate-backup-config.php" "$config"
exec 9>/home/ensenada/backend-laravel-produccion/.production-control-lock
flock -x 9
test ! -e /home/ensenada/backend-laravel-produccion/.iwinds-production-enabled
test ! -e "$2" && test ! -L "$2"
mkdir -m 700 "$2"
dest=$(realpath "$2")
case "$dest" in /home/ensenada/respaldos_privados/*) ;; *) exit 1;; esac
dump=$(command -v mariadb-dump || command -v mysqldump)
"$dump" --defaults-extra-file="$config" --single-transaction --routines --events --triggers --hex-blob ensenada_web_prod > "$dest/database.sql.partial"
test -s "$dest/database.sql.partial"
mv "$dest/database.sql.partial" "$dest/database.sql"
# The exclusive lock drains in-flight requests and keeps database/file snapshots consistent.
tar -cpf "$dest/production-private.tar" -C /home/ensenada backend-laravel-produccion
tar -cpf "$dest/web-rollback.tar" -C /home/ensenada/public_html .htaccess web-produccion
tar -tf "$dest/production-private.tar" > "$dest/private-index.txt"
tar -tf "$dest/web-rollback.tar" > "$dest/web-index.txt"
(cd "$dest" && sha256sum database.sql production-private.tar web-rollback.tar > SHA256SUMS && sha256sum -c SHA256SUMS)
echo 'Respaldo consistente creado. Antes del corte, restaurar y comprobar en un destino aislado autorizado.'

#!/bin/sh
set -eu
test "$#" -eq 2 || { echo 'Uso: backup.sh CLIENTE_SCHEMA_0600 DIRECTORIO_PRIVADO_NUEVO' >&2; exit 1; }
exec /opt/cpanel/ea-php83/root/usr/bin/php "$(dirname "$0")/handover.php" backup "--schema-config=$1" "--out=$2"

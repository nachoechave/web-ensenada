#!/bin/sh
# Run only from an authorized iWinds terminal, using a separate schema administrator.
set -eu
usage() {
    printf '%s\n' 'Uso: sh initialize-schema.sh mysql|mariadb USUARIO_ADMIN_ESQUEMA [--convert-empty-latin1]' >&2
    exit 1
}
[ "$#" -gt 0 ] || usage
case "$1" in
    mysql|mariadb) engine=$1; shift ;;
    *) engine=mysql ;; # Preserve the earlier one-argument MySQL invocation.
esac
[ "$#" -ge 1 ] && [ "$#" -le 2 ] || usage
account=$1
case "$account" in ''|ensenada_web_app|ensenada_web_news_reader) usage ;; esac
here=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
client=mysql
sql=$here/schema-init.sql
if [ "$engine" = mariadb ]; then
    client=mariadb
    sql=$here/schema-init-mariadb.sql
fi
if [ "$#" -eq 2 ]; then
    [ "$engine" = mariadb ] && [ "$2" = --convert-empty-latin1 ] || usage
    [ -r "$sql" ] || exit 1
    { printf '%s\n' 'SET @ensenada_convert_empty_latin1 = 1;'; cat "$sql"; } |
        "$client" --no-defaults --protocol=SOCKET --skip-reconnect --batch --user="$account" --password --database=ensenada_web
else
    exec "$client" --no-defaults --protocol=SOCKET --skip-reconnect --batch \
        --user="$account" --password --database=ensenada_web < "$sql"
fi

#!/bin/sh
# MySQL entrypoint fixture. The real installation uses schema-grants.sql directly.
(
    set -eu
    [ "${MYSQL_DATABASE:-}" = ensenada_auth_test ] && [ "${MYSQL_USER:-}" = auth_setup ]
    case "$AUTH_STAGE8_COLLATION" in utf8mb4_0900_ai_ci|utf8mb4_unicode_520_ci) ;; *) exit 1 ;; esac
    case "$AUTH_STAGE8_RUNTIME_PASSWORD" in ''|*[!a-zA-Z0-9_-]*) exit 1 ;; esac
    export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"
    {
        printf "CREATE USER 'auth_runtime'@'%%' IDENTIFIED BY '%s';\n" "$AUTH_STAGE8_RUNTIME_PASSWORD"
        sed 's/ensenada_web_app/auth_runtime/g; s/ensenada_web/ensenada_auth_test/g' /stage8/grants.sql \
            | sed "s/@'localhost'/@'%'/g"
        printf '%s\n' 'CREATE TABLE ensenada_auth_test.auth_schema_baseline (name VARCHAR(64) PRIMARY KEY, definition LONGTEXT NOT NULL);'
        printf '%s\n' "GRANT SELECT ON ensenada_auth_test.auth_schema_baseline TO 'auth_runtime'@'%';"
        printf 'CREATE DATABASE ensenada_auth_unit CHARACTER SET utf8mb4 COLLATE %s;\n' "$AUTH_STAGE8_COLLATION"
        printf '%s\n' "GRANT SELECT ON ensenada_auth_unit.* TO 'auth_setup'@'%';"
    } | mysql --protocol=SOCKET --user=root --batch

    cd /stage8/flyway
    {
        cat V1__portal_prensa.sql V2__hacienda_publicaciones.sql V3__jwt_token_version.sql
        sed -n '1,/^);/p' V4__portal_site_content.sql
        cat V5__noticias_slug.sql V6__auditoria_noticias.sql V7__proveedores_documentos.sql V99__noticia_imagenes.sql
    } | mysql --protocol=SOCKET --user=root --batch --database=ensenada_auth_unit
)

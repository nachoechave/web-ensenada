#!/usr/bin/env bash
source "$(dirname -- "${BASH_SOURCE[0]}")/common.sh"

verify() (
    cd -- "$1"
    plain_file SHA256SUMS && plain_file ensenada_web.sql.gz && plain_file archivos.tar.gz ||
        die 'Faltan archivos del respaldo.'
    diff -u <(printf 'archivos.tar.gz\nensenada_web.sql.gz\n') <(cut -c 67- SHA256SUMS | sort)
    sha256sum --strict -c SHA256SUMS
    gzip -t ensenada_web.sql.gz archivos.tar.gz
    gzip -dc ensenada_web.sql.gz | awk '
        /^CREATE TABLE `[a-z_]+` \(/ { split($0, fields, "`"); tables[fields[2]] = 1 }
        /DEFINER=.*TRIGGER `?revoke_auth_sessions`?([[:space:]]|$)/ { trigger = 1 }
        END {
            count = split("archivos_hacienda auth_login_windows auth_mutexes auth_sessions auditoria_noticias documentos_proveedores noticia_imagenes noticias portal_site_content publicaciones_hacienda usuario_roles usuarios", required, " ")
            for (i = 1; i <= count; i++) if (!tables[required[i]]) exit 1
            if (!trigger) exit 1
        }
    ' || die 'El respaldo no contiene las 12 tablas, el trigger de revocación y su definidor.'
    tar -tzf archivos.tar.gz | awk '
        !/^storage\/app\/private(\/|$)/ || /(^|\/)\.\.(\/|$)/ { exit 1 }
        END { if (NR == 0) exit 1 }
    '
    LC_ALL=C tar -tvzf archivos.tar.gz | awk '$0 !~ /^[-d]/ { exit 1 }'
    printf 'Integridad del respaldo verificada. Falta ensayar la restauración de esta copia.\n'
)

case "${1:-}" in
    verify)
        [[ $# == 2 ]] || die 'Uso: bash backup.sh verify RUTA_PRIVADA'
        verify "$2"
        ;;
    create)
        [[ $# == 2 && $2 =~ ^[a-zA-Z0-9_]+$ && $2 != ensenada_web_app ]] ||
            die 'Uso: bash backup.sh create USUARIO_EXPORTADOR_EXISTENTE'
        lock_staging
        closed
        plain_directory "$backend/storage/app/private" || die 'Almacenamiento privado inválido.'
        [[ -z $(find "$backend/storage/app/private" ! -type d ! -type f -print -quit) ]] ||
            die 'El almacenamiento tiene enlaces u otros archivos especiales.'
        backup=$(mktemp -d -- "$state/backup.XXXXXXXX")
        printf 'Copia privada: %s\n' "$backup"
        mariadb-dump --no-defaults --protocol=socket --host=localhost --user="$2" --password \
            --single-transaction --quick --triggers --skip-lock-tables --skip-add-drop-table --no-tablespaces \
            --hex-blob ensenada_web | gzip -c > "$backup/ensenada_web.sql.gz"
        tar -czf "$backup/archivos.tar.gz" -C "$backend" storage/app/private
        closed
        (cd "$backup" && sha256sum archivos.tar.gz ensenada_web.sql.gz > SHA256SUMS)
        verify "$backup"
        ;;
    *) die 'Uso: bash backup.sh create USUARIO_EXPORTADOR_EXISTENTE | verify RUTA_PRIVADA' ;;
esac

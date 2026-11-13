#!/usr/bin/env bash
set -euo pipefail
umask 077
release=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
home=/home/ensenada
backend=$home/backend-laravel
web=$home/public_html/prueba.ensenada.gov.ar
php=/opt/cpanel/ea-php83/root/usr/bin/php
state=$home/iwinds-public-web-state
period=$backend/.iwinds-staging-admin-period.lock
marker=$backend/.iwinds-staging-public-web

fail() { printf 'BLOQUEADO: %s\n' "$*" >&2; exit 1; }
regular() { [[ -f $1 && ! -L $1 && $(realpath -e -- "$1") == "$1" ]]; }
directory() { [[ -d $1 && $(realpath -e -- "$1") == "$1" ]]; }
copy_atomically() (
    local src=$1 dst=$2 mode=$3 temporary
    temporary=$(mktemp "${dst%/*}/.iwinds-web.XXXXXXXX")
    trap 'rm -f -- "$temporary"' EXIT
    install -m "$mode" -- "$src" "$temporary"
    mv -fT -- "$temporary" "$dst"
)
target_for() {
    case "$1" in
        private/*) printf '%s/%s' "$backend" "${1#private/}" ;;
        apache/ensenada-api.php) printf '%s/ensenada-api.php' "$web" ;;
        *) fail "Componente sin destino: $1." ;;
    esac
}

[[ $# == 1 && ( $1 == install || $1 == rollback ) ]] || fail 'Uso: bash install.sh install|rollback'
directory "$backend" && directory "$web" && regular "$web/.htaccess" && [[ -x $php ]] ||
    fail 'El staging de iWinds no coincide con las rutas previstas.'
[[ $release == /home/ensenada/* && $release != /home/ensenada/public_html/* ]] || fail 'Extraé el ZIP en un directorio privado.'
(cd "$release" && sha256sum --strict --quiet -c SHA256SUMS)
cd "$release"
[[ ! -L $state && ! -L $period ]] || fail 'Bloqueo o respaldo inválido.'
mkdir -m 700 -p -- "$state"
directory "$state" && [[ $(stat -c '%a' "$state") == 700 ]] || fail 'El respaldo debe ser privado.'
[[ ! -e $period || -f $period ]] || fail 'Bloqueo administrativo inválido.'
exec 9<>"$period"
[[ $(stat -c '%a:%h' -- "$period") == 600:1 ]] || fail 'Bloqueo administrativo fuera del perfil privado.'
flock -n 9 || fail 'Hay una apertura o un cierre en curso.'
[[ ! -e $marker && ! -L $marker ]] || fail 'Cerrá primero open-public-web con close-public-web.'
for other in .iwinds-api-enabled .iwinds-api-public-probe; do
    [[ ! -e $backend/$other && ! -L $backend/$other ]] || fail "Retirá primero $other."
done
if [[ $1 == install ]]; then
    for name in noticias sitio hacienda proveedores; do
        storage=$backend/storage/app/private/$name
        directory "$storage" && [[ $(stat -c '%a' -- "$storage") == 700 ]] ||
            fail "Almacenamiento privado ausente o con permisos inseguros: $name."
    done
    [[ -z $(find "$web" -type l -print -quit) ]] ||
        fail 'Hay enlaces simbólicos en el document root. Revisá su destino antes de abrir.'
    for entry in "$web/uploads" "$web/storage" "$web/vendor" "$web/backend-laravel"; do
        [[ ! -e $entry && ! -L $entry ]] || fail "Revisá la exposición directa de $entry."
    done
fi

backup=$state/original
mkdir -m 700 -p -- "$backup"
directory "$backup" || fail 'Respaldo inválido.'
if ! regular "$backup/htaccess.protected"; then
    [[ ! -e $backup/htaccess.protected && ! -L $backup/htaccess.protected ]] || fail 'Respaldo Apache inválido.'
    [[ $1 == install ]] || fail 'Falta el respaldo de Apache.'
    grep -Eiq '^[[:space:]]*(AuthType[[:space:]]+Basic|Require[[:space:]]+valid-user)' "$web/.htaccess" ||
        fail 'No se pudo comprobar Directory Privacy en el .htaccess. Conservá Basic y revisá el vhost.'
    copy_atomically "$web/.htaccess" "$backup/htaccess.protected" 600
fi
grep -Eiq '^[[:space:]]*(AuthType[[:space:]]+Basic|Require[[:space:]]+valid-user)' "$web/.htaccess" ||
    fail 'Restablecé primero la protección de cPanel y comprobá HTTP 401 sin credenciales.'

mapfile -t files < <(cut -c 67- BASELINE.sha256)
new=private/app/Support/IwindsPublishedImages.php
changes=()
for file in "${files[@]}"; do
    dst=$(target_for "$file")
    saved=$backup/$file
    regular "$dst" || fail "Destino inválido: $dst."
    expected=$(awk -v file="$file" '$2 == file { print $1 }' BASELINE.sha256)
    current=$(sha256sum -- "$dst")
    if [[ ${current%% *} == "$expected" ]]; then
        if [[ $1 == install && ! -e $saved && ! -L $saved ]]; then
            mkdir -m 700 -p -- "${saved%/*}"
            directory "${saved%/*}" || fail "Directorio de respaldo inválido: $file."
            copy_atomically "$dst" "$saved" "$(stat -c '%a' -- "$dst")"
        fi
        [[ $1 != install ]] || changes+=("$file")
    else
        cmp -s -- "$dst" "$release/$file" || fail "Versión instalada distinta de los paquetes esperados: $file."
        [[ $1 != rollback ]] || changes+=("$file")
    fi
    if [[ $1 == install || ${current%% *} != "$expected" ]]; then
        regular "$saved" || fail "Falta el respaldo de $file."
        (cd "$backup" && printf '%s  %s\n' "$expected" "$file" | sha256sum --strict --quiet -c -)
    fi
    "$php" -l "$release/$file"
done
helper=$(target_for "$new")
if [[ -e $helper || -L $helper ]]; then
    regular "$helper" && cmp -s -- "$helper" "$release/$new" || fail 'El helper de imágenes ya tiene otro contenido.'
fi

generation=$("$php" -r 'echo bin2hex(random_bytes(16));')
printf '%s' "$generation" > "$period"
if [[ $1 == install ]]; then
    "$php" -l "$release/$new"
    [[ -e $helper ]] || copy_atomically "$release/$new" "$helper" 600
else
    # Restore the old bridge first. Basic is still active and no public marker exists.
    files=(apache/ensenada-api.php "${files[@]}")
fi
for file in "${files[@]}"; do
    if [[ " ${changes[*]} " == *" $file "* ]]; then
        src=$release/$file
        [[ $1 != rollback ]] || src=$backup/$file
        copy_atomically "$src" "$(target_for "$file")" "$(stat -c '%a' -- "$backup/$file")"
        if [[ $1 == rollback ]]; then
            changes=("${changes[@]/"$file"/}")
        fi
    fi
done
if [[ $1 == rollback ]]; then
    copy_atomically "$backup/htaccess.protected" "$web/.htaccess" "$(stat -c '%a' -- "$web/.htaccess")"
    [[ ! -e $helper ]] || rm -f -- "$helper"
fi
printf 'Operación %s aprobada. El instalador no cambia marcadores ni Directory Privacy. Respaldo: %s\n' "$1" "$backup"

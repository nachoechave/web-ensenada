set -euo pipefail
umask 077

release=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
home=/home/ensenada
backend=$home/backend-laravel
docroot=$home/public_html/prueba.ensenada.gov.ar
php=/opt/cpanel/ea-php83/root/usr/bin/php
state=$home/iwinds-final-state
photos=$release/photos
markers=(.iwinds-api-public-probe .iwinds-api-admin-probe .iwinds-api-enabled)

die() { printf 'BLOQUEADO: %s\n' "$*" >&2; exit 1; }
plain_file() { [[ -f $1 && ! -L $1 ]]; }
plain_directory() { [[ -d $1 && $(realpath -e -- "$1") == "$1" ]]; }

lock_staging() {
    plain_directory "$backend" && plain_directory "$docroot" || die 'Rutas de staging inesperadas.'
    [[ ! -L $state ]] || die 'El directorio privado de estado es un enlace.'
    mkdir -m 700 -p -- "$state"
    plain_directory "$state" && [[ $(stat -c '%a' -- "$state") == 700 && -O $state ]] ||
        die 'El directorio privado de estado debe pertenecer a la cuenta y tener modo 700.'
    [[ ! -L $state/lock ]] || die 'El archivo de bloqueo es un enlace.'
    exec 9>"$state/lock"
    flock -n 9 || die 'Hay otra operación de este paquete en ejecución.'
}

closed() {
    local marker
    for marker in "${markers[@]}"; do
        [[ ! -e $backend/$marker && ! -L $backend/$marker ]] ||
            die "Cerrá la API antes de continuar ($marker)."
    done
}

atomic_copy() (
    local source=$1 target=$2 mode=$3 scratch
    plain_file "$source" || die "Archivo de origen inválido: $source"
    [[ ! -e $target && ! -L $target ]] || plain_file "$target" || die "Destino inválido: $target"
    scratch=$(mktemp -- "${target%/*}/.iwinds-final.XXXXXXXX")
    trap 'rm -f -- "$scratch"' EXIT
    install -m "$mode" -- "$source" "$scratch"
    mv -fT -- "$scratch" "$target"
)

php_checked() {
    "$php" -d upload_max_filesize=32M -d post_max_size=40M -d memory_limit=256M "$@"
}

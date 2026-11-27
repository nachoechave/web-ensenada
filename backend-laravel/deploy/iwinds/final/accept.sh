#!/usr/bin/env bash
source "$(dirname -- "${BASH_SOURCE[0]}")/common.sh"

[[ $# == 2 && $1 == --write-synthetic && -t 0 ]] ||
    die 'Uso en Terminal: bash accept.sh --write-synthetic RUTA_PRIVADA_DEL_RESPALDO'
lock_staging
closed
(cd "$release" && sha256sum --strict --quiet -c SHA256SUMS)
bash "$release/backup.sh" verify "$2"
plain_file "$docroot/index.html" && cmp -s -- "$photos/angular/index.html" "$docroot/index.html" ||
    die 'El índice Angular del paquete debe estar instalado.'
for bundle in "$photos"/angular/*.{js,css}; do
    [[ -f $bundle ]] || continue
    plain_file "$docroot/${bundle##*/}" && cmp -s -- "$bundle" "$docroot/${bundle##*/}" ||
        die "Falta el bundle ${bundle##*/}."
done

cd "$backend"
php_checked "$release/routes.php" check
php_checked -r 'exit(extension_loaded("curl") && extension_loaded("gd") ? 0 : 1);' ||
    die 'La aceptación necesita cURL y GD en PHP CLI.'
nonce=$("$php" -r 'echo bin2hex(random_bytes(16));')
probe=iwinds-limits-$nonce.php
work=$(mktemp -d -- "$state/accept.XXXXXXXX")
admin_marker=$backend/.iwinds-api-admin-probe
opened=false
cleanup() {
    local result=$?
    trap - EXIT
    if ! rm -f -- "$docroot/$probe"; then result=1; fi
    if [[ $opened == true && ( -e $admin_marker || -L $admin_marker ) ]]; then
        if ! mv -T -- "$admin_marker" "$work/closed-marker"; then result=1; fi
    fi
    if (( result != 0 )); then
        printf 'Aceptación bloqueada. Revisá el cierre de la API y el estado de la prueba en %s\n' "$work" >&2
    fi
    exit "$result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM HUP
atomic_copy "$release/web-limits.php" "$docroot/$probe" 644
php_checked "$release/accept.php" closed "$probe" "$work"
printf 'Se crearán una noticia y fotos sintéticas. Se archivará la noticia y se restaurará el contenido institucional.\n'
read -r -p 'Confirmá base exclusivamente sintética y sin ediciones simultáneas escribiendo STAGING SINTETICO: ' confirm
[[ $confirm == 'STAGING SINTETICO' ]] || die 'La aceptación no fue autorizada.'
opened=true
php_checked deploy/iwinds/provision-staging.php open-admin
php_checked "$release/accept.php" exercise "$probe" "$work"
mv -T -- "$admin_marker" "$work/closed-marker"
opened=false
closed
php_checked "$release/accept.php" closed "$probe" "$work"
printf 'Aceptación HTTPS de staging aprobada. La API quedó cerrada. Evidencia: %s\n' "$work"

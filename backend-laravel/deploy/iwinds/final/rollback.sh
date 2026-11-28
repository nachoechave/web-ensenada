#!/usr/bin/env bash
source "$(dirname -- "${BASH_SOURCE[0]}")/common.sh"

[[ $# == 0 ]] || die 'Uso: bash rollback.sh'
lock_staging
closed
(cd "$release" && sha256sum --strict --quiet -c SHA256SUMS)
index=$docroot/index.html
original=$state/index.original.html
plain_file "$index" && plain_file "$original" || die 'Falta el índice activo o su respaldo.'
if ! cmp -s -- "$index" "$original"; then
    cmp -s -- "$index" "$photos/angular/index.html" || die 'El índice activo pertenece a otra publicación.'
    atomic_copy "$original" "$index" 644
fi
printf 'Angular revertido. Los lectores de imágenes y las referencias WebP siguen disponibles.\n'

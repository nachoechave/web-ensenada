#!/usr/bin/env bash
source "$(dirname -- "${BASH_SOURCE[0]}")/common.sh"

[[ $# == 0 ]] || die 'Uso: bash install.sh'
lock_staging
closed
(cd "$release" && sha256sum --strict --quiet -c SHA256SUMS)

count=0
while IFS= read -r item; do
    case "$item" in
        private/*)
            target=$backend/${item#private/}
            plain_file "$target" && [[ $(realpath -e -- "$target") == "$target" ]] &&
                cmp -s -- "$photos/$item" "$target" ||
                die "El componente privado ${item#private/} difiere. Revisá el paquete de fotos antes de instalar."
            count=$((count + 1))
            ;;
    esac
done < "$photos/FILES"
[[ $count == 9 ]] || die 'El inventario privado debe contener nueve archivos.'
printf 'Nueve componentes privados verificados y conservados.\n'

storage=$backend/storage/app/private
plain_directory "$storage" || die 'La raíz del almacenamiento privado no es válida.'
for directory in noticias sitio ensenada-site-scratch; do
    target=$storage/$directory
    if [[ ! -e $target && ! -L $target ]]; then
        mkdir -m 700 -- "$target"
    fi
    plain_directory "$target" && [[ -O $target && -w $target ]] ||
        die "El almacenamiento privado de $directory debe pertenecer a esta cuenta."
    if [[ $(stat -c '%a' -- "$target") != 700 ]]; then
        chmod 700 -- "$target"
    fi
done

cd "$backend"
php_checked deploy/iwinds/platform.php
cache=$(php_checked "$release/routes.php" path)
[[ $cache == "$backend/bootstrap/cache/routes-v7.php" ]] || die 'Ruta de caché inesperada.'
plain_directory "${cache%/*}" || die 'Directorio de caché inválido.'
if ! php_checked "$release/routes.php" check; then
    previous=$(mktemp -d -- "$state/routes.XXXXXXXX")
    if [[ -e $cache || -L $cache ]]; then
        plain_file "$cache" || die 'Archivo de caché inválido.'
        cp -p -- "$cache" "$previous/routes.php"
    fi
    if ! php_checked artisan route:cache || ! php_checked "$release/routes.php" check; then
        if plain_file "$previous/routes.php"; then
            atomic_copy "$previous/routes.php" "$cache" "$(stat -c '%a' -- "$previous/routes.php")"
        else
            rm -f -- "$cache"
        fi
        die 'Falló la verificación de rutas WebP. Se recuperó el estado anterior.'
    fi
fi

index=$docroot/index.html
original=$state/index.original.html
plain_file "$index" || die 'El índice activo debe ser un archivo regular.'
if ! plain_file "$original"; then
    [[ ! -e $original && ! -L $original ]] || die 'Respaldo de Angular inválido.'
    if cmp -s -- "$photos/angular/index.html" "$index"; then
        prior=$home/iwinds-photos-backup-final/index.html
        plain_file "$prior" && ! cmp -s -- "$photos/angular/index.html" "$prior" ||
            die 'El índice ya está actualizado y falta el respaldo de la versión anterior.'
        atomic_copy "$prior" "$original" 600
    else
        atomic_copy "$index" "$original" 600
    fi
fi
cmp -s -- "$photos/angular/index.html" "$index" || cmp -s -- "$original" "$index" ||
    die 'El índice activo cambió desde el respaldo. Revisá la versión antes de reemplazarlo.'
while IFS= read -r item; do
    case "$item" in
        angular/*.js|angular/*.css)
            target=$docroot/${item#angular/}
            if [[ -e $target || -L $target ]]; then
                plain_file "$target" && cmp -s -- "$photos/$item" "$target" ||
                    die "Colisión del bundle ${item#angular/}."
            else
                atomic_copy "$photos/$item" "$target" 644
            fi
            ;;
    esac
done < "$photos/FILES"
closed
if ! cmp -s -- "$photos/angular/index.html" "$index"; then
    atomic_copy "$photos/angular/index.html" "$index" 644
fi
printf 'Angular instalado. Respaldo anterior: %s\n' "$original"

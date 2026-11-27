#!/usr/bin/env bash
source "$(dirname -- "${BASH_SOURCE[0]}")/common.sh"

[[ $# == 1 && ( $1 == install || $1 == rollback ) ]] || die 'Uso: bash install.sh install|rollback'
state=$home/iwinds-admin-period-state
lock_staging
(cd "$release" && sha256sum --strict --quiet -c SHA256SUMS)

period_lock=$backend/.iwinds-staging-admin-period.lock
[[ ! -L $period_lock && ( ! -e $period_lock || -f $period_lock ) ]] || die 'Bloqueo administrativo inválido.'
exec 8<>"$period_lock"
[[ -f $period_lock && $(stat -c '%a:%h' -- "$period_lock") == 600:1 ]] || die 'Bloqueo administrativo fuera del perfil privado.'
flock -n 8 || die 'Hay otra operación de staging en curso.'
closed

paths=(deploy/iwinds/provision-staging.php app/Support/IwindsPublicProbe.php)
backup=$state/original
[[ ! -L $backup ]] || die 'El respaldo privado es un enlace.'
mkdir -m 700 -p -- "$backup"
plain_directory "$backup" || die 'Ruta de respaldo inválida.'
changes=()
for item in "${paths[@]}"; do
    target=$backend/$item
    saved=$backup/$item
    plain_file "$target" && [[ $(realpath -e -- "$target") == "$target" ]] || die "Destino inválido: $item."
    expected=$(awk -v target="$item" '$2 == target { print $1 }' "$release/BASELINE.sha256")
    [[ $expected =~ ^[a-f0-9]{64}$ ]] || die "No se conoce la versión original de $item."
    current=$(sha256sum -- "$target")
    if [[ ${current%% *} == "$expected" ]]; then
        if [[ $1 == install && ! -e $saved && ! -L $saved ]]; then
            mkdir -m 700 -p -- "${saved%/*}"
            plain_directory "${saved%/*}" || die "Directorio de respaldo inválido para $item."
            atomic_copy "$target" "$saved" "$(stat -c '%a' -- "$target")"
        fi
        [[ $1 != install ]] || changes+=("$item")
    else
        cmp -s -- "$target" "$release/private/$item" || die "Versión inesperada de $item."
        [[ $1 != rollback ]] || changes+=("$item")
    fi
    if [[ $1 == install || ${current%% *} != "$expected" ]]; then
        plain_file "$saved" && [[ $(realpath -e -- "$saved") == "$saved" ]] || die "Falta el respaldo de $item."
        (cd "$backup" && printf '%s  %s\n' "$expected" "$item" | sha256sum --strict --quiet -c -)
    fi
    "$php" -l "$release/private/$item"
done

if [[ ${#changes[@]} -gt 0 ]]; then
    # Cancel any open/renew command still checking the database with the prior generation.
    generation=$("$php" -r 'echo bin2hex(random_bytes(16));')
    printf '%s' "$generation" > "$period_lock"
    if [[ $1 == rollback ]]; then
        paths=(app/Support/IwindsPublicProbe.php deploy/iwinds/provision-staging.php)
    fi
    for item in "${paths[@]}"; do
        if [[ " ${changes[*]} " == *" $item "* ]]; then
            source=$release/private/$item
            [[ $1 != rollback ]] || source=$backup/$item
            atomic_copy "$source" "$backend/$item" "$(stat -c '%a' -- "$backup/$item")"
        fi
    done
fi
closed
printf 'Operación %s verificada. La API sigue cerrada. Respaldo: %s\n' "$1" "$backup"

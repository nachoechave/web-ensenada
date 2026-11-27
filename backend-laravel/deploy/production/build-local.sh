#!/bin/sh
# Local/container tooling only. Not an installation step for iWinds.
set -eu
test "$#" -eq 3 || { echo 'Uso: build-local.sh FUENTE DEPENDENCIAS SALIDA_ZIP' >&2; exit 1; }
source=$(realpath "$1")
dependencies=$(realpath "$2")
cd "$dependencies"
composer validate --no-check-publish
cmp composer.lock "$source/composer.lock"
composer check-platform-reqs --no-dev
composer audit --locked --no-dev
php "$source/deploy/production/build.php" "$source" "$dependencies" "$3"

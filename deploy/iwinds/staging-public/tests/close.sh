#!/usr/bin/env bash
set -euo pipefail
[[ -f /.dockerenv && $IW_FINAL_TEST == yes ]]
cd /home/ensenada/backend-laravel
php -n deploy/iwinds/provision-staging.php close-admin-extended
base=https://prueba.ensenada.gov.ar
code() { curl -sS --cacert /tmp/staging-ca.crt -o /dev/null -w '%{http_code}' "$base$1"; }
[[ $(code /api/admin/usuarios) == 404 ]]
[[ $(code /api/auth/login) == 404 ]]
[[ $(code /) == 200 ]]
php -n deploy/iwinds/provision-staging.php suspend-staging-api
[[ $(code /api/site-content) == 503 ]]
echo 'Cierre administrativo y suspensión total con MariaDB detenida.'

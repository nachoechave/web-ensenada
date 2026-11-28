#!/bin/sh
set -eu
mkdir -p "$HOME/.pki/nssdb"
certutil -N -d "sql:$HOME/.pki/nssdb" --empty-password
certutil -A -d "sql:$HOME/.pki/nssdb" -n ensenada-disposable-ca -t "C,," -i /certs/root.crt
exec npx playwright test "$@"

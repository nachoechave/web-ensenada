#!/bin/sh
set -eu
# No persisted or imported key material; these are disposable local keys.
export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export AUTH_JWT_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"
exec "$@"

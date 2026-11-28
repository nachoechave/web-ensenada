#!/bin/sh
set -eu
caddy run --config /etc/caddy/Caddyfile --adapter caddyfile &
tls_pid=$!
trap 'kill "$tls_pid" 2>/dev/null || true' TERM INT
while [ ! -f /data/caddy/pki/authorities/local/root.crt ]; do
    kill -0 "$tls_pid"
    sleep 0.2
done
cp /data/caddy/pki/authorities/local/root.crt /certs/root.crt
wait "$tls_pid"

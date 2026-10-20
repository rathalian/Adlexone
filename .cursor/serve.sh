#!/usr/bin/env bash
# Keep-alive nginx on port 8000, with four PHP workers behind it.
# php -S alone closes the TCP connection after every file and handles one
# request at a time, so a forwarded port pays a new round trip per asset.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

CONFDIR=/tmp/inlay-web
mkdir -p "$CONFDIR"

if [[ -f "$CONFDIR/nginx.pid" ]] && kill -0 "$(cat "$CONFDIR/nginx.pid")" 2>/dev/null; then
  echo "Inlay already listening on port 8000"
  exit 0
fi
rm -f "$CONFDIR/nginx.pid"

if ! command -v nginx >/dev/null 2>&1; then
  echo "nginx is not installed. The cloud image installs it from .cursor/Dockerfile." >&2
  exit 1
fi

if [[ -f "$CONFDIR/php.pids" ]]; then
  while read -r pid; do
    [[ -n "$pid" ]] && kill "$pid" 2>/dev/null || true
  done < "$CONFDIR/php.pids"
  rm -f "$CONFDIR/php.pids"
fi

sed -e "s#__ROOT__#${ROOT}#g" -e "s#__CONFDIR__#${CONFDIR}#g" \
  "$ROOT/.cursor/nginx.conf" > "$CONFDIR/nginx.conf"

PHP_OPTS=(
  -d opcache.enable_cli=1
  -d opcache.enable=1
  -d opcache.validate_timestamps=1
  -d opcache.revalidate_freq=0
  -d opcache.memory_consumption=128
)

: > "$CONFDIR/php.pids"
for port in 9081 9082 9083 9084; do
  php "${PHP_OPTS[@]}" -S "127.0.0.1:${port}" -t "$ROOT/public" "$ROOT/public/index.php" \
    >"$CONFDIR/php-${port}.log" 2>&1 &
  echo $! >> "$CONFDIR/php.pids"
done

ready=0
for _ in $(seq 1 50); do
  if bash -c 'echo >/dev/tcp/127.0.0.1/9081' 2>/dev/null; then
    ready=1
    break
  fi
  sleep 0.05
done
if [[ "$ready" -ne 1 ]]; then
  echo "PHP workers did not open port 9081" >&2
  cat "$CONFDIR/php-9081.log" >&2 || true
  exit 1
fi

nginx -c "$CONFDIR/nginx.conf" -g 'daemon off;' &
nginx_pid=$!

cleanup() {
  kill "$nginx_pid" 2>/dev/null || true
  if [[ -f "$CONFDIR/php.pids" ]]; then
    while read -r pid; do
      [[ -n "$pid" ]] && kill "$pid" 2>/dev/null || true
    done < "$CONFDIR/php.pids"
  fi
}
trap cleanup EXIT INT TERM

set +e
wait "$nginx_pid"
code=$?
set -e
if [[ "$code" -ne 0 ]]; then
  echo "nginx exited ${code}" >&2
  cat "$CONFDIR/nginx-error.log" >&2 || true
fi
exit "$code"

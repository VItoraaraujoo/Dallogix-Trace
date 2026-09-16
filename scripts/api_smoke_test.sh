#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root_dir"

port="127.0.0.1:8000"
server_pid=""
cleanup() {
  if [[ -n "$server_pid" ]] && kill -0 "$server_pid" 2>/dev/null; then
    kill "$server_pid" >/dev/null 2>&1 || true
    wait "$server_pid" 2>/dev/null || true
  fi
}
trap cleanup EXIT

php -S "$port" -t servidor >/tmp/dallogix_trace_http.log 2>&1 &
server_pid=$!

python3 - "$port" <<'PY'
import json
import socket
import sys
import time

host, port = sys.argv[1].split(':')
port = int(port)
for _ in range(50):
    try:
        with socket.create_connection((host, port), timeout=0.2):
            break
    except OSError:
        time.sleep(0.1)
else:
    raise SystemExit('Servidor PHP não respondeu no tempo esperado.')
PY

status_me=$(curl -sS -o /tmp/me.out -w '%{http_code}' http://127.0.0.1:8000/api/me.php || true)
status_login=$(curl -sS -H 'Content-Type: application/json' -d '{"email":"x","password":"y"}' -o /tmp/login.out -w '%{http_code}' http://127.0.0.1:8000/api/login.php || true)

if [[ "$status_me" != "401" ]]; then
  echo "ERRO: /api/me.php deve exigir autenticação." >&2
  cat /tmp/me.out >&2 || true
  exit 1
fi

if [[ "$status_login" != "401" && "$status_login" != "400" && "$status_login" != "415" ]]; then
  echo "ERRO: /api/login.php retornou status inesperado: $status_login" >&2
  cat /tmp/login.out >&2 || true
  exit 1
fi

echo "OK: smoke test de autenticação e entrada da API validado."

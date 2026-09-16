#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa3-cookie.txt"

status() {
  curl -sS -o /dev/null -w '%{http_code}' "$@"
}

if [[ "$(status "$base_url/api/me.php")" != "401" ]]; then
  echo "FAIL: /api/me.php deveria exigir sessão"
  exit 1
fi

login_response="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login_response" | grep -q '"authenticated":true'; then
  echo "FAIL: login válido não autenticou: $login_response"
  exit 1
fi

if [[ "$(status -b "$cookie_file" "$base_url/api/me.php")" != "200" ]]; then
  echo "FAIL: sessão não foi reconhecida"
  exit 1
fi

if [[ "$(status -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"senha-errada"}' "$base_url/api/login.php")" != "401" ]]; then
  echo "FAIL: senha inválida foi aceita"
  exit 1
fi

if [[ "$(status -b "$cookie_file" -X POST "$base_url/api/logout.php")" != "200" ]]; then
  echo "FAIL: logout não respondeu 200"
  exit 1
fi

if [[ "$(status -b "$cookie_file" "$base_url/api/me.php")" != "401" ]]; then
  echo "FAIL: sessão continuou válida após logout"
  exit 1
fi

echo "OK: sessão protegida, login válido, senha inválida e logout validados."

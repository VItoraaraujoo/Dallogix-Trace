#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa6-cookie.txt"

status() { curl -sS -o /dev/null -w '%{http_code}' "$@"; }

if [[ "$(status "$base_url/api/carregamentos.php")" != "401" ]]; then
  echo "FAIL: carregamentos sem sessão não está protegido"
  exit 1
fi

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

loading="$(curl -sS -b "$cookie_file" "$base_url/api/carregamentos.php")"
if ! printf '%s' "$loading" | grep -q '"romaneio_number"'; then
  echo "FAIL: carregamento ativo não foi retornado: $loading"
  exit 1
fi
if ! printf '%s' "$loading" | grep -q '"valid_readings"'; then
  echo "FAIL: leituras válidas não foram retornadas: $loading"
  exit 1
fi

echo "OK: tela de trabalho possui carregamento, estado e leituras persistidas."

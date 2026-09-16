#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa10-cookie.txt"

unauth="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/produtos.php")"
if [[ "$unauth" != "401" ]]; then
  echo "FAIL: produtos sem sessão não está protegido"
  exit 1
fi

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

products="$(curl -sS -b "$cookie_file" "$base_url/api/produtos.php")"
if ! printf '%s' "$products" | grep -q '"code":"PROD3"'; then
  echo "FAIL: produto PROD3 não foi retornado"
  exit 1
fi
if ! printf '%s' "$products" | grep -q '7898250782592'; then
  echo "FAIL: barcode do produto não foi retornado"
  exit 1
fi

echo "OK: catálogo persistido protegido e disponível para a tela de produtos."

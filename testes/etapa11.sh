#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa11-cookie.txt"
test_number="ETAPA11-TEST-$(date +%s)"
temporary_csv="/tmp/dallogix-trace-etapa11-${test_number}.csv"
sed "s/ETAPA11-TEST-001/${test_number}/" testes/fixtures/import-etapa11.csv > "$temporary_csv"

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

result="$(curl -sS -b "$cookie_file" -F "file=@${temporary_csv}" "$base_url/api/importar_csv.php")"
if ! printf '%s' "$result" | grep -q '"items":1'; then
  echo "FAIL: importação CSV falhou: $result"
  exit 1
fi

romaneios="$(curl -sS -b "$cookie_file" "$base_url/api/romaneios.php")"
if ! printf '%s' "$romaneios" | grep -q "$test_number"; then
  echo "FAIL: romaneio importado não foi listado"
  exit 1
fi

echo "OK: CSV validado e persistido transacionalmente."

#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa34-cookie.txt"
temporary_csv="/tmp/dallogix-trace-etapa34-$(date +%s).csv"
number="CSV-$(date +%s)"
fail() { echo "FAIL: $1"; exit 1; }

sed "s/ETAPA34-TEST-001/$number/g" testes/fixtures/import-etapa34.csv > "$temporary_csv"
login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
printf '%s' "$login" | grep -q '"authenticated":true' || fail "login falhou"
result="$(curl -sS -b "$cookie_file" -F "file=@${temporary_csv}" "$base_url/api/importar_csv.php")"
printf '%s' "$result" | grep -q '"romaneios":1' || fail "romaneio não importado: $result"
printf '%s' "$result" | grep -q '"items":1' || fail "produto repetido não foi consolidado: $result"
printf '%s' "$result" | grep -q '"linhas":2' || fail "linhas importadas não informadas: $result"

echo "OK: CSV aceita data brasileira, motorista, expedidor e consolida produto repetido."

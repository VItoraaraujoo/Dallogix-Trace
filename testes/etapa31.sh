#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
equipment_id="${TRACE_TEST_EQUIPMENT_ID:-$(docker compose exec -T mysql mysql -N -utrace -pchange-me-local trace_local -e "SELECT e.id FROM equipamentos e WHERE NOT EXISTS (SELECT 1 FROM carregamentos c WHERE c.equipment_id = e.id AND c.state <> 'FINALIZADO') ORDER BY e.id LIMIT 1" 2>/dev/null | tr -d '\r' | head -n 1)}"
cookie_file="/tmp/dallogix-trace-etapa31-cookie.txt"
number="READ-$(date +%s)"
plate="RDR$(date +%s | tail -c 7)"
fail() { echo "FAIL: $1"; exit 1; }

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
printf '%s' "$login" | grep -q '"authenticated":true' || fail "login falhou"
manifest="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"number\":\"$number\",\"scheduled_date\":\"$(date +%F)\",\"plate\":\"$plate\",\"product_code\":\"PROD3\",\"planned_quantity\":2}" "$base_url/api/romaneios.php")"
manifest_id="$(printf '%s' "$manifest" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
truck_id="$(printf '%s' "$manifest" | sed -n 's/.*"truck_id":\([0-9][0-9]*\).*/\1/p')"
prepared="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"romaneio_id\":$manifest_id,\"truck_id\":$truck_id,\"equipment_id\":$equipment_id}" "$base_url/api/carregamentos.php")"
loading_id="$(printf '%s' "$prepared" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
[ -n "$loading_id" ] || fail "não foi possível preparar carregamento: $prepared"

blocked="$(curl -sS -o /tmp/dallogix-trace-etapa31-blocked.json -w '%{http_code}' -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"barcode\":\"7898250782592\"}" "$base_url/api/leituras.php")"
[ "$blocked" = "409" ] || fail "leitura foi aceita fora de CARREGANDO"

curl -sS -b "$cookie_file" -X PATCH -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"state\":\"CARREGANDO\"}" "$base_url/api/estado_carregamento.php" >/dev/null
accepted="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"barcode\":\"7898250782592\"}" "$base_url/api/leituras.php")"
printf '%s' "$accepted" | grep -q '"result":"VALIDO"' || fail "leitura válida não foi aceita: $accepted"
curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"justification\":\"Fixture local finalizada com divergência prevista.\"}" "$base_url/api/encerrar_carregamento.php" >/dev/null

echo "OK: scanner só contabiliza leitura com esteira em CARREGANDO."

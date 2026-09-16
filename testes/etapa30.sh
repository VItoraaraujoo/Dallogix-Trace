#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
equipment_id="${TRACE_TEST_EQUIPMENT_ID:-$(docker compose exec -T mysql mysql -N -utrace -pchange-me-local trace_local -e "SELECT e.id FROM equipamentos e WHERE NOT EXISTS (SELECT 1 FROM carregamentos c WHERE c.equipment_id = e.id AND c.state <> 'FINALIZADO') ORDER BY e.id LIMIT 1" 2>/dev/null | tr -d '\r' | head -n 1)}"
gateway_token="${TRACE_DEVICE_TOKEN:-trace-device-local-token-2026-v1}"
cookie_file="/tmp/dallogix-trace-etapa30-cookie.txt"
number="PREP-$(date +%s)"
plate="PRP$(date +%s | tail -c 7)"
fail() { echo "FAIL: $1"; exit 1; }

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
printf '%s' "$login" | grep -q '"authenticated":true' || fail "login falhou"

manifest="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"number\":\"$number\",\"scheduled_date\":\"$(date +%F)\",\"plate\":\"$plate\",\"product_code\":\"PROD3\",\"planned_quantity\":2}" "$base_url/api/romaneios.php")"
manifest_id="$(printf '%s' "$manifest" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
truck_id="$(printf '%s' "$manifest" | sed -n 's/.*"truck_id":\([0-9][0-9]*\).*/\1/p')"
[ -n "$manifest_id" ] && [ -n "$truck_id" ] || fail "romaneio de teste não foi criado: $manifest"

prepared="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"romaneio_id\":$manifest_id,\"truck_id\":$truck_id,\"equipment_id\":$equipment_id}" "$base_url/api/carregamentos.php")"
loading_id="$(printf '%s' "$prepared" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
printf '%s' "$prepared" | grep -q '"state":"PREPARANDO"' || fail "preparação falhou: $prepared"

curl -sS -H 'Content-Type: application/json' -H "X-Device-Token: $gateway_token" -d "{\"equipment_id\":$equipment_id,\"device_type\":\"CLP\",\"status\":\"ONLINE\"}" "$base_url/api/device_heartbeat.php" >/dev/null

duplicate_code="$(curl -sS -o /dev/null -w '%{http_code}' -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"romaneio_id\":$manifest_id,\"truck_id\":$truck_id,\"equipment_id\":$equipment_id}" "$base_url/api/carregamentos.php")"
[ "$duplicate_code" = "409" ] || fail "duplicidade de Dala/caminhão foi aceita"

started="$(curl -sS -b "$cookie_file" -X PATCH -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"state\":\"CARREGANDO\"}" "$base_url/api/estado_carregamento.php")"
printf '%s' "$started" | grep -q '"state":"CARREGANDO"' || fail "transição para carregando falhou: $started"

finished="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"justification\":\"Fixture local finalizada com divergência prevista.\"}" "$base_url/api/encerrar_carregamento.php")"
printf '%s' "$finished" | grep -q '"romaneio_finalizado":true' || fail "finalização não consolidou romaneio: $finished"

detail="$(curl -sS -b "$cookie_file" "$base_url/api/romaneios.php?id=$manifest_id")"
printf '%s' "$detail" | grep -q '"status":"FINALIZADO"' || fail "romaneio não foi finalizado: $detail"

grep -q 'prepare-loading-form' interface/js/telas/operacoes.js || fail "tela de preparação ausente"
grep -q 'prepareLoading(payload)' interface/js/classes/ArmazenamentoTrace.js || fail "store sem preparação de carregamento"

echo "OK: romaneio -> caminhão -> Dala -> preparação -> finalização validados."

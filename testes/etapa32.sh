#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
equipment_id="${TRACE_TEST_EQUIPMENT_ID:-$(docker compose exec -T mysql mysql -N -utrace -pchange-me-local trace_local -e "SELECT e.id FROM equipamentos e WHERE NOT EXISTS (SELECT 1 FROM carregamentos c WHERE c.equipment_id = e.id AND c.state <> 'FINALIZADO') ORDER BY e.id LIMIT 1" 2>/dev/null | tr -d '\r' | head -n 1)}"
gateway_token="${TRACE_DEVICE_TOKEN:-trace-device-local-token-2026-v1}"
cookie_file="/tmp/dallogix-trace-etapa32-cookie.txt"
number="PLC-$(date +%s)"
plate="PLC$(date +%s | tail -c 7)"
fail() { echo "FAIL: $1"; exit 1; }

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
printf '%s' "$login" | grep -q '"authenticated":true' || fail "login falhou"

manifest="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"number\":\"$number\",\"scheduled_date\":\"$(date +%F)\",\"plate\":\"$plate\",\"product_code\":\"PROD3\",\"planned_quantity\":2}" "$base_url/api/romaneios.php")"
manifest_id="$(printf '%s' "$manifest" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
truck_id="$(printf '%s' "$manifest" | sed -n 's/.*"truck_id":\([0-9][0-9]*\).*/\1/p')"
[ -n "$manifest_id" ] && [ -n "$truck_id" ] || fail "romaneio de teste não foi criado: $manifest"

prepared="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"romaneio_id\":$manifest_id,\"truck_id\":$truck_id,\"equipment_id\":$equipment_id}" "$base_url/api/carregamentos.php")"
loading_id="$(printf '%s' "$prepared" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
[ -n "$loading_id" ] || fail "não foi possível preparar carregamento: $prepared"

curl -sS -H 'Content-Type: application/json' -H "X-Device-Token: $gateway_token" -d "{\"equipment_id\":$equipment_id,\"device_type\":\"CLP\",\"status\":\"ONLINE\"}" "$base_url/api/device_heartbeat.php" >/dev/null
curl -sS -b "$cookie_file" -X PATCH -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"state\":\"PAUSADO\"}" "$base_url/api/estado_carregamento.php" >/dev/null
requested="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"command\":\"REVERSAO_ATIVAR\"}" "$base_url/api/comando_maquina.php")"
request_id="$(printf '%s' "$requested" | sed -n 's/.*"command_request_id":\([0-9][0-9]*\).*/\1/p')"
[ -n "$request_id" ] || fail "reversão não entrou na fila: $requested"

unauthorized="$(curl -sS -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d "{\"action\":\"CLAIM\",\"equipment_id\":$equipment_id}" "$base_url/api/plc_gateway.php")"
[ "$unauthorized" = "401" ] || fail "gateway aceitou consumo sem token"

claimed="$(curl -sS -H 'Content-Type: application/json' -H "X-Device-Token: $gateway_token" -d "{\"action\":\"CLAIM\",\"equipment_id\":$equipment_id}" "$base_url/api/plc_gateway.php")"
printf '%s' "$claimed" | grep -q "\"id\":$request_id" || fail "gateway não reservou o comando correto: $claimed"
completed="$(curl -sS -H 'Content-Type: application/json' -H "X-Device-Token: $gateway_token" -d "{\"action\":\"COMPLETE\",\"request_id\":$request_id,\"status\":\"REJEITADO\",\"message\":\"Mapa de I/O ainda não homologado\"}" "$base_url/api/plc_gateway.php")"
printf '%s' "$completed" | grep -q '"status":"REJEITADO"' || fail "gateway não concluiu retorno seguro: $completed"
visible="$(curl -sS -b "$cookie_file" "$base_url/api/comandos_industriais.php?carregamento_id=$loading_id")"
printf '%s' "$visible" | grep -q '"status":"REJEITADO"' || fail "painel não recebeu o estado do gateway: $visible"

curl -sS -b "$cookie_file" -X PATCH -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"state\":\"CARREGANDO\"}" "$base_url/api/estado_carregamento.php" >/dev/null
curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"justification\":\"Fixture local finalizada com divergência prevista.\"}" "$base_url/api/encerrar_carregamento.php" >/dev/null

echo "OK: fila de reversão, autenticação e retorno seguro do gateway validados."

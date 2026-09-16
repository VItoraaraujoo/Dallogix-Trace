#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa17-cookie.txt"
source "$(cd "$(dirname "$0")" && pwd)/lib/ensure_loading.sh"
normal_uuid="66666666-6666-4666-8666-$(printf '%012d' "$(date +%s)")"
incident_uuid="77777777-7777-4777-8777-$(printf '%012d' "$(date +%s)")"

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

loading_id="${TRACE_LOADING_ID:-$(ensure_loading_carregando)}"
[ -n "$loading_id" ] || { echo "FAIL: nenhum carregamento CARREGANDO disponível"; exit 1; }
equipment_id="${TRACE_EQUIPMENT_ID:-$(curl -sS -b "$cookie_file" "$base_url/api/carregamentos.php" | sed -n 's/.*"id":'"$loading_id"',"state":"[^"]*","equipment_id":\([0-9][0-9]*\).*/\1/p' | head -n 1)}"

normal="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"equipment_id\":$equipment_id,\"event_uuid\":\"$normal_uuid\"}" "$base_url/api/sensor_eventos.php")"
normal_id="$(printf '%s' "$normal" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
valid="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"sensor_event_id\":$normal_id,\"barcode\":\"7898250782592\"}" "$base_url/api/leituras.php")"
if ! printf '%s' "$valid" | grep -q 'NAO_SOLICITADA'; then
  echo "FAIL: leitura válida não deveria solicitar foto: $valid"
  exit 1
fi

sleep 1
incident="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"equipment_id\":$equipment_id,\"event_uuid\":\"$incident_uuid\"}" "$base_url/api/sensor_eventos.php")"
incident_id="$(printf '%s' "$incident" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
failed="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"sensor_event_id\":$incident_id}" "$base_url/api/leituras.php")"
if ! printf '%s' "$failed" | grep -q 'CAPTURA_PENDENTE_INCIDENTE'; then
  echo "FAIL: captura do incidente não permaneceu pendente: $failed"
  exit 1
fi

requests="$(curl -sS -b "$cookie_file" "$base_url/api/camera_requests.php")"
if ! printf '%s' "$requests" | grep -q '"reason":"SEM_LEITURA"'; then
  echo "FAIL: pedido de foto não registrou o motivo do incidente: $requests"
  exit 1
fi

echo "OK: leitura válida não solicita foto; incidente solicita foto com motivo rastreável."

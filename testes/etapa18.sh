#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa18-cookie.txt"

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

loading_id="${TRACE_LOADING_ID:-$(curl -sS -b "$cookie_file" "$base_url/api/carregamentos.php" | sed -n 's/.*"id":\([0-9][0-9]*\),"state":"CARREGANDO".*/\1/p' | head -n 1)}"
[ -n "$loading_id" ] || { echo "FAIL: nenhum carregamento disponível"; exit 1; }

equipment_id="${TRACE_EQUIPMENT_ID:-$(curl -sS -b "$cookie_file" "$base_url/api/carregamentos.php" | sed -n 's/.*"id":'"$loading_id"',"state":"[^"]*","equipment_id":\([0-9][0-9]*\).*/\1/p' | head -n 1)}"
sleep 1
event_uuid="55555555-5555-4555-8555-$(printf '%012d' "$(date +%s)")"
event="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"equipment_id\":$equipment_id,\"event_uuid\":\"$event_uuid\"}" "$base_url/api/sensor_eventos.php")"
event_id="$(printf '%s' "$event" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
[ -n "$event_id" ] || { echo "FAIL: evento de sensor não foi criado: $event"; exit 1; }
pending="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"sensor_event_id\":$event_id}" "$base_url/api/leituras.php")"
printf '%s' "$pending" | grep -q 'CAPTURA_PENDENTE_INCIDENTE' || { echo "FAIL: não foi possível criar evidência pendente: $pending"; exit 1; }
summary="$(curl -sS -b "$cookie_file" "$base_url/api/resumo_carregamento.php?carregamento_id=$loading_id")"
if ! printf '%s' "$summary" | grep -q '"capturas_pendentes"'; then
  echo "FAIL: resumo não retornou capturas pendentes"
  exit 1
fi

finish="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id}" "$base_url/api/encerrar_carregamento.php")"
if ! printf '%s' "$finish" | grep -q 'capturas de incidentes pendentes'; then
  echo "FAIL: encerramento ignorou evidências pendentes: $finish"
  exit 1
fi

echo "OK: resumo final disponível e encerramento bloqueia evidências pendentes."

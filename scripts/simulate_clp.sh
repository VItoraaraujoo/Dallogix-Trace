#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-sim-clp-cookie.txt"
loading_id="${TRACE_LOADING_ID:-}"
equipment_id="${TRACE_EQUIPMENT_ID:-}"
device_token="${TRACE_DEVICE_TOKEN:-}"
barcode="${1:-7898250782592}"
event_suffix="$(printf '%04x%08x' "$RANDOM" "$(date +%s)")"
event_uuid="88888888-8888-4888-8888-${event_suffix}"

if [[ -z "$device_token" ]]; then
  echo "Defina TRACE_DEVICE_TOKEN com a credencial do CLP simulado."
  exit 1
fi

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "Falha no login do simulador: $login"
  exit 1
fi

loading_json="$(curl -sS -b "$cookie_file" "$base_url/api/carregamentos.php")"
if [[ -z "$loading_id" ]]; then
  loading_id="$(printf '%s' "$loading_json" | sed -n 's/.*"id":\([0-9][0-9]*\),"state":"CARREGANDO".*/\1/p' | head -n 1)"
fi
if [[ -z "$loading_id" ]]; then
  loading_id="$(printf '%s' "$loading_json" | sed -n 's/.*"id":\([0-9][0-9]*\),"state":"PREPARANDO".*/\1/p' | head -n 1)"
  if [[ -n "$loading_id" ]]; then
    promoted="$(curl -sS -b "$cookie_file" -X PATCH -H 'Content-Type: application/json' \
      -d "{\"carregamento_id\":${loading_id},\"state\":\"CARREGANDO\"}" \
      "$base_url/api/estado_carregamento.php")"
    if ! printf '%s' "$promoted" | grep -q '"state":"CARREGANDO"'; then
      echo "Não foi possível colocar o carregamento simulado em CARREGANDO: $promoted"
      exit 1
    fi
    loading_json="$(curl -sS -b "$cookie_file" "$base_url/api/carregamentos.php")"
  fi
fi
if [[ -z "$equipment_id" && -n "$loading_id" ]]; then
  equipment_id="$(printf '%s' "$loading_json" | sed -n 's/.*"id":'"$loading_id"',"state":"[^"]*","equipment_id":\([0-9][0-9]*\).*/\1/p' | head -n 1)"
fi
if [[ -z "$loading_id" || -z "$equipment_id" ]]; then
  echo "Nenhum carregamento CARREGANDO disponível para o simulador. Use TRACE_LOADING_ID e TRACE_EQUIPMENT_ID."
  exit 1
fi

heartbeat="$(curl -sS -X POST \
  -H 'Content-Type: application/json' \
  -H "X-Device-Token: ${device_token}" \
  -d "{\"equipment_id\":${equipment_id},\"device_type\":\"CLP\",\"status\":\"ONLINE\",\"details\":{\"source\":\"pc-simulator\"}}" \
  "$base_url/api/device_heartbeat.php")"
if ! printf '%s' "$heartbeat" | grep -q '"status":"ONLINE"'; then
  echo "Falha no heartbeat do CLP simulado: $heartbeat"
  exit 1
fi
echo "Heartbeat local: CLP ONLINE"

state="$(curl -sS -b "$cookie_file" -X PATCH -H 'Content-Type: application/json' -d "{\"carregamento_id\":${loading_id},\"state\":\"CARREGANDO\"}" "$base_url/api/estado_carregamento.php")"
echo "CLP estado: $state"

sleep 1
event="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":${loading_id},\"equipment_id\":${equipment_id},\"event_uuid\":\"${event_uuid}\"}" "$base_url/api/sensor_eventos.php")"
event_id="$(printf '%s' "$event" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
if [[ -z "$event_id" ]]; then
  echo "Falha no sensor simulado: $event"
  exit 1
fi
echo "Sensor detectou saco: evento ${event_id}"

if [[ "$barcode" == "SEM_LEITURA" ]]; then
  reading="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":${loading_id},\"sensor_event_id\":${event_id}}" "$base_url/api/leituras.php")"
else
  reading="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":${loading_id},\"sensor_event_id\":${event_id},\"barcode\":\"${barcode}\"}" "$base_url/api/leituras.php")"
fi
echo "Scanner simulado: $reading"
echo "Simulação concluída."

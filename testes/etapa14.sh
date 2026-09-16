#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa14-cookie.txt"

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

heartbeat="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d '{"equipment_id":1,"device_type":"CLP","status":"ONLINE","details":{"source":"teste"}}' "$base_url/api/dispositivos.php")"
if ! printf '%s' "$heartbeat" | grep -q '"status":"ONLINE"'; then
  echo "FAIL: heartbeat não foi aceito: $heartbeat"
  exit 1
fi

devices="$(curl -sS -b "$cookie_file" "$base_url/api/dispositivos.php")"
if ! printf '%s' "$devices" | grep -q '"device_type":"CLP"'; then
  echo "FAIL: dispositivo não apareceu na consulta"
  exit 1
fi

monitoring="$(curl -sS -b "$cookie_file" "$base_url/api/monitoramento.php")"
if ! printf '%s' "$monitoring" | grep -q '"dispositivos"'; then
  echo "FAIL: dispositivo não apareceu no monitoramento"
  exit 1
fi

echo "OK: heartbeat e status dos dispositivos persistidos e monitorados."

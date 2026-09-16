#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa7-cookie.txt"
source "$(cd "$(dirname "$0")" && pwd)/lib/ensure_loading.sh"
db_name="${MYSQL_DATABASE:-trace_local}"
db_password="${MYSQL_ROOT_PASSWORD:-change-me-root}"

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

loading_id="${TRACE_LOADING_ID:-$(ensure_loading_carregando)}"
[ -n "$loading_id" ] || { echo "FAIL: nenhum carregamento CARREGANDO disponível"; exit 1; }

reading="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"carregamento_id\":$loading_id,\"barcode\":\"7898250782592\"}" "$base_url/api/leituras.php")"
if ! printf '%s' "$reading" | grep -q '"result":"VALIDO"'; then
  echo "FAIL: leitura operacional falhou: $reading"
  exit 1
fi

audit_count="$(docker compose exec -T mysql mysql -N -B -u root "-p${db_password}" "$db_name" -e "SELECT COUNT(*) FROM logs_auditoria WHERE action='LEITURA_REGISTRADA';" 2>/dev/null)"
sync_count="$(docker compose exec -T mysql mysql -N -B -u root "-p${db_password}" "$db_name" -e "SELECT COUNT(*) FROM fila_sincronizacao WHERE aggregate_type='leitura';" 2>/dev/null)"

if [[ -z "$audit_count" || "$audit_count" -lt 1 ]]; then
  echo "FAIL: auditoria não foi registrada"
  exit 1
fi
if [[ -z "$sync_count" || "$sync_count" -lt 1 ]]; then
  echo "FAIL: fila de sincronização não foi registrada"
  exit 1
fi

echo "OK: auditoria e fila local registraram a leitura operacional."

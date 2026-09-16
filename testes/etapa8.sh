#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa8-cookie.txt"

unauth="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/monitoramento.php")"
if [[ "$unauth" != "401" ]]; then
  echo "FAIL: monitoramento sem sessão não está protegido"
  exit 1
fi

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

dashboard="$(curl -sS -b "$cookie_file" "$base_url/api/monitoramento.php")"
for field in romaneios leituras ocorrencias auditoria sync_pendente; do
  if ! printf '%s' "$dashboard" | grep -q "\"$field\""; then
    echo "FAIL: campo ausente no monitoramento: $field"
    exit 1
  fi
done

echo "OK: painel de monitoramento protegido e com dados persistidos."

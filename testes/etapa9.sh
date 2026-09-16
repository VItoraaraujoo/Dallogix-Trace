#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa9-cookie.txt"

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

loading_id="${TRACE_LOADING_ID:-$(curl -sS -b "$cookie_file" "$base_url/api/carregamentos.php" | sed -n 's/.*"id":\([0-9][0-9]*\),"state":"[^"]*".*/\1/p' | head -n 1)}"
[ -n "$loading_id" ] || { echo "FAIL: nenhum carregamento disponível"; exit 1; }

created="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "{\"type\":\"Parada de máquina\",\"quantity\":1,\"description\":\"Teste automatizado\",\"carregamento_id\":$loading_id}" "$base_url/api/ocorrencias.php")"
if ! printf '%s' "$created" | grep -q '"type":"Parada de máquina"'; then
  echo "FAIL: ocorrência não foi criada: $created"
  exit 1
fi

list="$(curl -sS -b "$cookie_file" "$base_url/api/ocorrencias.php")"
if ! printf '%s' "$list" | grep -q '"description":"Teste automatizado"'; then
  echo "FAIL: ocorrência não apareceu na listagem"
  exit 1
fi

dashboard="$(curl -sS -b "$cookie_file" "$base_url/api/monitoramento.php")"
if ! printf '%s' "$dashboard" | grep -q '"ocorrencias"'; then
  echo "FAIL: ocorrência não apareceu no monitoramento"
  exit 1
fi

echo "OK: ocorrência criada, listada e refletida no monitoramento."

#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa12-cookie.txt"
db_name="${MYSQL_DATABASE:-trace_local}"
db_password="${MYSQL_ROOT_PASSWORD:-change-me-root}"

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login falhou"
  exit 1
fi

before="$(docker compose exec -T mysql mysql -N -B -u root "-p${db_password}" "$db_name" -e 'SELECT COUNT(*) FROM romaneios;' 2>/dev/null)"
response="$(curl -sS -b "$cookie_file" -F 'file=@testes/fixtures/import-etapa12-invalido.csv' "$base_url/api/importar_csv.php")"
if ! printf '%s' "$response" | grep -q 'Produto PRODUTO-QUE-NAO-EXISTE'; then
  echo "FAIL: CSV inválido não foi rejeitado: $response"
  exit 1
fi

after="$(docker compose exec -T mysql mysql -N -B -u root "-p${db_password}" "$db_name" -e 'SELECT COUNT(*) FROM romaneios;' 2>/dev/null)"
if [[ "$before" != "$after" ]]; then
  echo "FAIL: rollback não preservou a contagem ($before -> $after)"
  exit 1
fi

echo "OK: CSV inválido rejeitado e rollback confirmado ($after romaneios)."

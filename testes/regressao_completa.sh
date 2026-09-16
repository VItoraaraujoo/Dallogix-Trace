#!/usr/bin/env bash
set -u

root="$(cd "$(dirname "$0")/.." && pwd)"
pass=0
fail_count=0

# Garante uma operação local de teste sem acionar nenhum equipamento físico.
fixture_cookie="/tmp/dallogix-trace-regression-fixture.txt"
base_url="${TRACE_BASE_URL:-http://localhost:8080}"
login="$(curl -sS -c "$fixture_cookie" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if printf '%s' "$login" | grep -q '"authenticated":true'; then
  active="$(curl -sS -b "$fixture_cookie" "$base_url/api/carregamentos.php")"
  if ! printf '%s' "$active" | grep -Eq '"state":"(PREPARANDO|CARREGANDO|EMERGENCIA|PAUSADO)"'; then
    number="FIXTURE-$(date +%s%N)"
    plate="FIX$(date +%s%N | tail -c 8)"
    manifest_date="$(date +%Y-%m-%d)"
    manifest="$(curl -sS -b "$fixture_cookie" -H 'Content-Type: application/json' -d "{\"number\":\"$number\",\"scheduled_date\":\"$manifest_date\",\"plate\":\"$plate\",\"product_code\":\"PROD3\",\"planned_quantity\":20}" "$base_url/api/romaneios.php")"
    manifest_id="$(printf '%s' "$manifest" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
    truck_id="$(printf '%s' "$manifest" | sed -n 's/.*"truck_id":\([0-9][0-9]*\).*/\1/p')"
    equipment_ids="$(curl -sS -b "$fixture_cookie" "$base_url/api/equipamentos.php" | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{for(const x of (JSON.parse(s).data||[]).reverse()) console.log(x.id)})')"
    prepared=""
    while IFS= read -r equipment_id; do
      [ -n "$equipment_id" ] || continue
      candidate="$(curl -sS -b "$fixture_cookie" -H 'Content-Type: application/json' -d "{\"romaneio_id\":$manifest_id,\"truck_id\":$truck_id,\"equipment_id\":$equipment_id}" "$base_url/api/carregamentos.php")"
      if printf '%s' "$candidate" | grep -q '"state":"PREPARANDO"'; then
        prepared="$candidate"
        break
      fi
    done <<< "$equipment_ids"
    if [[ -z "$prepared" ]]; then
      echo "FAIL: não foi possível criar fixture de carregamento local" >&2
      exit 1
    fi
  fi
fi

for i in $(seq 1 37); do
  test_file="$root/testes/etapa${i}.sh"
  [ -f "$test_file" ] || continue
  if bash "$test_file"; then
    pass=$((pass + 1))
  else
    fail_count=$((fail_count + 1))
  fi
done
echo "Resultado da regressão: ${pass} aprovadas; ${fail_count} falhas."
[ "$fail_count" -eq 0 ]

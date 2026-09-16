#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa4-cookie.txt"
number="TEST-$(date +%s)"
scheduled_date="$(date +%F)"

status() {
  curl -sS -o /dev/null -w '%{http_code}' "$@"
}

if [[ "$(status "$base_url/api/romaneios.php")" != "401" ]]; then
  echo "FAIL: romaneios sem sessão não está protegido"
  exit 1
fi

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login" | grep -q '"authenticated":true'; then
  echo "FAIL: login da etapa 4 falhou"
  exit 1
fi

if [[ "$(status -b "$cookie_file" "$base_url/api/produtos.php")" != "200" ]]; then
  echo "FAIL: listagem de produtos falhou"
  exit 1
fi

payload="{\"number\":\"${number}\",\"scheduled_date\":\"${scheduled_date}\",\"plate\":\"TST4$(date +%s | tail -c 5)\",\"product_code\":\"PROD3\",\"planned_quantity\":10}"
created="$(curl -sS -b "$cookie_file" -H 'Content-Type: application/json' -d "$payload" "$base_url/api/romaneios.php")"
if ! printf '%s' "$created" | grep -q '"status":"AGUARDANDO"'; then
  echo "FAIL: criação de romaneio falhou: $created"
  exit 1
fi

romaneio_id="$(printf '%s' "$created" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
product_id="$(curl -sS -b "$cookie_file" "$base_url/api/produtos.php" | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{const p=(JSON.parse(s).data||[]).find(x=>x.code==="PROD3");if(p)process.stdout.write(String(p.id))})')"
updated_number="${number}-EDIT"
update_payload="{\"action\":\"update\",\"romaneio_id\":${romaneio_id},\"number\":\"${updated_number}\",\"scheduled_date\":\"${scheduled_date}\",\"plate\":\"EDT4\",\"expedidor\":\"Expedidor editado\",\"driver_name\":\"Motorista editado\",\"items\":[{\"product_id\":${product_id},\"quantity\":12}]}"
updated="$(curl -sS -b "$cookie_file" -X PATCH -H 'Content-Type: application/json' -d "$update_payload" "$base_url/api/romaneios.php")"
if ! printf '%s' "$updated" | grep -q "\"id\":${romaneio_id}"; then
  echo "FAIL: atualização de romaneio falhou: $updated"
  exit 1
fi
detail="$(curl -sS -b "$cookie_file" "$base_url/api/romaneios.php?id=${romaneio_id}")"
if ! printf '%s' "$detail" | grep -q "$updated_number" || ! printf '%s' "$detail" | grep -q '"planned_quantity":12'; then
  echo "FAIL: alteração não persistiu no romaneio: $detail"
  exit 1
fi

duplicate_payload="{\"number\":\"${updated_number}\",\"scheduled_date\":\"${scheduled_date}\",\"plate\":\"DUP4\",\"product_code\":\"PROD3\",\"planned_quantity\":10}"
if [[ "$(status -b "$cookie_file" -H 'Content-Type: application/json' -d "$duplicate_payload" "$base_url/api/romaneios.php")" != "409" ]]; then
  echo "FAIL: romaneio duplicado foi aceito"
  exit 1
fi

if [[ "$(status -b "$cookie_file" "$base_url/api/romaneios.php")" != "200" ]]; then
  echo "FAIL: listagem de romaneios falhou"
  exit 1
fi

filtered="$(curl -sS -b "$cookie_file" "$base_url/api/romaneios.php?number=${updated_number}&status=AGUARDANDO&date_from=${scheduled_date}&date_to=${scheduled_date}")"
if ! printf '%s' "$filtered" | grep -q "$updated_number"; then
  echo "FAIL: filtro por número/status/data não retornou o romaneio: $filtered"
  exit 1
fi

miss="$(curl -sS -b "$cookie_file" "$base_url/api/romaneios.php?number=${updated_number}&status=FINALIZADO")"
if printf '%s' "$miss" | grep -q "$updated_number"; then
  echo "FAIL: filtro por status ignorou o critério: $miss"
  exit 1
fi

outside="$(curl -sS -b "$cookie_file" "$base_url/api/romaneios.php?number=${updated_number}&date_from=2099-01-01")"
if printf '%s' "$outside" | grep -q "$updated_number"; then
  echo "FAIL: filtro por data ignorou o critério: $outside"
  exit 1
fi

if [[ "$(status -b "$cookie_file" "$base_url/api/romaneios.php?status=INVALIDO")" != "422" ]]; then
  echo "FAIL: status inválido não foi rejeitado"
  exit 1
fi

echo "OK: sessão, produtos, criação/edição/listagem de romaneio, duplicidade e filtros validados."

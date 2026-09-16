#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
admin_cookie="/tmp/dx-etapa22-admin.txt"
empresa_cookie="/tmp/dx-etapa22-empresa.txt"

fail() { echo "FAIL: $1"; exit 1; }

# 1. Login do administrador Dallogix.
login_admin="$(curl -sS -c "$admin_cookie" -H 'Content-Type: application/json' -d '{"email":"master@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login_admin" | grep -q '"role":"ADMIN_DALLOGIX"'; then
  fail "login do admin Dallogix falhou: $login_admin"
fi

# 2. Lista geral de empresas com indicadores.
list="$(curl -sS -b "$admin_cookie" "$base_url/api/empresas.php")"
if ! printf '%s' "$list" | grep -q '"total_machines"'; then
  fail "lista de empresas sem indicadores de máquinas: $list"
fi
if ! printf '%s' "$list" | grep -q 'Empresa Demonstração'; then
  fail "Empresa Demonstração ausente na lista: $list"
fi

company_id="$(printf '%s' "$list" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)"
if [ -z "$company_id" ]; then
  fail "não foi possível identificar o id da empresa na lista"
fi

# 3. Detalhe gerencial da empresa com máquinas e ocorrências.
detail="$(curl -sS -b "$admin_cookie" "$base_url/api/empresas.php?company_id=$company_id")"
if ! printf '%s' "$detail" | grep -q '"maquinas"'; then
  fail "detalhe da empresa sem bloco de máquinas: $detail"
fi
if ! printf '%s' "$detail" | grep -q 'EST-001'; then
  fail "máquina EST-001 ausente no detalhe da empresa: $detail"
fi

missing="$(curl -sS -o /dev/null -w '%{http_code}' -b "$admin_cookie" "$base_url/api/empresas.php?company_id=999999")"
if [ "$missing" != "404" ]; then
  fail "empresa inexistente deveria retornar 404: $missing"
fi

# 4. Isolamento por perfil: ADMIN_EMPRESA só acessa a própria empresa.
login_empresa="$(curl -sS -c "$empresa_cookie" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
if ! printf '%s' "$login_empresa" | grep -q '"authenticated":true'; then
  fail "login do admin da empresa falhou: $login_empresa"
fi

denied="$(curl -sS -o /dev/null -w '%{http_code}' -b "$empresa_cookie" "$base_url/api/empresas.php?company_id=$((company_id + 1000))")"
if [ "$denied" != "403" ]; then
  fail "ADMIN_EMPRESA acessou empresa de terceiros: $denied"
fi

own="$(curl -sS -b "$empresa_cookie" "$base_url/api/empresas.php?company_id=$company_id")"
if ! printf '%s' "$own" | grep -q 'EST-001'; then
  fail "própria empresa deveria ser acessível pelo ADMIN_EMPRESA: $own"
fi

# 5. Monitoramento local expõe o bloco de máquinas para os cartões.
monitoring="$(curl -sS -b "$empresa_cookie" "$base_url/api/monitoramento.php")"
if ! printf '%s' "$monitoring" | grep -q '"maquinas"'; then
  fail "monitoramento sem bloco de máquinas: $monitoring"
fi

echo "OK: painel Dallogix de empresas, dashboard gerencial por empresa e isolamento por perfil validados."

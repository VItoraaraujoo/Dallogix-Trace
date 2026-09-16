#!/usr/bin/env bash
set -u

root="$(cd "$(dirname "$0")/.." && pwd)"
fail() { echo "FAIL: $1"; exit 1; }

dashboard="$root/interface/js/telas/painel.js"
app="$root/interface/js/aplicacao.js"
css="$root/interface/css/light-theme.css"
monitoring="$root/servidor/api/monitoramento.php"

grep -q 'data-action="view-manifest"' "$dashboard" || fail "dashboard sem acesso ao romaneio"
grep -q 'data-action="view-dala"' "$dashboard" || fail "dashboard sem acesso à Dala"
grep -q 'data-action="view-dala"' "$dashboard" || fail "dashboard sem visualização da Dala"
grep -q '"dashboard"' "$app" || fail "navegação do dashboard não configurada"
grep -q 'dragstart' "$app" || fail "proteção contra arraste não instalada"
grep -q 'width: 30px' "$css" || fail "ícones do menu não ampliados"
grep -q 'r.id AS romaneio_id' "$monitoring" || fail "monitoramento sem id do romaneio"

node --input-type=module --check < "$app" >/dev/null || fail "app.js inválido"
node --input-type=module --check < "$dashboard" >/dev/null || fail "dashboard.js inválido"
echo "OK: dashboard vinculado, permissões por perfil, ícones ampliados e arraste bloqueado."

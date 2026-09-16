#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
fail() { echo "FAIL: $1"; exit 1; }

# Cada tela possui seu próprio HTML com o identificador data-page correspondente.
check_page() {
  local file="$1" page="$2"
  local body code
  code="$(curl -sS -o /tmp/dx-etapa23.html -w '%{http_code}' "$base_url/$file")"
  [ "$code" = "200" ] || fail "$file retornou HTTP $code"
  grep -q "data-page=\"$page\"" /tmp/dx-etapa23.html || fail "$file sem data-page=\"$page\""
  if [ "$page" != "login" ]; then
    grep -q 'class="sidebar"' /tmp/dx-etapa23.html || fail "$file sem menu lateral estático"
    grep -q 'class="topbar"' /tmp/dx-etapa23.html || fail "$file sem barra superior estática"
    grep -q 'id="screen-root"' /tmp/dx-etapa23.html || fail "$file sem área de conteúdo"
    grep -q 'data-action="toggle-menu"' /tmp/dx-etapa23.html || fail "$file sem botão de recolher menu"
    grep -q 'data-action="logout"' /tmp/dx-etapa23.html || fail "$file sem botão sair"
  else
    grep -q 'id="login-form"' /tmp/dx-etapa23.html || fail "$file sem formulário de login estático"
  fi
  grep -Eq 'js/aplicacao.js\?v=[0-9]+' /tmp/dx-etapa23.html || fail "$file sem versionamento do app.js"
}

check_page index.html login
check_page dashboard.html dashboard
check_page manifests.html manifests
check_page import.html import
check_page division.html division
check_page work.html work
check_page occurrences.html occurrences
check_page summary.html summary
check_page history.html history
check_page products.html products
check_page alerts.html alerts
check_page emergency.html emergency
check_page settings.html settings
check_page dalas.html dalas
check_page companies.html companies
check_page company.html company

core="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/js/aplicacao.js")"
[ "$core" = "200" ] || fail "js/aplicacao.js não disponível (HTTP $core)"
if curl -sS "$base_url/js/aplicacao.js" | grep -q '?page='; then
  fail "js/aplicacao.js ainda contém navegação ?page="
fi

# As exports consumidas pelas telas precisam existir em view.js/html.js.
view_js="$(curl -sS "$base_url/js/funcoes/view.js")"
for fn in pageHeader statuses progress badge manifestsTable deviceBadge machineGrid companyGrid; do
  printf '%s' "$view_js" | grep -q "export function $fn" || fail "view.js sem export: $fn"
done
html_js="$(curl -sS "$base_url/js/funcoes/html.js")"
for fn in el esc button; do
  printf '%s' "$html_js" | grep -q "export const $fn" || fail "html.js sem export: $fn"
done

# HTML/JS/CSS devem ser sempre revalidados pelo navegador.
cache_header="$(curl -sSI "$base_url/manifests.html" | grep -i 'cache-control' | head -1)"
printf '%s' "$cache_header" | grep -qi 'no-cache' || fail "Cache-Control ausente no HTML: $cache_header"

echo "OK: telas HTML locais servidas com data-page correto e núcleo único em js/aplicacao.js."

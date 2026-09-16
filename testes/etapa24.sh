#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
root="$(cd "$(dirname "$0")/.." && pwd)"
jar="/tmp/dx-etapa24-cookies.txt"
rm -f "$jar"
fail() { echo "FAIL: $1"; exit 1; }

# 1. Páginas de detalhe novas servidas com data-page correto.
check_page() {
  local file="$1" page="$2"
  local code
  code="$(curl -sS -o /tmp/dx-etapa24.html -w '%{http_code}' "$base_url/$file")"
  [ "$code" = "200" ] || fail "$file retornou HTTP $code"
  grep -q "data-page=\"$page\"" /tmp/dx-etapa24.html || fail "$file sem data-page=\"$page\""
  grep -q 'class="sidebar"' /tmp/dx-etapa24.html || fail "$file sem menu lateral estático"
  grep -q 'id="screen-root"' /tmp/dx-etapa24.html || fail "$file sem área de conteúdo"
  grep -q 'data-action="logout"' /tmp/dx-etapa24.html || fail "$file sem botão sair"
  grep -Eq 'js/aplicacao.js\?v=[0-9]+' /tmp/dx-etapa24.html || fail "$file sem versionamento do app.js"
}
check_page manifest.html manifest
check_page manifest-edit.html manifest-edit
check_page dala.html dala
check_page dala-edit.html dala-edit

# 2. Credenciais nunca trafegam pela URL: formulário com POST e envio via fetch POST.
login_html="$(curl -sS "$base_url/index.html")"
printf '%s' "$login_html" | grep -q 'id="login-form"' || fail "index.html sem formulário de login"
printf '%s' "$login_html" | grep -Eq '<form id="login-form" method="post"' || fail "formulário de login sem method=post"
app_js="$(curl -sS "$base_url/js/aplicacao.js")"
printf '%s' "$app_js" | grep -q 'fetch("/api/login.php"' || fail "app.js não envia login para /api/login.php"
printf '%s' "$app_js" | grep -q "replaceState" || fail "app.js sem higiene de URL (replaceState)"
if printf '%s' "$app_js" | grep -q 'password='; then fail "app.js monta query string com password"; fi
if printf '%s' "$login_html" | grep -q 'password='; then fail "index.html contém password na URL/marcação"; fi

# 3. Autenticação e APIs da etapa respondem com JSON válido.
login_code="$(curl -sS -o /tmp/dx-etapa24-login.json -w '%{http_code}' -c "$jar" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
[ "$login_code" = "200" ] || fail "login admin retornou HTTP $login_code"
grep -q '"authenticated":true\|"authenticated": true' /tmp/dx-etapa24-login.json || fail "login não autenticou"
me_code="$(curl -sS -o /dev/null -w '%{http_code}' -b "$jar" "$base_url/api/me.php")"
[ "$me_code" = "200" ] || fail "sessão inválida após login (/api/me.php HTTP $me_code)"

for endpoint in romaneios.php monitoramento.php configuracoes.php dashboard.php produtos.php equipamentos.php; do
  body_code="$(curl -sS -o /tmp/dx-etapa24-api.json -w '%{http_code}' -b "$jar" "$base_url/api/$endpoint")"
  [ "$body_code" = "200" ] || fail "/api/$endpoint retornou HTTP $body_code"
done

# Filtros de romaneios aceitos pela API.
filtered_code="$(curl -sS -o /tmp/dx-etapa24-filter.json -w '%{http_code}' -b "$jar" "$base_url/api/romaneios.php?status=FINALIZADO&divergence=1")"
[ "$filtered_code" = "200" ] || fail "filtro de romaneios retornou HTTP $filtered_code"

# 4. Divergência sinalizada apenas para cargas finalizadas.
grep -q "FINALIZADO" "$root/servidor/api/romaneios.php" || fail "romaneios.php sem regra de divergência para FINALIZADO"
if grep -q 'has_divergence' /tmp/dx-etapa24-api.json && ! grep -q 'has_divergence' /tmp/dx-etapa24-filter.json; then
  fail "campo has_divergence ausente na listagem filtrada"
fi

# 5. CSS cobre todos os componentes usados pelas telas.
css="$(curl -sS "$base_url/css/light-theme.css")$(curl -sS "$base_url/css/styles.css")"
for selector in '.grid.five' '.with-actions' '.detail-card' '.metric-blue' '.metric-orange' '.badge.orange' '.warning' '.required' '.placeholder-panel' '.csv-legacy' '.manifest-item' '.machine-grid' '.status-dot.online' '.status-dot.offline' '.dala-status'; do
  printf '%s' "$css" | grep -q -- "$selector" || fail "CSS sem estilo para $selector"
done

echo "OK: detalhes (romaneio/dala), login por POST sem credenciais na URL e componentes CSS completos."

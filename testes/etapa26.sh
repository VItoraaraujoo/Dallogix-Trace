#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
jar="/tmp/dx-etapa26-cookies.txt"
rm -f "$jar"
fail() { echo "FAIL: $1"; exit 1; }

login_code="$(curl -sS -o /tmp/dx-etapa26-login.json -w '%{http_code}' -c "$jar" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
[ "$login_code" = "200" ] || fail "login retornou HTTP $login_code"

status_code="$(curl -sS -o /tmp/dx-etapa26-sync.json -w '%{http_code}' -b "$jar" "$base_url/api/sync_status.php")"
[ "$status_code" = "200" ] || fail "sync_status retornou HTTP $status_code"
grep -q '"summary"' /tmp/dx-etapa26-sync.json || fail "status sem resumo"
grep -q '"remote_configured"' /tmp/dx-etapa26-sync.json || fail "status sem configuração remota"

unauth="$(curl -sS -o /dev/null -w '%{http_code}' "$base_url/api/sync_status.php")"
[ "$unauth" = "401" ] || fail "endpoint de sync sem autenticação retornou HTTP $unauth"

alerts_html="$(curl -sS "$base_url/alerts.html")"
printf '%s' "$alerts_html" | grep -q 'data-page="alerts"' || fail "alerts.html sem data-page"
app_js="$(curl -sS "$base_url/js/aplicacao.js")"
printf '%s' "$app_js" | grep -q 'loadSyncStatus' || fail "app.js sem carregamento do status de sync"
printf '%s' "$app_js" | grep -q 'retry-sync' || fail "app.js sem retry de sync"

echo "OK: painel de sincronização, endpoint autenticado e retry manual validados."

#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
jar="/tmp/dx-etapa25-cookies.txt"
rm -f "$jar"
fail() { echo "FAIL: $1"; exit 1; }

login_code="$(curl -sS -o /tmp/dx-etapa25-login.json -w '%{http_code}' -c "$jar" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
[ "$login_code" = "200" ] || fail "login retornou HTTP $login_code"
grep -q '"authenticated":true\|"authenticated": true' /tmp/dx-etapa25-login.json || fail "login não autenticou"

report_code="$(curl -sS -o /tmp/dx-etapa25-report.json -w '%{http_code}' -b "$jar" "$base_url/api/relatorio_operacional.php")"
[ "$report_code" = "200" ] || fail "relatório retornou HTTP $report_code"
grep -q '"summary"' /tmp/dx-etapa25-report.json || fail "relatório sem resumo"
grep -q '"rows"' /tmp/dx-etapa25-report.json || fail "relatório sem linhas"

invalid_code="$(curl -sS -o /tmp/dx-etapa25-invalid.json -w '%{http_code}' -b "$jar" "$base_url/api/relatorio_operacional.php?date_from=2026-02-31")"
[ "$invalid_code" = "422" ] || fail "data inválida não retornou HTTP 422"

history_html="$(curl -sS "$base_url/history.html")"
printf '%s' "$history_html" | grep -q 'data-page="history"' || fail "history.html sem data-page"
app_js="$(curl -sS "$base_url/js/aplicacao.js")"
printf '%s' "$app_js" | grep -q 'loadReport' || fail "app.js sem carregamento do relatório"
printf '%s' "$app_js" | grep -q 'export-report' || fail "app.js sem exportação do relatório"

echo "OK: relatório operacional autenticado, filtros e exportação do histórico validados."

#!/usr/bin/env bash
set -u

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
fail() { echo "FAIL: $1"; exit 1; }

emergency_html="$(curl -sS "$base_url/emergency.html")"
printf '%s' "$emergency_html" | grep -q 'data-page="emergency"' || fail "emergency.html sem data-page"
app_js="$(curl -sS "$base_url/js/aplicacao.js")"
printf '%s' "$app_js" | grep -q 'emergency: \[store.loadActiveLoading()' || fail "emergency sem carregamento ativo"
printf '%s' "$app_js" | grep -q 'Emergência liberada' || fail "fluxo de desbloqueio sem confirmação"
monitoring_js="$(curl -sS "$base_url/js/telas/monitoramento.js")"
printf '%s' "$monitoring_js" | grep -q 'Desbloquear máquina' || fail "tela de emergência sem botão de desbloqueio"
store_js="$(curl -sS "$base_url/js/classes/ArmazenamentoTrace.js")"
printf '%s' "$store_js" | grep -q 'await this.loadActiveLoading();' || fail "estado não é recarregado após desbloqueio"

echo "OK: tela de emergência agora exibe a liberação e atualiza o estado do carregamento."

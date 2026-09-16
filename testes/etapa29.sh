#!/usr/bin/env bash
set -u

root="$(cd "$(dirname "$0")/.." && pwd)"
fail() { echo "FAIL: $1"; exit 1; }

grep -q 'configuracoes_empresa' "$root/servidor/src/Aplicacao/ServicoSincronizacao.php" || fail "retry não usa URL salva nas configurações"
grep -q 'WEB_BIND_ADDRESS=127.0.0.1' "$root/.env.example" || fail "interface local ainda exposta por padrão"
! test -e "$root/interface/tablet.html" || fail "tela tablet removida ainda existe"
! grep -q 'tabletTimer\|export function tablet' "$root/interface/js/aplicacao.js" "$root/interface/js/telas/monitoramento.js" || fail "referência à tela tablet ainda existe"
grep -q 'animation: none' "$root/interface/css/light-theme.css" || fail "animações não removidas"
grep -q 'gap: 24px' "$root/interface/css/light-theme.css" || fail "espaçamento do dashboard não corrigido"
for file in "$root"/interface/*.html; do grep -q 'js/aplicacao.js?v=' "$file" || fail "cache do app não versionado em $file"; done

php -l "$root/servidor/api/sync_queue.php" >/dev/null || fail "sync_queue.php inválido"
php -l "$root/servidor/api/sync_status.php" >/dev/null || fail "sync_status.php inválido"
node --input-type=module --check < "$root/interface/js/aplicacao.js" >/dev/null || fail "app.js inválido"
node --input-type=module --check < "$root/interface/js/telas/monitoramento.js" >/dev/null || fail "monitoring.js inválido"
echo "OK: retry, acesso local, desbloqueio, configurações, animações, cache e espaçamento validados."

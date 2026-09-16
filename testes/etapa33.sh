#!/usr/bin/env bash
set -u

root="$(cd "$(dirname "$0")/.." && pwd)"
fail() { echo "FAIL: $1"; exit 1; }

roles="$(docker compose -f "$root/docker-compose.yml" exec -T mysql mysql -N -utrace -pchange-me-local trace_local -e "SHOW COLUMNS FROM usuarios LIKE 'role'" 2>/dev/null)"
printf '%s' "$roles" | grep -q "'ADMIN_DALLOGIX','ADMIN_EMPRESA','SUPERVISOR','USUARIO'" || fail "enum de perfis não foi consolidado"
printf '%s' "$roles" | grep -q 'OPERADOR\|MANUTENCAO' && fail "perfil antigo ainda está presente no banco"
grep -q 'USUARIO:' "$root/interface/js/aplicacao.js" || fail "navegação do usuário não configurada"
grep -q 'require_role(\["ADMIN_EMPRESA", "SUPERVISOR"\])' "$root/servidor/api/desbloquear_maquina.php" || fail "desbloqueio não está restrito a administrador/supervisor"

echo "OK: quatro perfis oficiais e permissões críticas consolidados."

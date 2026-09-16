#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root_dir"

# Falha somente para credenciais com formato inequívoco. Valores de exemplo
# documentados (change-me-*) não são tratados como segredos reais.
if git grep -nI -E 'BEGIN (RSA|OPENSSH|EC|DSA) PRIVATE KEY|AKIA[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9_]{20,}' -- . ':!documentacao' ':!README.md'; then
  echo "Falha: possível credencial privada encontrada no código rastreado." >&2
  exit 1
fi

if git grep -nI -E "(^|[^A-Za-z])(password|passwd|secret|token)[[:space:]]*=[[:space:]]*[\"'][^\$\"']{12,}[\"']" -- '*.php' '*.js' '*.sh' '*.yml' '*.yaml' '*.json' ':!testes'; then
  echo "Falha: possível segredo fixo encontrado no código rastreado." >&2
  exit 1
fi

echo "OK: nenhuma credencial privada ou segredo fixo de alto risco encontrado."

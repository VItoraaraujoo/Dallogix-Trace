#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
env_file="${1:-$root_dir/.env}"
if [[ -f "$env_file" ]]; then
  set -a
  # shellcheck disable=SC1091
  . "$env_file"
  set +a
fi

failures=0
required=(MYSQL_PASSWORD MYSQL_ROOT_PASSWORD PLC_INTERNAL_TOKEN CAMERA_INTERNAL_TOKEN APP_URL)
for key in "${required[@]}"; do
  value="${!key:-}"
  if [[ -z "$value" || "$value" == change-me-* || "$value" == "password" || "$value" == "password1234" ]]; then
    echo "ERRO: $key precisa ser definido com valor próprio e não padrão." >&2
    failures=$((failures + 1))
  fi
done

if [[ ! -f "$env_file" ]]; then
  echo "ERRO: arquivo .env ausente para produção." >&2
  failures=$((failures + 1))
fi

if [[ "${APP_ENV:-}" != "production" ]]; then
  echo "ERRO: APP_ENV deve ser production para esta verificação." >&2
  failures=$((failures + 1))
fi
if [[ "${SESSION_SECURE:-}" != "true" ]]; then
  echo "ERRO: SESSION_SECURE deve ser true em produção." >&2
  failures=$((failures + 1))
fi
if [[ "${APP_URL:-}" != https://* ]]; then
  echo "ERRO: APP_URL deve usar HTTPS em produção." >&2
  failures=$((failures + 1))
fi
if [[ "${WEB_BIND_ADDRESS:-}" == "0.0.0.0" || "${WEB_BIND_ADDRESS:-}" == "::" ]]; then
  echo "ERRO: WEB_BIND_ADDRESS não pode expor a interface pública em produção." >&2
  failures=$((failures + 1))
fi
if [[ "${BIND_ADDRESS:-}" == "0.0.0.0" || "${BIND_ADDRESS:-}" == "::" ]]; then
  echo "ERRO: BIND_ADDRESS deve ficar restrito ao host local ou rede interna." >&2
  failures=$((failures + 1))
fi
if [[ -n "${SYNC_REMOTE_URL:-}" && -z "${SYNC_REMOTE_TOKEN:-}" ]]; then
  echo "ERRO: SYNC_REMOTE_TOKEN é obrigatório quando SYNC_REMOTE_URL está configurada." >&2
  failures=$((failures + 1))
fi
if [[ -n "${APP_URL:-}" && "${APP_URL:-}" == http://* ]]; then
  echo "ERRO: APP_URL não pode usar HTTP em produção." >&2
  failures=$((failures + 1))
fi
if [[ -n "${MYSQL_ROOT_PASSWORD:-}" && "${MYSQL_ROOT_PASSWORD:-}" == "change-me-root" ]]; then
  echo "ERRO: MYSQL_ROOT_PASSWORD não pode permanecer em valor padrão." >&2
  failures=$((failures + 1))
fi

if [[ "$failures" -gt 0 ]]; then
  exit 1
fi
echo "OK: configuração mínima de produção validada sem exibir segredos."

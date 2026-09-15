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
required=(MYSQL_PASSWORD MYSQL_ROOT_PASSWORD TRACE_DEVICE_TOKEN CAMERA_DEVICE_TOKEN APP_URL)
for key in "${required[@]}"; do
  value="${!key:-}"
  if [[ -z "$value" || "$value" == change-me-* || "$value" == "password" || "$value" == "password1234" || "$value" == "trace-device-local-token-2026-v1" || "$value" == "trace-camera-local-token-2026-v1" ]]; then
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
if [[ -n "${SYNC_REMOTE_URL:-}${SYNC_REMOTE_BATCH_URL:-}" && -z "${SYNC_REMOTE_TOKEN:-}" ]]; then
  echo "ERRO: SYNC_REMOTE_TOKEN é obrigatório quando a sincronização remota está configurada." >&2
  failures=$((failures + 1))
fi
for key in SYNC_REMOTE_URL SYNC_REMOTE_BATCH_URL; do
  value="${!key:-}"
  if [[ -n "$value" && "$value" != https://* ]]; then
    echo "ERRO: $key deve usar HTTPS em produção." >&2
    failures=$((failures + 1))
  fi
done
if [[ -n "${APP_URL:-}" && "${APP_URL:-}" == http://* ]]; then
  echo "ERRO: APP_URL não pode usar HTTP em produção." >&2
  failures=$((failures + 1))
fi
if [[ "${TRACE_TESTING_DISABLE_CSRF:-0}" == "1" || "${TRACE_TESTING_DISABLE_LOGIN_RATE_LIMIT:-0}" == "1" ]]; then
  echo "ERRO: proteções de teste não podem ser ativadas em produção." >&2
  failures=$((failures + 1))
fi

if [[ "${TRACE_ENV_CHECK_SKIP_DATABASE:-0}" != "1" ]] && command -v docker >/dev/null 2>&1; then
  known_hashes=(
    '$2y$10$ebOT1MqNyajFths8pCaJu.qE7MOSNMWkXYLan9LVzGSXFHIdgxK8C'
    '$2y$12$rNW5syuIUQXWmnkzFzzCUOq7APYSVr.0iN9JIkPvCvoT0mfa/Bwm.'
  )
  for hash in "${known_hashes[@]}"; do
    count="$(docker compose exec -T mysql sh -lc 'mysql --batch --skip-column-names -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "$1"' trace-env-check "SELECT COUNT(*) FROM usuarios WHERE password_hash = '$hash'" 2>/dev/null | tr -d '[:space:]' || true)"
    if [[ "$count" =~ ^[1-9][0-9]*$ ]]; then
      echo "ERRO: existe usuário com hash de senha de demonstração conhecido." >&2
      failures=$((failures + 1))
      break
    fi
  done
fi
if [[ -n "${MYSQL_ROOT_PASSWORD:-}" && "${MYSQL_ROOT_PASSWORD:-}" == "change-me-root" ]]; then
  echo "ERRO: MYSQL_ROOT_PASSWORD não pode permanecer em valor padrão." >&2
  failures=$((failures + 1))
fi

if [[ "$failures" -gt 0 ]]; then
  exit 1
fi
echo "OK: configuração mínima de produção validada sem exibir segredos."

#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 || ! -f "$1" ]]; then
  echo "Uso: $0 /caminho/backup.sql" >&2
  exit 2
fi
if [[ "${APP_ENV:-local}" == "production" && "${TRACE_ALLOW_RESTORE:-0}" != "1" ]]; then
  echo "Restauração bloqueada em produção. Defina TRACE_ALLOW_RESTORE=1 conscientemente." >&2
  exit 3
fi

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
bash "$root_dir/scripts/verify_backup.sh" "$1"

docker compose --project-directory "$root_dir" exec -T mysql sh -lc \
  'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < "$1"
printf 'Backup restaurado: %s\n' "$1"

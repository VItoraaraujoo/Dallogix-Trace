#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
output_dir="${1:-$root_dir/armazenamento/backups}"
mkdir -p "$output_dir"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
staging_dir="$(mktemp -d "$output_dir/.backup-${timestamp}.XXXXXX")"
trap 'rm -rf "$staging_dir"' EXIT
filename="trace_local_${timestamp}_${staging_dir##*.}.sql"
staging_file="$staging_dir/$filename"
output_file="$output_dir/$filename"

# As credenciais pertencem ao contêiner MySQL. Executar a expansão lá evita
# depender de variáveis existentes no host do servidor e não expõe a senha.
docker compose --project-directory "$root_dir" exec -T mysql sh -lc 'mysqldump --single-transaction --routines --events --triggers --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > "$staging_file"

test -s "$staging_file"
if command -v sha256sum >/dev/null 2>&1; then
  (cd "$staging_dir" && sha256sum "$filename") > "$staging_file.sha256"
else
  (cd "$staging_dir" && shasum -a 256 "$filename") > "$staging_file.sha256"
fi
bash "$root_dir/scripts/verify_backup.sh" "$staging_file"
mv "$staging_file.sha256" "$output_file.sha256"
mv "$staging_file" "$output_file"

retention_days="${TRACE_BACKUP_RETENTION_DAYS:-30}"
if [[ "$retention_days" =~ ^[0-9]+$ ]]; then
  find "$output_dir" -maxdepth 1 -type f -name 'trace_local_*.sql' -mtime "+$retention_days" -delete
  find "$output_dir" -maxdepth 1 -type f -name 'trace_local_*.sql.sha256' -mtime "+$retention_days" -delete
fi
printf 'Backup criado: %s\n' "$output_file"

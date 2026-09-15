#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
migrations_dir="$root_dir/banco-de-dados/migrations"

mysql_query() {
  local sql="$1"
  docker compose --project-directory "$root_dir" exec -T mysql sh -lc \
    'mysql --batch --skip-column-names -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "$1"' \
    trace-migrate "$sql"
}

mysql_file() {
  docker compose --project-directory "$root_dir" exec -T mysql sh -lc \
    'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < "$1"
}

mysql_query "CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(120) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)"

applied_count="$(mysql_query 'SELECT COUNT(*) FROM schema_migrations' | tr -d '[:space:]')"
has_trace_schema="$(mysql_query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'empresas'" | tr -d '[:space:]')"
if [[ "$applied_count" == "0" && "$has_trace_schema" == "1" ]]; then
  # Instalações anteriores não tinham controle de versão. O schema atual já
  # contém as 23 migrations históricas; registra-as sem reaplicar ALTERs.
  for migration in "$migrations_dir"/[0-9][0-9][0-9]_*.sql; do
    version="$(basename "$migration" .sql)"
    [[ "$version" < "024_" ]] || continue
    mysql_query "INSERT IGNORE INTO schema_migrations (version) VALUES ('$version')"
  done
fi

for migration in "$migrations_dir"/[0-9][0-9][0-9]_*.sql; do
  version="$(basename "$migration" .sql)"
  if [[ "$(mysql_query "SELECT COUNT(*) FROM schema_migrations WHERE version = '$version'" | tr -d '[:space:]')" == "1" ]]; then
    continue
  fi
  echo "Aplicando migration $version"
  mysql_file "$migration"
  mysql_query "INSERT INTO schema_migrations (version) VALUES ('$version')"
done

echo "OK: migrations controladas e atualizadas."

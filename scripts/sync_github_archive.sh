#!/usr/bin/env bash
set -euo pipefail

# Atualiza o servidor a partir da master pública sem chave de deploy. O processo
# altera somente o código versionado; .env, armazenamento e volumes do Docker
# permanecem no servidor.
root_dir="${TRACE_DEPLOY_PATH:-/opt/dallogix-trace}"
repository="${TRACE_GITHUB_REPOSITORY:-VItoraaraujoo/Dallogix-Trace-System}"
branch="${TRACE_GITHUB_BRANCH:-master}"
state_dir="${TRACE_DEPLOY_STATE_DIR:-/var/lib/dallogix-trace}"
marker="$state_dir/deployed-commit"

commit="$(curl --fail --silent --show-error --location \
  "https://api.github.com/repos/${repository}/commits/${branch}" \
  | python3 -c 'import json, sys; print(json.load(sys.stdin)["sha"])')"

if [[ -f "$marker" && "$(<"$marker")" == "$commit" ]]; then
  echo "Servidor já está na master ($commit)."
  exit 0
fi

release_dir="$(mktemp -d)"
trap 'rm -rf "$release_dir"' EXIT
curl --fail --silent --show-error --location \
  "https://github.com/${repository}/archive/refs/heads/${branch}.tar.gz" \
  | tar -xzf - -C "$release_dir" --strip-components=1

test -f "$release_dir/docker-compose.yml"
test -d "$release_dir/interface"

mkdir -p "$root_dir/armazenamento/backups" "$state_dir"
tar -C "$release_dir" -cf - . | tar -xf - -C "$root_dir"

# Migrations aplicadas recebem um marcador persistente em armazenamento. Na
# primeira instalação, os marcadores existentes são criados pelo instalador.
pending=()
for migration in "$root_dir"/banco-de-dados/migrations/*.sql; do
  marker_file="$root_dir/armazenamento/.migration-$(basename "$migration").done"
  [[ -f "$marker_file" ]] || pending+=("$migration")
done
if ((${#pending[@]})); then
  bash "$root_dir/scripts/backup_db.sh"
  for migration in "${pending[@]}"; do
    docker compose -f "$root_dir/docker-compose.yml" --project-directory "$root_dir" \
      exec -T mysql sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
      < "$migration"
    touch "$root_dir/armazenamento/.migration-$(basename "$migration").done"
  done
fi

docker compose -f "$root_dir/docker-compose.yml" --project-directory "$root_dir" \
  up -d --build --remove-orphans

for _ in $(seq 1 "${HEALTHCHECK_ATTEMPTS:-90}"); do
  if curl --fail --silent --max-time 3 "http://127.0.0.1:${WEB_PORT:-80}/api/health.php" >/dev/null; then
    printf '%s\n' "$commit" > "$marker"
    echo "Servidor atualizado para $commit."
    exit 0
  fi
  sleep 2
done

echo "Atualização aplicada, mas o healthcheck falhou." >&2
exit 6

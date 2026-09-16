#!/usr/bin/env bash
set -euo pipefail

# Sincroniza uma instalação do servidor somente por fast-forward.
# Não sobrescreve alterações locais, .env, armazenamento ou dados do Compose.
root_dir="$(cd "$(dirname "$0")/.." && pwd)"
remote_name="${TRACE_GITHUB_REMOTE:-empresa}"
branch="${TRACE_GITHUB_BRANCH:-master}"
dry_run="${TRACE_GITHUB_DRY_RUN:-0}"

cd "$root_dir"
[[ -d .git ]] || { echo "Repositório Git não encontrado: $root_dir" >&2; exit 2; }
git remote get-url "$remote_name" >/dev/null || { echo "Remote GitHub não configurado: $remote_name" >&2; exit 3; }
[[ -z "$(git status --porcelain --untracked-files=all -- . ':!.env' ':!armazenamento/' ':!tmp/' ':!**/__pycache__/')" ]] || {
  echo "Sincronização interrompida: existem alterações locais fora de .env/armazenamento/tmp/cache." >&2
  exit 4
}

git fetch --prune "$remote_name" "$branch"
local_commit="$(git rev-parse HEAD)"
remote_commit="$(git rev-parse "$remote_name/$branch")"
if [[ "$local_commit" == "$remote_commit" ]]; then
  echo "Servidor já está sincronizado com $remote_name/$branch ($local_commit)."
  exit 0
fi
git merge-base --is-ancestor "$local_commit" "$remote_commit" || {
  echo "Sincronização interrompida: atualização não é fast-forward." >&2
  exit 5
}
if [[ "$dry_run" == "1" ]]; then
  echo "Simulação: servidor avançaria de $local_commit para $remote_commit."
  exit 0
fi

git merge --ff-only "$remote_name/$branch"
# Migrações versionadas: mantém os dados, cria backup antes de qualquer DDL e
# usa schema_migrations como fonte única de verdade.
mkdir -p armazenamento/backups
bash scripts/backup_db.sh
bash scripts/migrate.sh
# Reconstrói e recria somente os serviços afetados pela alteração. A VM, o
# banco e os serviços que não mudaram permanecem em execução.
bash "$root_dir/scripts/docker_compose.sh" up -d --build --remove-orphans
healthy=0
web_port="${WEB_PORT:-}"
if [[ -z "$web_port" && -f "$root_dir/.env" ]]; then
  web_port="$(sed -n 's/^WEB_PORT=//p' "$root_dir/.env" | tail -1 | tr -d '"')"
fi
web_port="${web_port:-8080}"
for _ in $(seq 1 "${HEALTHCHECK_ATTEMPTS:-90}"); do
  if curl --fail --silent --max-time 3 "http://127.0.0.1:${web_port}/api/prontidao.php" >/dev/null; then healthy=1; break; fi
  sleep 2
done
[[ "$healthy" == "1" ]] || { echo "Atualização aplicada, mas o healthcheck falhou." >&2; exit 6; }
echo "Servidor sincronizado com $remote_name/$branch em $remote_commit."

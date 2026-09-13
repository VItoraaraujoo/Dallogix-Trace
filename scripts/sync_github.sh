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
# impede que o código novo suba usando uma estrutura antiga.
mkdir -p armazenamento/backups
bash scripts/backup_db.sh
for migration in banco-de-dados/migrations/020_nomenclatura_portugues.sql banco-de-dados/migrations/021_acoes_dala_e_logs_erros.sql banco-de-dados/migrations/022_produtos_empresa_demonstracao.sql banco-de-dados/migrations/023_codigo_barras_por_empresa.sql; do
  [[ -f "$migration" ]] || continue
  marker="armazenamento/.migration-$(basename "$migration").done"
  [[ -f "$marker" ]] && continue
  # MYSQL_USER e MYSQL_PASSWORD vivem somente no contêiner. Usar a conta da
  # aplicação (com privilégios de migração) torna o deploy independente da
  # senha root e evita vazar credenciais para o host ou para os logs.
  docker compose exec -T mysql sh -lc 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < "$migration"
  touch "$marker"
done
# Reconstrói e recria somente os serviços afetados pela alteração. A VM, o
# banco e os serviços que não mudaram permanecem em execução.
docker compose up -d --build --remove-orphans
healthy=0
for _ in $(seq 1 "${HEALTHCHECK_ATTEMPTS:-90}"); do
  if curl --fail --silent --max-time 3 "http://127.0.0.1:${WEB_PORT:-80}/api/health.php" >/dev/null; then healthy=1; break; fi
  sleep 2
done
[[ "$healthy" == "1" ]] || { echo "Atualização aplicada, mas o healthcheck falhou." >&2; exit 6; }
echo "Servidor sincronizado com $remote_name/$branch em $remote_commit."

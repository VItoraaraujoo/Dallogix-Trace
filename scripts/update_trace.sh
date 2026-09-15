#!/usr/bin/env bash
set -euo pipefail

# Atualiza uma instalação local somente quando não há carregamento em andamento.
# O servidor de atualização publica um manifesto assinado e um pacote imutável.
root_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root_dir"
if [[ -f "$root_dir/.env" ]]; then
  set -a
  # O .env é criado pelo administrador e já é usado pelo Compose.
  . "$root_dir/.env"
  set +a
fi

manifest_url="${UPDATE_MANIFEST_URL:-}"
public_key="${UPDATE_PUBLIC_KEY_FILE:-}"
channel="${UPDATE_CHANNEL:-stable}"
dry_run="${TRACE_UPDATE_DRY_RUN:-0}"
if [[ -z "$manifest_url" || -z "$public_key" ]]; then
  echo "Atualização remota não configurada: defina UPDATE_MANIFEST_URL e UPDATE_PUBLIC_KEY_FILE." >&2
  exit 2
fi
[[ "$manifest_url" == https://* ]] || { echo "O manifesto remoto deve usar HTTPS." >&2; exit 3; }
[[ -f "$public_key" ]] || { echo "Chave pública não encontrada: $public_key" >&2; exit 4; }

command -v curl >/dev/null || { echo "curl é necessário." >&2; exit 5; }
command -v openssl >/dev/null || { echo "openssl é necessário para verificar a assinatura." >&2; exit 6; }
command -v python3 >/dev/null || { echo "python3 é necessário para validar o manifesto." >&2; exit 7; }
command -v rsync >/dev/null || { echo "rsync é necessário para uma instalação segura." >&2; exit 8; }

state_dir="$root_dir/armazenamento/updates"
mkdir -p "$state_dir/releases" "$state_dir/backups"
lock_dir="$state_dir/.install.lock"
if ! mkdir "$lock_dir" 2>/dev/null; then
  echo "Já existe uma atualização em execução." >&2
  exit 9
fi
cleanup() {
  rmdir "$lock_dir" 2>/dev/null || true
  if [[ -n "${work_dir:-}" ]]; then rm -rf -- "$work_dir"; fi
}
trap cleanup EXIT
work_dir="$(mktemp -d "$state_dir/.staging.XXXXXX")"
manifest="$work_dir/manifest.json"
headers=()
if [[ -n "${UPDATE_MANIFEST_TOKEN:-}" ]]; then headers+=( -H "Authorization: Bearer ${UPDATE_MANIFEST_TOKEN}" ); fi
curl --fail --silent --show-error --location --max-time 20 "${headers[@]}" "$manifest_url" -o "$manifest"

mapfile -t fields < <(python3 - "$manifest" "$channel" <<'PY'
import json, pathlib, sys
data = json.loads(pathlib.Path(sys.argv[1]).read_text())
channel = sys.argv[2]
if data.get("channel", channel) != channel:
    raise SystemExit("manifesto de canal diferente do configurado")
required = ("version", "artifact_url", "sha256", "signature")
if any(not isinstance(data.get(k), str) or not data[k].strip() for k in required):
    raise SystemExit("manifesto sem campos obrigatórios")
if not data["artifact_url"].startswith("https://"):
    raise SystemExit("artefato remoto deve usar HTTPS")
if len(data["sha256"]) != 64 or any(c not in "0123456789abcdefABCDEF" for c in data["sha256"]):
    raise SystemExit("SHA-256 inválido")
print(data["version"])
print(data["artifact_url"])
print(data["sha256"].lower())
print(data["signature"])
PY
)
version="${fields[0]}"; artifact_url="${fields[1]}"; expected_sha="${fields[2]}"; signature_b64="${fields[3]}"
[[ "$version" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "Versão inválida." >&2; exit 10; }
failed_marker="$state_dir/failed-$version"
if [[ -f "$failed_marker" && "${TRACE_RETRY_FAILED_UPDATE:-0}" != "1" ]]; then
  echo "A versão $version já falhou anteriormente; revisão manual obrigatória (use TRACE_RETRY_FAILED_UPDATE=1 para repetir)." >&2
  exit 21
fi
mark_failed_update() {
  printf 'version=%s\nfailed_at=%s\n' "$version" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$failed_marker"
}
printf '%s\n%s\n%s\n' "$version" "$artifact_url" "$expected_sha" > "$work_dir/payload"
python3 - "$signature_b64" "$work_dir/signature.bin" <<'PY'
import base64, pathlib, sys
pathlib.Path(sys.argv[2]).write_bytes(base64.b64decode(sys.argv[1], validate=True))
PY
openssl dgst -sha256 -verify "$public_key" -signature "$work_dir/signature.bin" "$work_dir/payload" >/dev/null || {
  echo "Assinatura do pacote rejeitada." >&2; exit 11;
}

mysql_user="${MYSQL_USER:-trace}"
active="$(docker compose exec -T mysql mysql -N -B -u"$mysql_user" -p"${MYSQL_PASSWORD:-change-me-local}" "${MYSQL_DATABASE:-trace_local}" -e "SELECT COUNT(*) FROM carregamentos WHERE state IN ('PREPARANDO','CARREGANDO','PAUSADO','FINALIZANDO','EMERGENCIA');" 2>/dev/null | tr -d '[:space:]')" || {
  echo "Não foi possível verificar o estado do carregamento; atualização cancelada por segurança." >&2
  exit 12
}
[[ "$active" =~ ^[0-9]+$ ]] || {
  echo "Resposta inválida ao verificar o estado do carregamento; atualização cancelada por segurança." >&2
  exit 13
}
if [[ "$active" != "0" ]]; then
  echo "Atualização adiada: existe carregamento ativo ou em estado de intervenção." >&2
  exit 14
fi

if [[ -f "$state_dir/current_version" && "$(cat "$state_dir/current_version")" == "$version" ]]; then
  echo "Trace já está na versão $version."
  exit 0
fi

artifact="$work_dir/trace-$version.tar.gz"
curl --fail --silent --show-error --location --max-time 120 "${headers[@]}" "$artifact_url" -o "$artifact"
if command -v sha256sum >/dev/null; then
  actual_sha="$(sha256sum "$artifact" | awk '{print tolower($1)}')"
else
  actual_sha="$(shasum -a 256 "$artifact" | awk '{print tolower($1)}')"
fi
[[ "$actual_sha" == "$expected_sha" ]] || { echo "Integridade do pacote rejeitada." >&2; exit 15; }
tar -tzf "$artifact" >/dev/null || { echo "Pacote inválido." >&2; exit 16; }
if [[ "$dry_run" == "1" ]]; then
  echo "Manifesto, assinatura e integridade válidos para $version (simulação; nada foi alterado)."
  exit 0
fi

backup="$state_dir/backups/pre-$version-$(date -u +%Y%m%dT%H%M%SZ).tar.gz"
db_backup_output="$(bash "$root_dir/scripts/backup_db.sh" "$state_dir/backups")"
db_backup="$(printf '%s\n' "$db_backup_output" | sed -n 's/^Backup criado: //p' | tail -n 1)"
[[ -s "$db_backup" && -s "$db_backup.sha256" ]] || {
  echo "O backup do banco foi criado sem caminho verificável; atualização cancelada." >&2
  exit 17
}
tar --exclude='./.env' --exclude='./armazenamento' --exclude='./.git' -czf "$backup" -C "$root_dir" .
release_dir="$state_dir/releases/$version"
rm -rf "$release_dir"
mkdir -p "$release_dir"
tar -xzf "$artifact" -C "$release_dir" --no-same-owner
source_dir="$release_dir"
if [[ -d "$release_dir/trace" && -f "$release_dir/trace/docker-compose.yml" ]]; then source_dir="$release_dir/trace"; fi
[[ -f "$source_dir/docker-compose.yml" ]] || { echo "Pacote sem docker-compose.yml." >&2; exit 17; }

rollback() {
  echo "Restaurando a versão anterior." >&2
  docker compose stop >/dev/null 2>&1 || true
  if ! tar -xzf "$backup" -C "$root_dir" --no-same-owner; then
    echo "Não foi possível restaurar os arquivos da versão anterior." >&2
    return 1
  fi
  if ! docker compose up -d mysql >/dev/null; then
    echo "Não foi possível iniciar o banco para o rollback." >&2
    return 1
  fi
  mysql_ready=0
  for _ in $(seq 1 "${MYSQL_ROLLBACK_ATTEMPTS:-30}"); do
    if docker compose exec -T mysql sh -lc 'mysqladmin ping -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null 2>&1; then
      mysql_ready=1
      break
    fi
    sleep 2
  done
  if [[ "$mysql_ready" != "1" ]]; then
    echo "O banco não ficou pronto para o rollback." >&2
    return 1
  fi
  if ! bash "$root_dir/scripts/verify_backup.sh" "$db_backup" >/dev/null; then
    echo "O backup do banco não passou na verificação durante o rollback." >&2
    return 1
  fi
  if ! docker compose exec -T mysql sh -lc 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < "$db_backup"; then
    echo "Não foi possível restaurar o banco da versão anterior." >&2
    return 1
  fi
  if ! docker compose up -d --build >/dev/null; then
    echo "Não foi possível iniciar a versão anterior." >&2
    return 1
  fi
  rollback_healthy=0
  for _ in $(seq 1 "${HEALTHCHECK_ATTEMPTS:-30}"); do
    if curl --fail --silent --max-time 3 "http://127.0.0.1:${WEB_PORT:-8080}/api/prontidao.php" >/dev/null; then rollback_healthy=1; break; fi
    sleep 2
  done
  if [[ "$rollback_healthy" != "1" ]]; then
    echo "Rollback concluído, mas o healthcheck da versão anterior também falhou." >&2
    return 1
  fi
}

docker compose stop >/dev/null
if ! rsync -a --delete --exclude='.env' --exclude='armazenamento/' --exclude='.git/' "$source_dir/" "$root_dir/"; then
  echo "Não foi possível instalar os arquivos da nova versão." >&2
  mark_failed_update
  rollback || true
  exit 18
fi
if ! docker compose up -d mysql >/dev/null; then
  echo "O banco não conseguiu iniciar para receber as migrations; iniciando rollback." >&2
  mark_failed_update
  rollback || true
  exit 19
fi
if ! bash "$root_dir/scripts/migrate.sh"; then
  echo "As migrations da nova versão falharam; iniciando rollback." >&2
  mark_failed_update
  rollback || true
  exit 19
fi
if ! docker compose up -d --build >/dev/null; then
  echo "A nova versão não conseguiu iniciar; iniciando rollback." >&2
  mark_failed_update
  rollback || true
  exit 19
fi
healthy=0
for _ in $(seq 1 "${HEALTHCHECK_ATTEMPTS:-30}"); do
  if curl --fail --silent --max-time 3 "http://127.0.0.1:${WEB_PORT:-8080}/api/prontidao.php" >/dev/null; then healthy=1; break; fi
  sleep 2
done
if [[ "$healthy" != "1" ]]; then
  echo "A versão $version não passou no healthcheck; iniciando rollback." >&2
  rollback || true
  mark_failed_update
  exit 20
fi
rm -f "$failed_marker"
printf '%s\n' "$version" > "$state_dir/current_version"
echo "Trace atualizado com sucesso para $version. Backup: $backup"

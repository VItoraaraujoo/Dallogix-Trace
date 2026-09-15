#!/usr/bin/env bash
set -euo pipefail

# Compatibilidade para instalações antigas. Este nome não baixa mais a branch
# pública nem executa migrations por marcadores de arquivo. Atualizações remotas
# devem passar pelo manifesto assinado e pelo rollback de update_trace.sh.
root_dir="${TRACE_DEPLOY_PATH:-/opt/dallogix-trace}"
echo "sync_github_archive.sh foi aposentado; usando scripts/update_trace.sh." >&2
exec bash "$root_dir/scripts/update_trace.sh"

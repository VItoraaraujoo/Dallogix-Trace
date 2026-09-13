#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root_dir"

while IFS= read -r php_file; do
  php -l "$php_file" >/dev/null
done < <(find servidor -type f -name '*.php' | sort)

while IFS= read -r js_file; do
  # O front-end usa módulos ES. Validar pela entrada padrão evita que versões
  # antigas do Node tratem arquivos .js como CommonJS apenas por não haver package.json.
  node --input-type=module --check < "$js_file" >/dev/null
done < <(find interface/js -type f -name '*.js' | sort)

while IFS= read -r shell_file; do
  bash -n "$shell_file"
done < <(find scripts testes -type f -name '*.sh' | sort)

while IFS= read -r json_file; do
  node -e 'JSON.parse(require("fs").readFileSync(process.argv[1], "utf8"))' "$json_file"
done < <(find integracoes -type f -name '*.json' | sort)

if grep -Rqs 'TraceRouter' interface/js; then
  echo 'FAIL: referência residual ao TraceRouter removido.' >&2
  exit 1
fi

if grep -Eq '\$user\[' servidor/api/configuracoes.php; then
  echo 'FAIL: configuracoes.php usa $user sem inicialização; use $usuarioAtor.' >&2
  exit 1
fi

python3 -m py_compile scripts/test_modbus_virtual.py integracoes/modbus-virtual/server.py
bash -n scripts/check_production_env.sh
bash -n scripts/check_physical_deployment.sh scripts/launch_kiosk.sh

echo 'OK: sintaxe PHP/JavaScript e referências órfãs verificadas.'

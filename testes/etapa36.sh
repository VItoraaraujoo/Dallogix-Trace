#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root_dir"

fail() { echo "FAIL: $1"; exit 1; }

grep -q 'LIMITE_SEM_SINAL_SEGUNDOS = 3' servidor/src/Aplicacao/ServicoDisponibilidadeClp.php || fail "limite de 3 segundos ausente"
grep -q 'validarComando' servidor/src/Aplicacao/ServicoEstadoCarregamento.php || fail "estado de carregamento sem bloqueio de CLP"
grep -q 'validarComando' servidor/src/Aplicacao/ServicoComandoClp.php || fail "reversão sem bloqueio de CLP"
grep -q 'validarComando' servidor/api/desbloquear_maquina.php || fail "desbloqueio sem validação de CLP"
grep -q 'a cada \*\*1 segundo\*\*' integracoes/node-red/README.md || fail "heartbeat do gateway não atualizado"
grep -q 'a cada 2 segundos' integracoes/node-red/README.md || fail "tentativa de reconexão não documentada"
grep -q 'clpDisponivel' interface/js/telas/operacoes.js || fail "tela de trabalho não bloqueia os comandos"

echo "OK: indisponibilidade do CLP bloqueia comandos na API e é apresentada ao operador."

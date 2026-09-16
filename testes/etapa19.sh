#!/usr/bin/env bash
set -u

valid="$(bash scripts/simulate_clp.sh 7898250782592)"
if ! printf '%s' "$valid" | grep -q '"result":"VALIDO"'; then
  echo "FAIL: simulador não produziu leitura válida"
  exit 1
fi

failed="$(bash scripts/simulate_clp.sh SEM_LEITURA)"
if ! printf '%s' "$failed" | grep -q '"result":"SEM_LEITURA"'; then
  echo "FAIL: simulador não produziu falha de leitura"
  exit 1
fi

echo "OK: simulador validou leitura normal e incidente sem leitura."

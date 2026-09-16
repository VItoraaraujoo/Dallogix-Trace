#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR" || exit 1

failures=0

check_command() {
  if command -v "$1" >/dev/null 2>&1; then
    printf 'OK   comando disponível: %s\n' "$1"
  else
    printf 'ERRO comando ausente: %s\n' "$1"
    failures=$((failures + 1))
  fi
}

check_php_file() {
  if php -l "$1" >/dev/null 2>&1; then
    printf 'OK   sintaxe PHP: %s\n' "$1"
  else
    printf 'ERRO sintaxe PHP: %s\n' "$1"
    failures=$((failures + 1))
  fi
}

check_command php
check_command docker
check_command git
if docker compose version >/dev/null 2>&1; then
  printf 'OK   Docker Compose disponível\n'
else
  printf 'ERRO Docker Compose ausente\n'
  failures=$((failures + 1))
fi
check_php_file servidor/configuracao/bootstrap.php
check_php_file servidor/api/index.php
check_php_file servidor/api/health.php

if [ "$failures" -gt 0 ]; then
  printf '\nEtapa 1 não validada: %s problema(s).\n' "$failures"
  exit 1
fi

if docker compose config >/dev/null 2>&1; then
  printf 'OK   docker-compose.yml válido\n'
else
  printf 'ERRO docker-compose.yml inválido\n'
  failures=$((failures + 1))
fi

printf '\nEtapa 1: estrutura e configuração local validadas.\n'

#!/usr/bin/env bash

ensure_loading_carregando() {
  local json loading_id prep_id
  json="$(curl -sS -b "$cookie_file" "$base_url/api/carregamentos.php")"
  loading_id="$(printf '%s' "$json" | sed -n 's/.*"id":\([0-9][0-9]*\),"state":"CARREGANDO".*/\1/p' | head -n 1)"
  if [[ -z "$loading_id" ]]; then
    prep_id="$(printf '%s' "$json" | sed -n 's/.*"id":\([0-9][0-9]*\),"state":"PREPARANDO".*/\1/p' | head -n 1)"
    if [[ -n "$prep_id" ]]; then
      curl -sS -b "$cookie_file" -X PATCH -H 'Content-Type: application/json' -d "{\"carregamento_id\":$prep_id,\"state\":\"CARREGANDO\"}" "$base_url/api/estado_carregamento.php" >/dev/null
      loading_id="$prep_id"
    fi
  fi
  printf '%s' "$loading_id"
}

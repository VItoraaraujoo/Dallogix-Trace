#!/usr/bin/env bash
set -eu

base_url="${TRACE_BASE_URL:-http://localhost:8080}"
cookie_file="/tmp/dallogix-trace-etapa35-cookie.txt"
output_pdf="/tmp/dallogix-trace-etapa35-auditoria.pdf"
fail() { echo "FAIL: $1"; exit 1; }

login="$(curl -sS -c "$cookie_file" -H 'Content-Type: application/json' -d '{"email":"admin@dallogix.local","password":"password"}' "$base_url/api/login.php")"
printf '%s' "$login" | grep -q '"authenticated":true' || fail "login falhou"

romaneio_id="$(docker compose exec -T mysql mysql -N -u root -pchange-me-root trace_local -e "SELECT id FROM romaneios WHERE status = 'FINALIZADO' ORDER BY id DESC LIMIT 1" | tr -d '\r')"
[ -n "$romaneio_id" ] || fail "nenhum romaneio finalizado para auditoria"

content_type="$(curl -sS -o "$output_pdf" -w '%{content_type}' -b "$cookie_file" "$base_url/api/relatorio_auditoria.php?romaneio_id=$romaneio_id")"
printf '%s' "$content_type" | grep -q 'application/pdf' || fail "relatório não retornou PDF"
head -c 4 "$output_pdf" | grep -q '%PDF' || fail "arquivo retornado não é PDF"
pdfinfo "$output_pdf" | grep -q 'Pages:' || fail "PDF de auditoria inválido"
grep -q 'Baixar relatório de auditoria' interface/js/telas/operacoes.js || fail "botão de auditoria ausente na visualização"

echo "OK: relatório de auditoria PDF disponível para romaneio finalizado."

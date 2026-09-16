# Etapa 24 — Detalhes operacionais, login seguro e componentes visuais

## STATUS

CONCLUÍDA — regressão 24/24 PASS.

## OBJETIVO

1. Eliminar credenciais da URL no login (`?email=...&password=...`).
2. Criar as páginas de detalhe faltantes (romaneio, dala, edição de dala).
3. Completar o CSS dos componentes usados pelas telas.
4. Ajustar `has_divergence` para sinalizar divergência apenas em cargas FINALIZADAS.

## ARQUIVOS CRIADOS

- `interface/manifest.html` — detalhe do romaneio (`data-page="manifest"`).
- `interface/dala.html` — visualização da dala (`data-page="dala"`).
- `interface/dala-edit.html` — edição da dala (`data-page="dala-edit"`).
- `testes/etapa24.sh` — validação automatizada da etapa.

## ARQUIVOS ALTERADOS

- `interface/index.html` — formulário de login com `method="post"` (fallback sem JS nunca envia credenciais pela URL).
- `interface/js/aplicacao.js` — envio do login via `fetch POST /api/login.php`; higiene de URL com `history.replaceState` remove qualquer query string residual; bump de versão do cache.
- `servidor/api/romaneios.php` — `has_divergence` calculado somente quando `status = 'FINALIZADO'`.
- `interface/css/light-theme.css` — novos componentes: `.grid.five`, `.with-actions`, `.detail-card(s)`, `.metric-blue/orange`, `.badge.orange`, `.warning`, `.required`, `.placeholder-panel`, `.csv-legacy`, `.manifest-item`, `.machine-grid`, `.status-dot(.online/.offline)`, `.dala-status(-line)`.

## FUNCIONALIDADES

- Login exclusivamente por POST (JSON via fetch; formulário estático como fallback seguro).
- Navegação para páginas de detalhe sem 404.
- Badges de status e métricas coloridas completas nas telas de monitoramento/dalas/configurações.

## TESTES REALIZADOS

- `bash testes/etapa24.sh` — páginas novas, POST de login, APIs autenticadas, filtros, CSS.
- Regressão `etapas 1 → 24` — 24 PASS / 0 FAIL.
- Sintaxe dos 12 módulos JS validada pelo Node (modo ESM) no container `node-red`.

## RESULTADOS

Tudo aprovado. Nenhuma classe CSS órfã restante nas telas (verificação automatizada por script).

## ERROS ENCONTRADOS

1. Credenciais aparecendo na URL do navegador após login (relato do usuário).
2. Páginas de detalhe inexistentes (`dala.html`, `dala-edit.html`, `manifest.html`) — navegação daria 404.
3. Falso diagnóstico inicial de CSS faltante: greps com `\\.` (barra invertida literal) esvaziavam a lista de classes definidas — refeito com Python.

## ERROS CORRIGIDOS

Todos os acima.

## PENDÊNCIAS

- Se o editor VS Code ainda mostrar erros de sintaxe em `app.js`/`dashboard.js`, é buffer desaturado: usar "Revert File" ou recarregar a janela (os arquivos em disco estão válidos — comprovado por parser real).
- Senha inicial do seed segue padrão de demonstração; trocar em produção (Etapa de segurança).

## PRÓXIMA FASE

Etapa 25 — relatórios/histórico consolidados e refino das telas de acompanhamento remoto.

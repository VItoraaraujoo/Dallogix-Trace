# Status dos testes — Etapa 22

- Painel Dallogix: novo menu "Empresas" exclusivo do perfil `ADMIN_DALLOGIX`, com cartões quadrados por empresa (total de máquinas, online, sem sinal, ocorrências em 24h).
- Dashboard gerencial por empresa via botão "Gerenciar": métricas, cartões de máquinas (romaneio, caminhão, estado, progresso, último sinal), periféricos reportados e ocorrências recentes.
- Dashboard local modernizado: tabela substituída pelos mesmos cartões de máquina alimentados por `monitoramento.php` (bloco `maquinas`).
- Menu lateral: botão de recolher/expandir com estado persistido em `localStorage` e navegação SPA corrigida (`data-action` nos itens + `preventDefault`).
- Endpoint `GET /api/empresas.php`: lista geral (ADMIN_DALLOGIX) e detalhe por empresa; `ADMIN_EMPRESA` acessa somente a própria empresa; empresa inexistente retorna 404.
- Seed idempotente criou `master@dallogix.local` / `password` (role `ADMIN_DALLOGIX`).
- Correção de dados: nome da empresa demonstração estava com UTF-8 duplamente codificado; reparado via conversão latin1→binário→utf8mb4. Teste da etapa 2 ajustado para usar `--default-character-set=utf8mb4`.
- Teste reproduzível: `bash testes/etapa22.sh`. Regressão completa 1→22 sem falhas.

ETAPA 22 concluída. A integração física permanece condicionada aos protocolos reais do CLP, scanner e câmera.

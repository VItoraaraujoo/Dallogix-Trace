# Status dos testes — Etapa 25

## STATUS

CONCLUÍDA — relatório operacional e histórico consolidado implementados.

## ENTREGAS

- Novo endpoint autenticado `GET /api/relatorio_operacional.php`.
- Filtros por data inicial, data final e status.
- Balanço de romaneios, quantidades planejadas/carregadas, ocorrências, falhas de leitura e divergências.
- Duração calculada quando o carregamento possui início e término.
- Tela Histórico com indicadores, tabela consolidada, auditoria recente e exportação CSV.
- Dados permanecem no banco local e seguem disponíveis offline.

## TESTE

`bash testes/etapa25.sh`

Também foram validadas a sintaxe PHP do endpoint e a sintaxe dos módulos JavaScript alterados.

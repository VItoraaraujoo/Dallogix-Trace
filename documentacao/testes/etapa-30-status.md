# Status dos testes — Etapa 30

## STATUS

CONCLUÍDA — fluxo de preparação de carregamento integrado.

## ENTREGAS

- A tela de romaneio oferece **Preparar carregamento** para administrador da empresa e supervisor.
- A tela de preparação mostra caminhões e Dalas reais do banco local.
- O servidor bloqueia dois carregamentos ativos na mesma Dala ou para o mesmo caminhão.
- A preparação muda o romaneio para `EM_ANDAMENTO` e registra auditoria/fila local.
- Ao finalizar o último carregamento do romaneio, o status passa para `FINALIZADO`.

## TESTE

`bash testes/etapa30.sh`

O teste usa a Dala de demonstração `2` por padrão; para outro ambiente informe `TRACE_TEST_EQUIPMENT_ID`.

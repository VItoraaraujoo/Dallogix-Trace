# Status dos testes — Etapa 31

## STATUS

CONCLUÍDA — leituras bloqueadas fora da operação ativa.

## REGRA

O scanner pode permanecer conectado, mas a API só contabiliza leituras quando o carregamento está em `CARREGANDO`. Em preparação, pausa, emergência ou finalização, o evento retorna um erro e não altera quantidades.

## TESTE

`bash testes/etapa31.sh`

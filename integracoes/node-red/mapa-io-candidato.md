# Mapa de I/O candidato — referência externa

Este arquivo organiza a leitura do diagrama `I-007-00017` disponibilizado no Scribd. Ele **não é o mapa oficial do Dallogix Trace** e não autoriza ligação, escrita ou teste no CLP de produção.

## O que a referência confirma

- Controlador identificado como Delta `DVP-14SS2`.
- Expansões digitais identificadas como `DVP-08SP-1`, `DVP-08SP-2` e `DVP-08SP-3`.
- Entradas nomeadas no desenho: `FCS1`, `FCD1`, `FCSS1`, `FCSD1` e `EMERGENCIA`.
- Sinais de operação/desligamento e comandos associados ao reciprocador, inversor e troca de cor.
- O desenho usa referências elétricas e bornes, mas não entrega, por si só, o endereço Modbus TCP necessário para o gateway.

## Como isso entra no projeto

| Grupo | Candidato observado | Tratamento no TRACE |
| --- | --- | --- |
| Segurança | emergência e fins de curso de segurança | somente leitura; bloqueia comandos quando confirmado no CLP |
| Posicionamento | fins de curso de subida/descida | somente leitura; usado como intertravamento |
| Operação | liga/desliga e reversão | escrita somente após mapa aprovado e máquina pausada |
| Comunicação | DVP-14SS2 e expansões | confirmar módulo Ethernet, IP, porta, unidade e tabela Modbus |

## Pendências obrigatórias antes do Modbus Read/Write

1. Confirmar que o painel da operação atual é exatamente o painel do documento.
2. Entregar a tabela que converte cada sinal elétrico para endereço Modbus (bobina, entrada ou registrador).
3. Confirmar IP, porta TCP, unit ID e modo de endereçamento do módulo Ethernet.
4. Confirmar no Ladder quais intertravamentos precisam estar ativos para iniciar, pausar e reverter.
5. Validar tudo em bancada/simulador antes de qualquer conexão com produção.

Até essas confirmações, o fluxo do Node-RED conclui comandos como `REJEITADO` e não escreve no CLP.

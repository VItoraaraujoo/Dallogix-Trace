# Status dos testes — Etapa 5

## Implementação

- Registro de leituras em `/api/leituras.php`.
- Classificação de barcode `VALIDO`, `PRODUTO_INCORRETO` e `SEM_LEITURA`.
- Registro idempotente de eventos em `/api/sensor_eventos.php`.
- Transições protegidas em `/api/estado_carregamento.php`.
- Validação de empresa, carregamento e esteira em todas as operações.

## Validação

- Barcode válido aceito como `VALIDO`.
- Barcode desconhecido classificado como `PRODUTO_INCORRETO`.
- Evento novo retorna HTTP 201.
- Evento repetido retorna o mesmo registro com `duplicate: true`.
- Transição válida de estado aceita.
- Transição inválida retorna HTTP 409.
- Teste reproduzível: `bash testes/etapa5.sh`.

## Resultado

ETAPA 5 concluída. O próximo passo é conectar a operação real da tela de trabalho aos estados e leituras persistidas.

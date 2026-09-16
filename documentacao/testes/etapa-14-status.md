# Status dos testes — Etapa 14

## Implementação

- Endpoint protegido `/api/dispositivos.php`.
- Heartbeat com upsert por equipamento e tipo de dispositivo.
- Status de CLP, scanner, sensor, câmera e servidor.
- Status dos dispositivos incluído no monitoramento.

## Validação

- Heartbeat de CLP aceito.
- Registro consultável após atualização.
- Status aparece no painel de monitoramento.
- Teste reproduzível: `bash testes/etapa14.sh`.

## Resultado

ETAPA 14 concluída. Próxima etapa automática: fluxo de liberação/finalização do carregamento e encerramento operacional.

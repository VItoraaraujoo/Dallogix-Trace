# Status dos testes — Etapa 16

## Implementação

- Pedido de captura criado no mesmo instante do evento do sensor.
- Tabela `camera_capture_requests` vinculada ao evento, carregamento e equipamento.
- Status de captura: `PENDENTE`, `CAPTURANDO`, `CAPTURADA` e `ERRO`.
- Endpoint protegido para consulta dos pedidos de captura.
- Nenhum delay do scanner é usado para disparar a câmera.

## Resultado

ETAPA 16 concluída. Próxima etapa automática: adaptador Node-RED para executar a captura e salvar a imagem retornada pela câmera.

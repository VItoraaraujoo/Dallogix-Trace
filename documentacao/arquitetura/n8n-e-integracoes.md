# Onde o n8n entra

O n8n entra na camada de integração corporativa e cloud. Ele não deve controlar o motor, a emergência ou o tempo real do CLP.

## Divisão de responsabilidades

```text
CLP Delta + scanner + câmera
          ↓
Adaptador industrial / Node-RED
          ↓
Dallogix Trace local-first
          ↓
Fila de sincronização
          ↓
n8n → ERP, TMS, WMS, e-mail, WhatsApp, BI e APIs externas
```

## Node-RED

Use Node-RED no PC industrial para:

- comunicação Modbus com o Delta;
- leitura contínua do scanner;
- eventos de sensor com baixa latência;
- acionamento da câmera;
- heartbeat dos dispositivos;
- operação mesmo sem internet.

## n8n

Use n8n na nuvem ou em um servidor de integração para:

- consumir a `sync_queue` quando houver conexão;
- enviar romaneios e resultados para ERP/WMS/TMS;
- receber romaneios de sistemas externos;
- enviar notificações de incidentes;
- criar tarefas para supervisores;
- publicar dados em BI;
- fazer retries, transformações e roteamento entre APIs.

O n8n deve consumir uma API autenticada ou webhook do Dallogix. Ele não deve acessar o MySQL diretamente nem receber credenciais do CLP.

## Fluxo recomendado

1. Dallogix grava o evento no banco local.
2. A fila local marca o evento como `PENDENTE`.
3. O sincronizador envia o evento para a API cloud.
4. A API cloud dispara um webhook para o n8n.
5. O n8n integra o evento com os sistemas externos.
6. O n8n devolve o resultado por API, sem alterar diretamente o banco local.

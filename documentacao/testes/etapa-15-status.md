# Status dos testes — Etapa 15

## Implementação

- Campo `attempt_number` permanece disponível para rastreabilidade.
- Falha sem barcode é finalizada como `SEM_LEITURA` no ponto em que o saco passa.
- Ocorrência automática `FALHA_SEM_LEITURA` é criada.
- A câmera é sinalizada como `SOLICITAR_CAPTURA` quando houver configuração.
- Sem protocolo de câmera configurado, o sistema retorna `NAO_CONFIGURADA` sem inventar uma imagem.

## Resultado

ETAPA 15 concluída. Próxima etapa automática: integração do acionamento de câmera e visualização da evidência da falha.

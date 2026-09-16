# Etapa 32 — Fila segura do gateway industrial

Esta etapa separa a intenção registrada no painel da escrita física no CLP.

- `comando_maquina.php` aceita somente reversão de um carregamento pausado e cria uma solicitação pendente.
- `plc_gateway.php` exige o token exclusivo do dispositivo CLP, limita a fila à Dala vinculada e registra o retorno `APLICADO`, `REJEITADO` ou `ERRO`.
- `comandos_industriais.php` disponibiliza ao painel, por empresa, o último estado da solicitação; operador pode apenas visualizar.
- A implantação local usa um token de desenvolvimento por dispositivo. Em produção, cada CLP deve ser provisionado com uma credencial própria; não existe fallback global.
- Enquanto o mapa de I/O continuar como `CONFIRMAR`, o gateway deve concluir a solicitação como `REJEITADO`; não pode escrever no CLP.

Validação:

```bash
bash testes/etapa32.sh
```

O teste cria um romaneio de demonstração, exercita a fila e confirma o retorno seguro `REJEITADO`; ele não acessa nenhum CLP físico.

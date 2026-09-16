# Status dos testes — Etapa 1

## Testes locais executados

- Git: disponível e inicializado.
- PHP: disponível.
- PDO: disponível.
- `pdo_mysql`: disponível.
- Sintaxe dos endpoints PHP: aprovada.

## Testes executados

- `docker compose config`.
- Inicialização dos containers.
- Nginx respondendo HTTP 200.
- PHP-FPM acessível pelo Nginx.
- MySQL saudável e respondendo.
- Node-RED saudável e respondendo HTTP 200.
- Comunicação entre os serviços.
- Extensão `pdo_mysql` carregada no PHP.

## Resultado

Etapa 1 concluída. A imagem do Node-RED foi fixada em `nodered/node-red:3.1.9` porque a tag inicial `4.0` apresentou timeout no download do Docker Hub.

## Etapa 2

- Migrations `001_initial_schema.sql` e `002_operational_schema.sql` aplicadas no MySQL local.
- Seed `001_local_seed.sql` aplicado com sucesso e validado como idempotente.
- Entidades de demonstração confirmadas: empresa, usuário, máquina, esteira, produto e barcode.
- FKs e índices únicos conferidos no `information_schema`.
- Teste de barcode duplicado rejeitado pelo índice `uq_product_barcode`.
- Teste de empresa inexistente rejeitado pela FK `fk_machine_company`.
- Teste reproduzível: `bash testes/etapa2.sh`.

### Resultado

Etapa 2 concluída. O próximo passo é implementar o login local com sessão e controle de acesso.

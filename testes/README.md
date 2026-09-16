# Testes do Dallogix Trace

Os testes de regressão legados usam o nome `etapa<N>.sh` porque acompanham a
ordem de entrega original. Antes de alterar uma funcionalidade, consulte o
documento de status correspondente em `documentacao/testes/etapa-<N>-status.md`
e execute o teste da etapa relacionada.

Para testes novos, use nomes descritivos por funcionalidade, por exemplo:

- `api-leituras.sh`
- `api-romaneios.sh`
- `servico-estado-carregamento.php`
- `permissoes-usuarios.sh`

`qualidade.sh` valida a sintaxe e referências essenciais. `regressao_completa.sh`
executa toda a suíte integrada quando os serviços locais estiverem ativos.

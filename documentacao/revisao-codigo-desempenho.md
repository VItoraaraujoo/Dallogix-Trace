# Revisão de código — desempenho e manutenção

Data: 2026-08-31

## Resultado

O sistema está funcional e a suíte atual foi aprovada. A revisão priorizou os caminhos de maior frequência: dashboard, monitoramento, tela de trabalho e consultas por empresa.

## Achados priorizados

### P1 — consultas agregadas repetidas

`servidor/api/monitoramento.php` e `servidor/api/empresas.php` usam subconsultas correlacionadas para obter o último carregamento, o total planejado e as leituras válidas por Dala. Em bases pequenas isso é adequado, mas o custo cresce com o número de máquinas e leituras.

Próxima evolução recomendada: substituir por CTEs ou tabelas derivadas agregadas, medir com `EXPLAIN ANALYZE` e manter índices compostos por empresa/equipamento e carregamento/resultado.

### P1 — polling com renderização completa

`interface/js/aplicacao.js` atualiza a tela de Trabalho a cada dois segundos, reconstruindo o conteúdo da tela. O mecanismo evita bloquear a navegação, porém pode gerar trabalho desnecessário quando não houve alteração.

Próxima evolução recomendada: retornar `updated_at`/versão do monitoramento, atualizar somente os componentes alterados e aplicar backoff quando o navegador estiver oculto ou o serviço estiver indisponível.

### P2 — verificações de Dala em série lógica N+1

As telas de cadastro e detalhe consultam o status de cada Dala individualmente. Para poucas Dalas é simples; para instalações maiores, recomenda-se um endpoint de status em lote.

### P2 — contratos de API

As chamadas do front estão centralizadas em `ArmazenamentoTrace`, o que é positivo. A próxima melhoria de manutenção é padronizar timeout, cancelamento e tratamento de erro em um único cliente HTTP, evitando que cada método repita o mesmo fluxo.

## Organização do workspace

Foi adicionado `.editorconfig` e configuração do workspace em `.vscode/settings.json` para:

- formatar ao salvar;
- organizar imports explicitamente;
- remover espaços finais;
- manter LF e newline final;
- usar formatadores adequados para JavaScript, JSON e PHP.

O comando visual do VS Code não pôde ser executado nesta sessão porque o macOS estava bloqueado. A configuração fica pronta para ser aplicada ao abrir o workspace desbloqueado.

## Validação

- `bash testes/qualidade.sh`
- `bash testes/regressao_completa.sh`: 37 aprovadas, 0 falhas
- simulador Modbus virtual: aprovado
- API de saúde: PHP e MySQL aprovados
- `git diff --check`: aprovado

Não foram alteradas regras de negócio, dados de produção ou comandos físicos do CLP.

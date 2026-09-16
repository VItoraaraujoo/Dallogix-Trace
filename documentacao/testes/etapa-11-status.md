# Status dos testes — Etapa 11

## Implementação

- Endpoint protegido `/api/importar_csv.php`.
- Validação das colunas `romaneio`, `data`, `placa`, `produto` e `quantidade`.
- Importação transacional de romaneio, caminhão e item.
- Rollback automático quando uma linha ou produto é inválido.
- Formulário de upload conectado ao interface.

## Validação

- CSV de teste importado com HTTP 201.
- Um romaneio e um item criados.
- Registro importado aparece na listagem de romaneios.
- Teste reproduzível: `bash testes/etapa11.sh`.

## Resultado

ETAPA 11 concluída. Próxima etapa automática: validação de rollback com CSV inválido e tratamento de erros de importação.

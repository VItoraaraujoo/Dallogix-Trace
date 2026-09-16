# Equipamento único

## Regra de negócio

No Dallogix Trace, máquina e esteira representam o mesmo equipamento físico da operação. O domínio passou a usar a tabela `equipments` com `id`, `company_id`, `equipment_code`, `name` e timestamps.

## Migração

A migration `003_unify_equipment.sql` preserva os IDs existentes, converte os registros de `machines` e `esteiras` para `equipments`, atualiza as FKs operacionais e remove as tabelas antigas.

As APIs usam `equipment_id`. Para compatibilidade temporária, carregamentos e eventos de sensor ainda aceitam `esteira_id` como alias de entrada.

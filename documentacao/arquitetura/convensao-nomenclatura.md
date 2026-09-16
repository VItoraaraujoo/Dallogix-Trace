# Convenção de nomenclatura do Dallogix Trace

## Objetivo

Manter o sistema em português na camada funcional e operacional, preservando nomes de tecnologias, frameworks e integrações que dependem de convenções externas e não podem ser alterados sem quebrar a infraestrutura.

## Regra principal

1. Pastas e arquivos de domínio do negócio devem usar nomes em português.
2. Pastas e arquivos técnicos de infraestrutura devem manter os nomes padrão da ferramenta ou do ecossistema.
3. Nenhum nome interno deve ser alterado se isso comprometer build, runtime, integração ou compatibilidade.

## Exemplos aprovados

- `interface/` → mantido
- `servidor/` → mantido
- `banco-de-dados/` → mantido
- `documentacao/` → mantido
- `configuracoes`, `carregamentos`, `romaneios`, `usuarios`, `empresas`, `relatorios`, `auditoria` → nomes em português
- `docker-compose.yml`, `nginx`, `mysql`, `node-red`, `php` → mantidos por exigência técnica

## Exceções intencionais

Os nomes abaixo devem permanecer no padrão da tecnologia, mesmo quando o restante do sistema está em português:

- `docker/`
- `nginx/`
- `php/`
- `mysql`
- `node-red`
- `modbus-virtual`
- `tmp/` e `output/` somente quando forem artefatos de ambiente, não módulos de negócio

## Regras para novas alterações

- Sempre criar nomes em português para módulos de operação, regras, telas e relatórios.
- Sempre manter nomes da infraestrutura, pacotes e comandos exatamente como a ferramenta exige.
- Quando a funcionalidade for de negócio e não de runtime, priorizar termos em português, sem abreviações ambíguas.
- Evitar misturar termos em inglês e português no mesmo módulo.

## Status do projeto

O sistema já está em grande parte em português na camada funcional. Os ajustes restantes devem seguir esta convenção e priorizar compatibilidade e estabilidade operacional.

# Estrutura do projeto

O TRACE é organizado para operar no PC industrial, sem internet, com o CLP como autoridade de segurança física.

| Pasta                     | Responsabilidade                                                                                         |
| ------------------------- | -------------------------------------------------------------------------------------------------------- |
| `interface/`              | Telas web, estilos e JavaScript da operação local e administração.                                      |
| `servidor/api/`           | API HTTP local. Mantém as rotas `/api/*.php` consumidas pela interface e pelo Node-RED.                  |
| `servidor/src/Aplicacao/` | Serviços de regra de negócio: estado do carregamento, comandos do CLP, gateway e relatório de auditoria. |
| `servidor/configuracao/`  | Inicialização, sessão, banco de dados, autorização e auditoria.                                          |
| `banco-de-dados/`         | Migrations e dados iniciais do MySQL local.                                                              |
| `integracoes/`            | Contratos, mapa de registradores e fluxo do Node-RED.                                                    |
| `armazenamento/`          | Imagens e evidências do ambiente industrial; não deve ser versionado.                                    |
| `testes/`                 | Testes de API, regras de negócio, permissões e qualidade.                                                |
| `documentacao/`           | Arquitetura, decisões, operação, auditorias e pendências.                                                |

## Limites de responsabilidade

1. A interface apresenta dados e solicita ações autorizadas.
2. A API valida dados, permissões, auditoria e persistência local.
3. O Node-RED traduz mensagens entre o CLP e a API, sem concentrar regras de negócio.
4. O CLP executa intertravamentos, emergência e acionamento físico.
5. O banco local preserva as informações quando não houver internet.

## Disponibilidade do CLP

O gateway envia um heartbeat a cada segundo. Sem sinal do CLP por mais de três segundos, a API bloqueia iniciar, parar, alterar reversão e liberar emergência. A tela informa o bloqueio e consulta novamente o estado a cada dois segundos. A emergência física e os intertravamentos permanecem exclusivamente no Ladder do CLP.

## Compatibilidade industrial

Os nomes internos foram organizados em português. As rotas públicas `/api/*.php` foram mantidas para não interromper a configuração atual do Node-RED, dos testes e de futuras integrações de equipamento.

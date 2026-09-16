# Padrão de desenvolvimento

## Decisão arquitetural

O Dallogix Trace opera como **monólito modular em camadas** no PC industrial. Essa escolha mantém a operação local e a recuperação offline simples, sem introduzir dependências de rede entre serviços durante o carregamento.

O sistema fica preparado para extrair, quando houver necessidade real de escala, três serviços independentes: gateway industrial (CLP), sincronização com nuvem e retenção/armazenamento de imagens. Nenhum deles é necessário para a operação local básica.

## Camadas

| Camada       | Responsabilidade                                                    | Local atual                                                                   |
| ------------ | ------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Apresentação | Telas, componentes visuais e coleta de ações do usuário             | `interface/` e `interface/js/telas/`                                            |
| Controle     | Navegação, eventos de tela, autenticação e composição das respostas | `interface/js/aplicacao.js`, `interface/js/controladores/` e `servidor/api/`      |
| Aplicação    | Regras de carregamento, permissões, auditoria, filas e validações   | `servidor/src/Aplicacao/` e `servidor/configuracao/`                              |
| Dados        | Migrations, consultas preparadas e persistência local               | `banco-de-dados/` e MySQL                                                           |
| Integração   | CLP, scanner, câmera e sincronização remota por filas autenticadas  | `integracoes/`, `plc_gateway.php`, `camera_worker.php` e `sync_queue.php`     |

## Regras para novas alterações

1. A tela apenas solicita uma ação; nunca decide a permissão definitiva.
2. O endpoint valida sessão, empresa, perfil, dados e transição de estado antes de gravar.
3. Acesso ao banco usa consultas preparadas e migrações versionadas.
4. Comandos ao CLP e à câmera entram em fila; a interface nunca escreve diretamente no equipamento.
5. Toda alteração operacional relevante gera auditoria e evento de sincronização.
6. Antes de integrar uma função, execute `bash testes/qualidade.sh` e o teste de etapa correspondente.
7. Ações de interface por assunto devem ficar em `interface/js/controladores/`; `aplicacao.js` fica responsável por compor a tela, navegar e ligar os controladores.
8. Novos testes devem ter nomes descritivos por funcionalidade. As etapas históricas e seu uso estão documentados em `testes/README.md`.

## Primeiro recorte em camadas

O comando de reversão, a transição de carregamento e o gateway industrial já usam o recorte completo: as rotas HTTP são controladores; `ServicoComandoClp`, `ServicoEstadoCarregamento` e `ServicoGatewayClp` guardam as regras de negócio; as tabelas existentes são a persistência atual.

## Limpeza controlada

Um arquivo só pode ser removido quando não tiver importação, referência HTML, uso em teste ou responsabilidade operacional documentada. Arquivos de migration, integrações industriais e evidências não são removidos por busca textual isolada.

# Análise de código — 04/09/2026

## Escopo e limites

Revisão transversal do checkout local baseado em `9d6e559`, incluindo a alteração local já existente em `interface/js/aplicacao.js`. Foram inspecionados autenticação, permissões, carregamento das telas, leituras, comandos industriais, sincronização, estrutura relacional das ações/gatilhos, relatório de imagens, scripts de backup e deploy e workflows. Não é uma certificação de segurança nem uma afirmação de leitura e execução de cada linha dos 254 arquivos inventariados.

Nenhum código da aplicação, configuração de produção ou dado operacional foi alterado nesta revisão. A regressão completa não foi executada porque cria registros e executa operações contra uma instalação existente. Não foram acionados equipamentos físicos. O estado do servidor remoto e os resultados dos workflows no GitHub não foram revalidados nesta análise.

## Achados de prioridade alta

### A01 — Consultas de todas as telas são iniciadas em cada navegação

Evidência: `interface/js/aplicacao.js:1491`, objeto `tasks` contendo chamadas imediatas; apenas em `:1536` é selecionado `tasks[page]`.

Teste isolado executando a função real com dependências simuladas: abrir `manifests` iniciou 38 chamadas de carregamento, 36 delas diferentes de `loadManifests`. Inclui usuários, logs, detalhes sem ID e carregamento de Dala. Não significa necessariamente 38 requisições HTTP, pois alguns métodos podem retornar antes do fetch.

Impacto: tráfego desnecessário, erros de permissão/ID, rejeições não aguardadas e alterações concorrentes no estado compartilhado. Pode contribuir para os problemas de navegação e limite de requisições anteriormente relatados; não foi comprovado que explica sozinho todos eles.

Correção: armazenar funções no mapa e executar somente a função da página selecionada; aguardar dependências de carregamento antes de consultar leituras pendentes.

### A02 — Administrador pode alterar uma conta administrativa da mesma empresa

Evidência: `servidor/api/usuarios.php:62` valida apenas o perfil solicitado. `:81` busca o perfil atual do alvo, mas `:92` só impede desativação da própria conta. O UPDATE em `:98` permite alterar qualquer alvo encontrado na empresa.

Impacto: administrador da empresa pode enviar o ID de outro administrador e um perfil permitido (SUPERVISOR/USUARIO), rebaixar a conta, desativá-la ou trocar sua senha. Não foi demonstrada invasão entre empresas nem promoção a Master.

Correção: autorizar também sobre o perfil atual do alvo e proteger o último administrador e o próprio perfil.

### A03 — Leituras não validam a quantidade de cada produto

Evidência: `servidor/api/leituras.php:81` soma o planejamento de todos os produtos; `:125` verifica apenas se o produto aparece no romaneio; `:141` compara a quantidade total.

Impacto: em um pedido de 5 sacos A e 5 sacos B, dez sacos A podem ser considerados válidos. Correção: validar saldo por produto e caminhão, além do total.

### A04 — Contagem e estados sujeitos a concorrência

Evidência: `servidor/api/leituras.php:48` lê estado, depois conta e insere sem transação/bloqueio do carregamento. `ServicoEstadoCarregamento.php`, método `change`, lê estado e atualiza sem comparação atômica do estado anterior. `ServicoComandoClp.php`, método `requestReversal`, verifica comando pendente antes da transação.

Impacto: duas sessões/estações podem aceitar o último saldo simultaneamente, repetir comandos ou sobrescrever uma transição recente. Correção: transações, bloqueio por carregamento e transições condicionais, com testes de concorrência entre sessões.

### A05 — Gateway fornecido não executa comandos físicos

Evidência: `integracoes/node-red/trace-clp-bridge.flow.json:183`, nó `command-safe-gate`, devolve REJEITADO para reversão e ERRO para outros comandos; informa que o mapa de I/O ainda não foi aprovado.

Impacto: a existência da tela e da fila não demonstra que LIGAR/PARAR/REVERSO funcionam na máquina. É uma restrição deliberada, não deve ser removida sem mapa e intertravamentos homologados. Exige integração e ensaio de bancada.

### A06 — Credencial de gateway sem escopo de equipamento/empresa

Evidência: `servidor/api/equipamentos_gateway.php:9` autentica token global e lista todos os equipamentos. `servidor/api/plc_gateway.php` aceita equipment_id/request_id; `ServicoGatewayClp.php`, método `complete`, confirma por ID sem identidade de gateway.

Impacto: numa instalação central multiempresa, comprometer um gateway com esse token permite consultar/reservar/confirmar solicitações de outras máquinas. Se cada instalação estiver completamente isolada, o impacto fica limitado à instalação. Correção: identidade e credencial por instalação, escopo validado em cada rota e confirmação vinculada à reserva.

### A07 — Gatilho aceita ação de outra Dala/empresa

Evidência: `servidor/api/acoes_dala.php:90` recebe acao_id e atualiza o gatilho sem validar proprietário/equipamento da ação. A FK de `banco-de-dados/migrations/021_acoes_dala_e_logs_erros.sql` garante só existência do ID. `servidor/api/leituras.php:316` resolve a ação sem restringir a empresa/equipamento dela.

Impacto: uma configuração administrativa pode ligar seu gatilho a uma ação alheia e usar o comando dela na própria máquina. Correção: validar pertencimento e coerência no backend e reforçar as consultas.

### A08 — Deploy independente da aprovação de segurança

Evidência: `.github/workflows/deploy-test.yml` dispara em push para master, independentemente de `security.yml` e `quality.yml`. `scripts/sync_github.sh:36` avança código antes de backup; migrações são uma lista fixa contendo somente 020 e 021.

Impacto: código pode chegar ao servidor antes de verificações aprovarem; falha posterior deixa checkout atualizado e migrações futuras podem ser ignoradas. Correção: promover o commit validado, backup antes da promoção, executor versionado de migrações e recuperação de falhas.

## Achados de prioridade média

### A09 — “Conectado” não prova comunicação

Evidência: `interface/js/telas/configuracoes.js:13` usa remote_configured; `servidor/api/sync_status.php` deriva isso de uma URL não vazia. Não verifica sucesso recente. `equipamentos.php:53` testa TCP e relata resposta Modbus sem intercâmbio Modbus.

Correção: distinguir configuração, disponibilidade TCP e comunicação confirmada; apresentar data do último sucesso.

### A10 — Sincronização sem consumidor automático visível e reserva não atômica

Evidência: o envio está em `servidor/api/sync_queue.php:73`; seleção e UPDATE não são uma reserva atômica; `available_at` não condiciona o retry. Não foi localizado consumidor agendado em scripts, flows ou Compose inspecionados. `PROCESSANDO` não possui recuperação implementada nessa rota.

Impacto: dois usuários podem enviar o mesmo evento, e queda do processo pode deixar evento preso. A afirmação anterior de reenvio automático ao retornar a internet não está demonstrada pelo repositório. Um serviço externo não inspecionado pode existir.

Correção: worker, reserva atômica, expiração de reserva, backoff respeitado e idempotência confirmada no receptor.

### A11 — Auditoria/fila podem falhar sem invalidar a operação

Evidência: `servidor/configuracao/bootstrap.php:297` captura erros de record_operational_event e apenas escreve no log. Auditoria e fila são inseridas separadamente; chamadores nem sempre usam transação.

Impacto: operação pode ser considerada concluída sem evento sincronizável ou rastreabilidade completa. Correção: persistir operação e outbox na mesma transação, com falha explícita quando indispensável.

### A12 — Mascaramento incompleto de segredos

Evidência: regex em `servidor/configuracao/bootstrap.php:311` para no primeiro espaço. Reprodução com valor fictício: `Authorization: Bearer TESTE_SEM_SEGREDO` vira `Authorization: [REDACTED] TESTE_SEM_SEGREDO`. Existem error_log diretos fora desse filtro.

Impacto: credenciais presentes em mensagens de erro podem permanecer no log. Correção: mensagens estruturadas com campos permitidos e saneamento centralizado, cobrindo autorização, valores entre aspas e URLs com credenciais.

### A13 — Troca de senha não revoga sessões anteriores

Evidência: `usuarios.php:105` atualiza hash; `bootstrap.php:200` revalida existência/active/perfil, sem versão de sessão ou data de troca de senha. Login não inclui MFA.

Correção: versão de autenticação comparada em cada requisição e incremento na troca de senha, além de implementação real de MFA. MFA por aplicativo pode ser implementado no sistema sem contratar provedor externo; exige fluxo de cadastro, recuperação e testes.

### A14 — Restauração e backups incompletos como garantia de recuperação

Evidência: `scripts/restore_db.sh:8` usa APP_ENV do shell, não a configuração efetiva do contêiner/.env. Não chama verify_backup. `verify_backup.sh:12` aceita ausência de checksum. Backup inclui banco, não arquivos de imagens; não foi localizado agendamento externo no repositório.

Impacto: proteção de produção pode ser omitida pelo ambiente do shell; backup SQL sozinho não recupera evidências fotográficas. Correção: confirmar alvo efetivo, exigir verificação e autorização explícita, incluir imagens e testar restauração em banco isolado. Agendamentos externos não foram inspecionados.

### A15 — Scanner artesanal pode aprovar mesmo se a execução falhar

Evidência: `scripts/security_scan.sh:9` e `:14` tratam git grep dentro de if sem distinguir retorno 1 (nenhum resultado) de erro; excluem documentação e testes. Só analisam arquivos rastreados e poucos formatos.

Correção: tratamento de erro fechado, scanner especializado, cobertura de histórico e exceções mínimas. SBOM em security.yml é inventário; não constitui análise de vulnerabilidades. CodeQL configurado somente para JavaScript não cobre o backend PHP.

### A16 — Consulta de ações cria configuração e devolve ID incorreto na criação

Evidência: `acoes_dala.php:63` executa ensure_default_actions em GET, sem transação. Em POST, record_operational_event insere auditoria/fila antes do lastInsertId usado na resposta (`:79`).

Impacto: leitura pode recriar ações excluídas, inicializações simultâneas podem falhar parcialmente e o ID devolvido pode ser da fila. Correção: inicialização explícita transacional e guardar o ID antes de registrar auditoria.

## Verificações realizadas

- `bash testes/qualidade.sh`: aprovado (sintaxe PHP/JS/Shell, JSON e verificações estáticas).
- `bash scripts/security_scan.sh`: aprovado; limitações descritas em A15.
- `bash testes/etapa29.sh`: aprovado; é verificação estática e não comprova fluxo completo.
- Reprodução isolada de A01 com função real e armazenamento simulado.
- Reprodução da expressão de mascaramento com texto fictício, sem usar credenciais reais.
- Consulta somente de leitura ao estado dos contêineres locais; disponibilidade não equivale a homologação.

## Conclusão

As respostas anteriores exageraram a conclusão de segurança e integração. Há controles úteis, mas não evidência de que todas as rotas e fluxos estejam corretos. A prioridade é corrigir A01–A08 e validar concorrência, isolamento e comandos antes da instalação industrial. Em seguida: sincronização, auditoria, sessões, restauração e CI. Testes em ambiente isolado e com hardware homologado continuam necessários.

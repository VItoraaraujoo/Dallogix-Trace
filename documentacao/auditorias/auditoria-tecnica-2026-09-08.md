# Auditoria técnica do Dallogix Trace

**Data:** 08/09/2026  
**Escopo:** backend PHP, APIs, autenticação e sessão, banco MySQL, front-end JavaScript/HTML, Docker/Nginx, Node-RED, testes, observabilidade e deploy.  
**Objetivo:** avaliar segurança, estabilidade, performance, escalabilidade, arquitetura, manutenção e prontidão para produção industrial.

## 1. Diagnóstico executivo

O projeto tem uma base funcional coerente para um MVP avançado ou homologação controlada: há separação inicial entre interface, APIs, serviços de aplicação, configuração, integrações e armazenamento; as consultas usam prepared statements em grande parte do código; existem controles de sessão, CSRF, rate limit, trilha de auditoria, fila de sincronização, backups e verificações automatizadas.

Ainda não recomendo liberar o sistema para uma operação industrial ampla sem uma etapa adicional de estabilização. Os principais motivos são:

- a quantidade planejada é validada de forma agregada, sem garantir o limite por produto;
- leituras e transições de carregamento podem sofrer corrida concorrente;
- o gateway ainda não está homologado para comandar o CLP real e usa credencial global;
- o deploy pode ocorrer mesmo que a validação de qualidade/segurança falhe;
- a fila local-first ainda não apresenta um trabalhador automático, lease, dead-letter e confirmação de contrato robusto;
- a revogação de sessão após troca de senha e MFA ainda não estão implementados;
- observabilidade, testes de concorrência, testes E2E e restauração operacional ainda são insuficientes para produção.

Conclusão: **apto para evolução e homologação assistida; não apto para produção irrestrita sem os itens de bloqueio da Fase 0.**

As notas abaixo são uma avaliação técnica baseada na estrutura e nos arquivos presentes, não uma certificação de segurança.

| Área | Avaliação indicativa | Situação |
|---|---:|---|
| Organização do código | 6/10 | Há camadas iniciais, mas muitos controladores ainda concentram responsabilidades |
| Segurança da aplicação | 6/10 | Bons controles básicos; faltam endurecimentos de identidade, escopo e segredos |
| Integridade operacional | 5/10 | Fluxos existem, mas há riscos de concorrência e validação incompleta |
| Performance e escala | 4/10 | Adequado para baixa/média escala; gargalos aparecem com muitas máquinas e leituras |
| Observabilidade | 4/10 | Há logs e auditoria, mas falta correlação, métricas e alertas operacionais |
| Deploy e recuperação | 5/10 | Há backup e CI, mas faltam gate, releases atômicos e rollback testado |
| Prontidão industrial | 2/10 | O mapa real do CLP e a homologação do comando físico continuam pendentes |

## 2. Pontos fortes encontrados

- `servidor/configuracao/bootstrap.php` centraliza cabeçalhos de segurança, sessão, JSON, CSRF, rate limit e contexto do usuário.
- Cookies de sessão usam `HttpOnly`, `SameSite=Strict` e podem usar `Secure` em produção.
- `request_json()` limita o corpo e rejeita JSON inválido.
- Autenticação usa `password_verify()`, mensagem genérica de falha e rehash quando necessário.
- APIs usam consultas parametrizadas na maior parte dos pontos examinados.
- Há segregação por empresa em vários serviços e autorização por papel.
- Existe trilha de auditoria e fila de sincronização para o modelo local-first.
- Nginx aplica limites de requisição/conexão, `nosniff`, política de frame, referrer e CSP.
- O front-end escapa valores dinâmicos com `esc()` em seus principais templates e não usa `eval`, `new Function` ou scripts externos.
- Existem scripts de backup, verificação de integridade e proteção contra restauração acidental em produção.
- O CI executa verificações de qualidade, testes isolados e varredura de segredos.
- O gateway mantém uma barreira de segurança que impede comandos físicos ainda não homologados.

## 3. Achados prioritários

### Críticos para a liberação industrial

#### A-01 — Quantidade aceita não é controlada por produto

**Evidência:** `servidor/api/leituras.php` calcula o planejado com soma agregada e valida se o produto pertence ao romaneio, mas não mantém um contador/limite específico para cada item.

**Risco:** um romaneio com produtos A e B pode aceitar quantidade excedente de A desde que o total geral permaneça dentro do planejado. Isso compromete rastreabilidade, estoque e conferência.

**Correção:** validar dentro de uma transação o item do romaneio, somando apenas as leituras daquele produto e rejeitando quando `lido + quantidade_atual > planejado`. Criar índice composto para a consulta e cobrir o caso com teste de dois produtos.

#### A-02 — Corrida entre leituras e mudança de estado

**Evidência:** `leituras.php`, `servidor/src/Aplicacao/ServicoEstadoCarregamento.php` e `servidor/src/Aplicacao/ServicoComandoClp.php` fazem leituras de estado/contagem e depois gravam sem um bloqueio consistente de linha ou atualização condicional baseada no estado anterior.

**Risco:** duas requisições simultâneas podem aceitar o mesmo limite, repetir comando ou sobrescrever uma transição de carregamento.

**Correção:** usar transação MySQL, `SELECT ... FOR UPDATE` na unidade de concorrência correta e `UPDATE ... WHERE estado = estado_anterior`; rejeitar quando `rowCount() !== 1`. Adicionar teste concorrente real contra MySQL, não apenas mocks.

#### A-03 — Homologação do CLP físico ainda não concluída

**Evidência:** `integracoes/node-red/trace-clp-bridge.flow.json` mantém o gate seguro bloqueando comandos não aprovados. A documentação ainda indica pendência de modelo exato, mapa de registradores, entradas/saídas e intertravamentos.

**Risco:** habilitar escrita sem mapa validado pode movimentar a máquina incorretamente. O bloqueio atual é adequado, mas significa que a integração industrial ainda não está pronta.

**Correção:** homologar em bancada o CLP com rede, mapear cada comando, validar retorno, timeout, heartbeat e intertravamento com responsável de automação. Manter parada de emergência e permissivos no CLP; a aplicação não deve ser a única barreira de segurança.

### Altos

#### A-04 — Credencial do gateway é global e não está vinculada ao equipamento

**Evidência:** `servidor/api/equipamentos_gateway.php` e `servidor/api/plc_gateway.php` autenticam por token interno, enquanto `ServicoGatewayClp` localiza requisições por identificador/status sem comprovar a identidade do gateway responsável.

**Risco:** se o token for obtido, um gateway pode consultar ou completar operações de outra instalação/empresa, dependendo da exposição do servidor.

**Correção:** credencial por instalação/equipamento, escopo explícito de empresa e equipamento, expiração/rotação e auditoria de cada operação. Em ambiente distribuído, preferir certificado por instalação ou token armazenado fora do código.

#### A-05 — Deploy não depende dos checks de qualidade e segurança

**Evidência:** `.github/workflows/deploy-test.yml` reage ao push em `master`, enquanto os workflows de qualidade e segurança são independentes.

**Risco:** uma alteração pode chegar ao servidor mesmo com teste ou scanner falhando.

**Correção:** proteger `master`, exigir checks obrigatórios e fazer o deploy depender de um workflow de validação concluído. Separar build, migração, smoke test e promoção. A imagem/artefato deve ser imutável e identificada pelo commit.

#### A-06 — Atualização do código ocorre antes de um backup verificável e o script de migração é restrito

**Evidência:** `scripts/sync_github.sh` atualiza o checkout antes do backup e executa somente um conjunto fixo de migrações (`020`/`021`). Não há troca atômica por diretório de release nem rollback automático após smoke test.

**Risco:** falha de código ou esquema pode deixar o servidor em estado parcialmente atualizado; migrações futuras podem ser esquecidas.

**Correção:** backup verificado antes da promoção, controle de migrações por tabela/versão, releases versionadas, symlink ou mecanismo equivalente, health check pós-promoção e procedimento testado de rollback.

#### A-07 — Fila de sincronização ainda não é um outbox operacional completo

**Evidência:** `servidor/api/sync_queue.php` permite consulta/reprocessamento, mas não há trabalhador automático claramente configurado em `docker-compose.yml`, scripts ou flows; faltam lease para `PROCESSANDO`, dead-letter, backoff exponencial com jitter e validação forte da resposta remota.

**Risco:** eventos podem permanecer parados, ser processados duas vezes ou ficar presos após queda do processo. A operação local continua, mas a convergência remota fica incerta.

**Correção:** fila com estados `PENDENTE`, `PROCESSANDO`, `ENVIADO`, `ERRO` e `DESCARTADO`; lease com prazo; `event_uuid` idempotente no receptor; backoff; limite de tentativas; painel apenas com resumo operacional e alerta para fila envelhecida.

#### A-08 — Auditoria e sincronização podem falhar sem abortar a operação

**Evidência:** `record_operational_event()` registra falhas de auditoria/fila em log e permite que o fluxo chamador prossiga.

**Risco:** uma ação crítica pode ser concluída sem evidência auditável ou evento de sincronização.

**Correção:** para eventos obrigatórios, gravar operação e outbox na mesma transação. Se a auditoria for opcional, declarar isso no contrato e gerar alerta explícito; não esconder falha crítica apenas em `error_log`.

#### A-09 — Sessões antigas permanecem válidas após troca de senha

**Evidência:** a verificação de sessão confirma que o usuário está ativo, mas não há versão de sessão ou `senha_alterada_em` comparada no cookie/sessão. MFA não foi encontrado.

**Risco:** uma sessão roubada continua funcionando após a troca de senha ou desativação parcial.

**Correção:** adicionar `versao_sessao` ou timestamp de credencial, invalidar sessões na troca de senha/desativação e aplicar MFA ao perfil master/administradores quando o ambiente estiver exposto externamente.

#### A-10 — URL remota configurável sem política de destino suficientemente restritiva

**Evidência:** `sync_queue.php` usa URL de configuração para comunicação remota. A interface deixou de expor esse dado, mas o backend ainda aceita configuração persistida.

**Risco:** em caso de comprometimento administrativo ou de banco, a aplicação pode ser usada para requisições a destinos internos ou não autorizados (SSRF) e para vazamento de payload.

**Correção:** permitir somente HTTPS, host em allowlist por ambiente, bloquear IPs privados/reservados após resolução, limitar redirecionamentos e validar status/content-type/schema da resposta.

## 4. Achados médios e de manutenção

#### A-11 — Status de conexão não representa uma verificação recente

`sync_status.php` e `configuracoes.js` informam configuração ou último erro, mas isso não equivale a uma confirmação recente de sucesso. O usuário deve ver “último contato confirmado”, horário e idade do evento, sem expor URL, token ou payload.

#### A-12 — Redação de logs é incompleta

`registrar_log_erro()` mascara padrões de senha/token, mas o padrão pode parar no primeiro espaço e há chamadas diretas a `error_log()` em serviços. O resultado pode preservar partes de `Authorization`, tokens ou dados pessoais.

Centralizar logging em uma função estruturada, com campos permitidos, redaction por chave, `request_id`, usuário, empresa, rota, status e duração. Nunca registrar corpo completo, senha, token, cookie ou payload industrial sensível.

#### A-13 — Observabilidade não fecha o ciclo operacional

Há `logs_erros` e `logs_auditoria`, mas faltam métricas de latência, taxa de erro, fila por idade, heartbeat por instalação, comandos rejeitados, falhas de banco e espaço em disco. Também faltam alertas e política de retenção.

Começar com logs JSON, access log do Nginx, endpoint de saúde protegido quando necessário, verificação periódica e alertas por limiar. Centralização/SIEM pode ser uma etapa posterior.

#### A-14 — Backup não cobre claramente todo o estado do sistema

O backup do MySQL tem checksum e retenção, porém não há evidência de rotina agendada, cópia fora do servidor, criptografia em repouso, teste periódico de restauração ou inclusão garantida das imagens armazenadas em `armazenamento/`.

Definir RPO/RTO, copiar banco e imagens de forma consistente, criptografar, manter cópia fora do host e executar restauração de prova em ambiente isolado.

#### A-15 — Escalabilidade do dashboard e status de máquinas é limitada

Há consultas agregadas e endpoints que podem buscar muitos registros; a checagem de disponibilidade de equipamentos abre conexões com timeout por equipamento. Com dezenas de dalas, isso aumenta a latência da tela e a carga no servidor.

Adicionar paginação, índices revisados por `EXPLAIN`, agregados/counters para progresso, cache curto apenas para dados não críticos e um coletor de status periódico. A tela deve consultar o último status persistido, não testar cada CLP durante cada carregamento.

#### A-16 — Importações e relatórios concentram processamento e memória

`importar_csv.php`, `importar_pdf.php`, `romaneios.php` e `RelatorioAuditoriaPdf.php` concentram validação, consultas e transformação de dados. Importações linha a linha e PDFs com imagens podem gerar N+1 e picos de memória.

Validar arquivo e tamanho, processar em lotes, usar transação por lote, consultar mapas de produtos previamente, limitar dimensões de imagem e gerar relatório pesado de forma assíncrona quando necessário.

#### A-17 — Arquivos de API ainda são controladores monolíticos

Os maiores controladores chegam a centenas de linhas e misturam autorização, validação, SQL, transição de estado, auditoria e resposta HTTP. Isso aumenta custo de manutenção e risco de regressão.

Migrar gradualmente para controlador fino, DTO/validador, serviço de caso de uso, repositório e adaptador de integração. Não é necessário trocar a stack nem reescrever o sistema.

#### A-18 — CSRF desativado no ambiente `local`

Isso é útil para testes isolados, mas perigoso se uma instalação local for acessível por outros dispositivos na rede e usar autenticação por cookie. O modo local deve continuar restrito a loopback ou exigir um sinal explícito de teste.

#### A-19 — Configuração padrão contém credenciais fracas de desenvolvimento

`docker-compose.yml` usa valores `change-me-*` e o ambiente padrão é local. Isso precisa ser impossível em produção: validação deve falhar no startup se qualquer segredo padrão existir, e os exemplos devem estar separados de `.env` real.

#### A-20 — Dependências e cadeia de suprimentos precisam de governança

Há scanner de segredos, CodeQL para JavaScript e SBOM no CI, mas a governança ainda é artesanal; não há evidência de lockfiles completos para PHP/Node ou de pinagem por digest/SHA de todas as imagens e actions. O scanner local não substitui análise de histórico, dependências e revisão humana.

## 5. Correções já realizadas e verificadas

Os pontos abaixo foram encontrados durante a evolução recente e já possuem correção no código ou no CI:

- carregamento de páginas no front-end deixou de executar todas as tarefas na construção do objeto; o fluxo agora chama apenas as tarefas da página atual;
- criação de ações passou a retornar/usar o identificador criado;
- ações e gatilhos passaram a validar vínculo com equipamento e empresa;
- regras de usuário impedem que administrador de empresa altere o administrador da plataforma ou desative a própria conta;
- retry da fila passou a reservar o evento com atualização condicional para reduzir duplicidade concorrente;
- respostas JSON passaram a usar `Cache-Control: no-store`;
- corpos JSON grandes/malformados são rejeitados;
- rehash de senha é aplicado quando necessário;
- o teste isolado de permissões foi corrigido para respeitar `strict_types` no PHP 8.3;
- testes locais de navegação, permissões, qualidade, etapa 29 e scanner de segurança passaram após a última correção.

Essas correções reduzem risco, mas não substituem os testes concorrentes e de integração real indicados abaixo.

## 6. Arquitetura recomendada, mantendo a stack atual

Não há necessidade de migrar imediatamente para outra linguagem ou microserviços. A evolução mais segura é um monólito modular com adaptadores bem definidos:

1. **Entrada HTTP:** autenticação, autorização, validação de entrada, limite e resposta padronizada.
2. **Aplicação:** casos de uso como criar romaneio, registrar leitura, iniciar/pausar/finalizar carregamento, emitir comando e sincronizar evento.
3. **Domínio:** regras de quantidade, estados permitidos, permissões e invariantes industriais, sem SQL ou HTML.
4. **Infraestrutura:** repositórios MySQL, transações, sessões, logs, armazenamento e fila outbox.
5. **Integrações:** gateway CLP, scanner, câmera e sincronização remota, cada um com contrato, timeout e tratamento de falha.
6. **Interface:** telas que consomem contratos estáveis e exibem status resumido; nenhum segredo ou detalhe interno de sincronização.

Para o local-first, a operação deve gravar localmente a transação e o evento outbox de forma atômica. O sincronizador envia eventos idempotentes, mantém tentativas e não bloqueia a operação por indisponibilidade da nuvem. Conflitos devem ter regra por entidade e ser auditados; não basta “última gravação vence” para operações industriais.

Para o CLP, a aplicação deve solicitar intenção e acompanhar resultado. O CLP continua responsável por permissivos, intertravamentos, parada segura e estado físico. O gateway deve ter identidade por instalação e operar com escopo mínimo.

## 7. Padronização de nomes

Recomendo não renomear todo o banco de uma vez. Padronizar nomes novos em português e manter compatibilidade por migrações/aliases reduz risco.

| Atual/misto | Padrão de domínio recomendado |
|---|---|
| `company_id` | `empresa_id` |
| `equipment_id` / Dala | `dala_id` ou `equipamento_id`, escolher um termo oficial |
| `created_at` | `criado_em` |
| `updated_at` | `atualizado_em` |
| `settings` | `configuracoes` |
| `sync_queue` | `fila_sincronizacao` |
| `payload` | `dados_evento` |
| `status` | `situacao` quando não houver ambiguidade |
| `equipment_code` | `codigo_dala` ou `codigo_equipamento` |
| `request_id` | `id_requisicao` |
| `image_path` | `caminho_imagem` |

Convenções: tabelas e colunas em `snake_case`, verbos de caso de uso em português, estados enumerados documentados, datas em UTC no banco, identificadores estáveis e um glossário único para “dala”, “romaneio”, “carregamento”, “leitura” e “saco”. Renomeações devem ocorrer por compatibilidade progressiva, nunca apenas alterando a interface.

## 8. Plano de implementação por fases

### Fase 0 — Bloqueios antes da produção industrial

- corrigir quantidade por produto;
- adicionar transações, locks e testes concorrentes para leitura/estado/comando;
- proteger `master` e transformar qualidade/segurança em gate obrigatório do deploy;
- corrigir fluxo de migrações, backup pré-promoção, health check e rollback;
- remover segredos padrão em produção e exigir HTTPS quando houver acesso remoto;
- definir escopo/credencial por gateway;
- manter comandos físicos bloqueados até concluir homologação do CLP.

### Fase 1 — Confiabilidade e rastreabilidade

- separar outbox da auditoria ou tornar ambos atômicos;
- implementar trabalhador da sincronização com lease, backoff, dead-letter e idempotência;
- adicionar revogação de sessão, MFA para perfis administrativos e rotação de segredos;
- padronizar logs estruturados, correlação e política de retenção;
- incluir backup de imagens e simulado de restauração com RPO/RTO definidos.

### Fase 2 — Performance e escala controlada

- medir queries com `EXPLAIN` e revisar índices;
- paginar listas e relatórios;
- substituir contagens repetidas por agregados seguros quando necessário;
- mover status de equipamentos para coleta periódica;
- processar importações e PDFs em lotes, com limites de memória e tamanho;
- executar teste de carga com o número esperado de empresas, dalas, romaneios e leituras.

### Fase 3 — Homologação industrial

- registrar modelo exato do CLP de rede;
- documentar mapa de registradores, comandos, retornos, timeout e heartbeat;
- testar falha de rede, reinício do PC industrial, queda do servidor, duplicidade de leitura e recuperação offline;
- validar fisicamente permissivos e emergência com automação responsável;
- habilitar somente comandos aprovados por instalação.

### Fase 4 — Governança de produção

- revisão de dependências e imagens por versão/digest;
- análise de segurança recorrente, revisão humana e teste de intrusão autorizado;
- monitoramento de disponibilidade, erros, fila, banco, disco e recursos;
- exercícios de restauração e rollback;
- gestão formal de mudanças, aprovação, versão, evidência de teste e registro de incidente.

## 9. Matriz mínima de testes que ainda falta

- **API com MySQL real:** permissões por empresa, transições inválidas, idempotência e rollback de transação.
- **Concorrência:** dois leitores para o último item, duas finalizações, dois comandos e retry simultâneo.
- **Front-end E2E:** login por perfil, navegação de voltar, visualização de dala, ações, cancelamento e mensagens de erro.
- **Local-first:** operação sem internet, fila acumulada, reconexão, resposta duplicada e conflito.
- **Industrial:** simulador do CLP, timeout, heartbeat expirado, reinício do gateway e comandos não mapeados.
- **Backup:** restauração de banco, imagens e configuração em ambiente isolado.
- **Carga:** muitas dalas no dashboard, importação grande, geração de PDF com imagens e volume alto de leituras.
- **Segurança:** brute force, CSRF fora do modo de teste, escopo entre empresas, SSRF, upload, autorização horizontal e exposição de logs.

## 10. Decisão recomendada

Manter o projeto como monólito modular PHP/MySQL com Nginx, Docker e Node-RED, concluir a Fase 0 e homologar uma instalação-piloto. Só depois ampliar para várias máquinas/empresas. A prioridade não é adicionar telas: é garantir integridade de quantidade, concorrência, escopo do gateway, sincronização idempotente, rollback e evidência auditável.

Com esses itens resolvidos, o sistema terá uma base realista para crescer sem perder o funcionamento local-first e sem transformar a aplicação web em responsável isolada pela segurança da máquina.

# Relatório da Fase 1 — Varredura inicial do Dallogix Trace

Data da auditoria: 2026-08-28
Escopo analisado: `documentacao/prompt-mestre-dallogix-trace.md`
Comando de qualidade do projeto: `bash testes/qualidade.sh`

## Resumo executivo

O código atual está funcional para o ambiente local de desenvolvimento: os arquivos PHP e JavaScript passaram pela verificação de sintaxe e o teste de qualidade passou. A arquitetura local-first, o controle por empresa e o fluxo industrial seguro estão parcialmente implementados.

O sistema ainda não deve ser considerado pronto para produção. Os principais riscos são o uso de valores padrão conhecidos para tokens internos, a dependência de configuração correta de `APP_ENV` para ativar CSRF, consultas de monitoramento com subconsultas repetidas e funcionalidades de nuvem, backup/restauração e integração física ainda incompletas ou pendentes de confirmação.

## Evidências de execução

- `find servidor -type f -name '*.php' ... php -l`: todos os arquivos PHP passaram.
- `find interface -type f -name '*.js' ... node --check`: todos os arquivos JavaScript passaram.
- `bash testes/qualidade.sh`: **OK**.
- Os containers locais estavam ativos, incluindo MySQL, Nginx, PHP, Node-RED e o simulador Modbus.
- Nenhuma ação foi executada em produção ou em equipamento físico.

## Segurança

### S-01 — Tokens internos padrão podem ser aceitos fora do ambiente local — Alta

O Compose fornece `change-me-camera-token` e `change-me-plc-token` como fallback em [docker-compose.yml](/Users/vitorhugocordeiroantunesaraujo/Documents/Dallogix Trace/docker-compose.yml:24). A rota de heartbeat valida apenas se o token recebido coincide com o valor configurado, sem rejeitar explicitamente o token padrão em [device_heartbeat.php](/Users/vitorhugocordeiroantunesaraujo/Documents/Dallogix Trace/servidor/api/device_heartbeat.php:9). A nova rota interna de descoberta de equipamentos tem a mesma característica em [equipamentos_gateway.php](/Users/vitorhugocordeiroantunesaraujo/Documents/Dallogix Trace/servidor/api/equipamentos_gateway.php:9).

Impacto: se uma implantação usar os defaults, alguém com acesso à rede poderá forjar heartbeat e fazer uma Dala parecer online. O gateway de comandos já rejeita o default, mas a política deve ser uniforme.

### S-02 — CSRF depende de `APP_ENV=production` — Média

`require_csrf()` retorna sem validar o token quando o ambiente não é produção em [bootstrap.php](/Users/vitorhugocordeiroantunesaraujo/Documents/Dallogix Trace/servidor/configuracao/bootstrap.php:71). O Compose usa `local` como fallback em [docker-compose.yml](/Users/vitorhugocordeiroantunesaraujo/Documents/Dallogix Trace/docker-compose.yml:20).

Impacto: uma implantação exposta com `APP_ENV` ausente pode aceitar requisições mutáveis sem CSRF. A correção deve tornar o modo seguro explícito para qualquer ambiente acessível por navegador, mantendo uma exceção documentada somente para testes locais.

### S-03 — Credenciais de desenvolvimento documentadas — Baixa, controlada

O README e os testes documentam `admin@dallogix.local` / `password`. Isso é aceitável para seed local, mas não pode ser levado para uma instalação real. O risco aumenta se o seed for executado sobre um banco de produção.

### Pontos positivos de segurança

- Queries observadas usam PDO com parâmetros, reduzindo risco de SQL injection.
- A sessão regenera o ID após login em [login.php](/Users/vitorhugocordeiroantunesaraujo/Documents/Dallogix Trace/servidor/api/login.php:30).
- Senhas novas são armazenadas com `password_hash()` em [usuarios.php](/Users/vitorhugocordeantunesaraujo/Documents/Dallogix Trace/servidor/api/usuarios.php:166).
- O gateway industrial exige token e o servidor não executa escrita física enquanto o mapa de I/O não está homologado.
- O isolamento por empresa está presente nas consultas críticas auditadas.

## Performance

### P-01 — Monitoramento usa subconsultas correlacionadas por equipamento — Média

O endpoint executa, para cada equipamento, subconsultas para último carregamento, quantidade planejada e leituras válidas em [monitoramento.php](/Users/vitorhugocordeiroantunesaraujo/Documents/Dallogix Trace/servidor/api/monitoramento.php:69). Com muitas Dalas, isso tende a aumentar o custo de cada atualização e é agravado pelo polling da Tela de Trabalho em [aplicacao.js](/Users/vitorhugocordeiroantunesaraujo/Documents/Dallogix Trace/interface/js/aplicacao.js:281).

Recomendação: substituir por agregações pré-calculadas/CTEs ou consultas agrupadas, revisar índices e manter um intervalo de atualização com proteção contra chamadas sobrepostas.

### P-02 — Dashboard e relatórios repetem agregações — Baixa/Média

Dashboard, romaneios e monitoramento calculam contagens por meio de consultas separadas. Funciona no volume atual, mas precisa de medição e índices antes de escalar para várias empresas e máquinas.

### P-03 — Polling assíncrono sem cancelamento de requisições — Baixa

O front usa `Promise.all()` no polling, o que é adequado para paralelismo, porém não há `AbortController` nem controle de geração para cancelar chamadas antigas em todas as telas. Em rede lenta, respostas atrasadas podem consumir recursos e atualizar uma tela já abandonada.

## Gaps em relação ao escopo

### G-01 — Servidor central/cloud não está implementado integralmente — Alta

O escopo exige painel central Dallogix, empresas, instalações, licenças, monitoramento remoto, sincronização, backup, atualizações e suporte. O código atual possui o painel local e APIs administrativas, mas não há uma implementação completa de servidor central nem contrato de sincronização central validado.

### G-02 — Backup, restauração e teste de corrupção — Alta

O escopo exige criação, armazenamento, restauração, teste de corrupção e recuperação validada. Na Fase 2 foram criados scripts locais de backup/restauração e uma restauração foi validada em um banco local temporário; o teste de corrupção controlada e a rotina operacional de retenção ainda precisam ser formalizados.

### G-03 — Integração física do CLP continua pendente — Esperado, não defeito

O próprio escopo proíbe inventar IP, protocolo, registrador ou comando. O mapa de I/O, sufixo exato do Delta DVP, Ladder e testes de bancada continuam marcados como pendentes. O simulador Modbus local está correto como etapa intermediária, mas não comprova a integração física.

### G-04 — Scanner e câmera reais ainda não homologados — Média

Existem fluxos de aplicação e simulação, mas interface física do scanner, protocolo/endereço da câmera e testes com os dispositivos reais permanecem pendentes conforme o README e a documentação operacional.

### G-05 — Testes não cobrem 100% dos módulos e cenários do escopo — Alta

O comando atual verifica principalmente sintaxe e referências. Há scripts por etapa, mas não existe ainda uma suíte unitária/integração unificada que comprove todos os cenários de falha, queda de energia, offline, sincronização, multi-tenant, backup/restauração e equipamentos reais.

### G-06 — Modelo de equipamentos está em transição — Média

As migrations antigas mantêm `machines`/`esteiras`, enquanto a migration de unificação introduz `equipments`. Essa transição é documentada, mas precisa de validação final para garantir que não existam caminhos ou constraints órfãos antes de produção.

## Itens que não foram classificados como falhas

- A quantidade de Dalas não deve ser limitada: o gateway local foi ajustado para descoberta automática dos equipamentos cadastrados.
- O Dashboard não controla máquina; o controle permanece no fluxo da Tela de Trabalho, conforme a organização funcional definida.
- O resumo final está condicionado à finalização do carregamento.
- A ausência de mapa físico do CLP não será “corrigida” inventando registradores; deve permanecer pendente até confirmação industrial.

## Conclusão da Fase 1

**STATUS: diagnóstico concluído e convertido em plano de correção.**

## Atualização após a Fase 2

Foram aplicados: validação centralizada de tokens internos, descoberta automática de todas as Dalas pelo Node-RED, índices operacionais, proteção contra polling sobreposto, scripts de backup/restauração, autenticação opcional da sincronização remota, validação HTTPS em produção e regressão local com fixture dinâmica. O dump local foi gerado e restaurado em banco temporário, com conferência de usuários e equipamentos.

O conjunto funcional foi reorganizado para não depender de IDs fixos, respeitar a ordem numérica das etapas, criar/promover uma fixture local quando necessário e selecionar uma Dala livre nos ciclos de preparação. A execução atual de `bash testes/regressao_completa.sh` retornou **37 aprovadas e 0 falhas**; `bash testes/qualidade.sh` também está aprovado e a configuração do Compose foi validada.

As pendências industriais permanecem intencionais: mapa de I/O e registradores do CLP Delta, homologação de scanner/câmera reais, endpoint cloud definitivo e operação de produção. Até essas confirmações, o Node-RED e o gateway seguem em modo seguro/simulado e não enviam escrita física.

Foi acrescentado o ciclo de licenciamento mensal por empresa na migration `016_licencas_empresas.sql`, com controle Master em `licencas.php` e bloqueio lógico de novas operações. O bloqueio não interrompe carregamentos já iniciados nem atua nos intertravamentos do CLP.

A migration `017_regras_operacionais_pendentes.sql` acrescenta justificativa de encerramento, motivo de parada e autoria de retornos. O backend aplica debounce de 400 ms, registra excesso e produto incorreto como parada segura, oferece identificação manual de leituras sem código e exige autorização/justificativa para encerramento divergente. A parada física continua deliberadamente condicionada ao gateway e ao mapa de I/O homologado.

Para o PC industrial com interface gráfica, foram acrescentados o serviço de inicialização automática do Compose, o serviço de navegador em modo quiosque, a recuperação automática do navegador e reinício dos serviços locais. O quiosque exige uma conta Linux operacional separada e configuração da sessão gráfica; não substitui as proteções administrativas do próprio Linux.

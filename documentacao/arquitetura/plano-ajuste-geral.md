# Plano de ajuste geral do Dallogix Trace

## Visão geral

Este plano consolida os ajustes necessários para deixar o sistema coerente, padronizado e operacionalmente seguro, sem comprometer a compatibilidade da infraestrutura local e industrial.

## 1. Padronização da nomenclatura

### Objetivo

Manter o domínio funcional em português e preservar os nomes técnicos exigidos por Docker, PHP, MySQL, Nginx, Node-RED e integrações industriais.

### Ações

- Definir nomes em português para telas, módulos de operação, relatórios, permissões e regras de negócio.
- Manter nomes de infraestrutura e ferramentas no padrão natural da tecnologia.
- Revisar arquivos e referências internas sem alterar compatibilidade dos endpoints e serviços.
- Registrar a convenção em documentação e validar antes de qualquer nova mudança.

## 2. Organização estrutural do projeto

### Objetivo

Organizar a lógica para facilitar manutenção, suporte e evolução sem duplicar responsabilidades.

### Ações

- Separar claramente domínio, UI, API, banco, integrações e implantação.
- Manter front-end, backend e infraestrutura com limites bem definidos.
- Preservar o comportamento local-first e reduzir dependência de serviços externos.
- Atualizar documentação de arquitetura e operação com a estrutura atual.

## 3. Ajustes de negócio e fluxo operacional

### Objetivo

Garantir que o carregamento, monitoramento, ocorrências, auditoria e sincronização sigam um modelo consistente e rastreável.

### Ações

- Validar o ciclo de carregamento em todas as etapas: preparação, conferência, autorização, execução e encerramento.
- Confirmar a relação entre ocorrências, divergências, justificativas e auditoria.
- Padronizar mensagens de status e confirmação para telas e integrações.
- Revisar regras de permissões por perfil e garantir que cada ação reflita a função do usuário.

## 4. Segurança e permissões

### Objetivo

Proteger o sistema sem bloquear operação local e sem expor itens sensíveis em produção.

### Ações

- Verificar autenticação, sessão, CSRF, rate limit e tokens internos.
- Validar políticas por perfil de acesso e sensibilidade de ações.
- Garantir que segredos e credenciais permaneçam fora do código e do pacote de instalação.
- Reforçar logs de auditoria e rastreabilidade de ações críticas.

## 5. Integrações industriais e infraestrutura

### Objetivo

Manter os pontos de conexão com CLP, câmera, scanner e Node-RED controlados e documentados.

### Ações

- Documentar mapa de I/O e protocolo do CLP.
- Definir canal e protocolo de scanner e câmera.
- Validar fila de comandos e confirmação do gateway industrial.
- Garantir que a operação continue com o sistema local mesmo sem internet.

## 6. Qualidade e validação

### Objetivo

Manter a base estável antes e depois de qualquer ajuste.

### Ações

- Executar `bash testes/qualidade.sh` antes das mudanças relevantes.
- Validar sintaxe PHP e JavaScript.
- Verificar referências órfãs e dependências da interface.
- Confirmar que qualquer alteração de documentação ou nomenclatura não impactou a execução local.

## 7. Prioridade de implementação

### Fase 1: base estável

- convenção de nomenclatura
- documentação de arquitetura
- revisão de permissões e papéis
- validação de qualidade da base

### Fase 2: refinamento operacional

- revisão de fluxo de carregamento
- ajuste de telas e textos
- padronização de estados e mensagens
- auditoria e logs

### Fase 3: produção e operação

- validação de integrações industriais
- planejamento de ambiente físico
- backup, restore e monitoramento
- hardening e implantação final

## Critério de conclusão

O ajuste geral estará concluído quando o sistema estiver:

- coerente em nomenclatura e organização;
- consistente em permissões e traçabilidade;
- validado em sintaxe e estrutura;
- documentado para operação e manutenção;
- pronto para seguir para refinamentos específicos de negócio, integração ou implantação.

# Etapa 25 — revisão de segurança e operação

## Resumo executivo

Foi revisado o fluxo local do Trace para uso em PC industrial. Foram corrigidos um erro que quebrava a atualização de produtos, a navegação que recarregava toda a aplicação, a captura de imagens indevida em leituras válidas e pontos de saída de HTML no interface.

O sistema permanece **local-first**: o banco local é a fonte de operação e a fila de sincronização é assíncrona. Nenhuma tela ou API implementada neste ciclo envia comando elétrico diretamente ao CLP.

## Correções concluídas

### SEC-01 — HTML de dados operacionais escapado

- Severidade: alta
- Local: `interface/js/aplicacao.js`, `interface/js/funcoes/view.js`, `interface/js/telas/monitoramento.js`
- Evidência: valores de produtos, ocorrências, dispositivos e respostas de status eram inseridos em trechos de HTML.
- Correção: aplicação de `esc()` antes da inserção e normalização de valores numéricos.
- Impacto mitigado: reduz risco de XSS armazenado a partir de cadastros, mensagens de periféricos ou respostas da API.

### SEC-02 — permissões de Dala coerentes entre interface e API

- Severidade: média
- Local: `interface/js/telas/dalas.js`, `servidor/api/equipamentos.php`
- Correção: o operador não recebe ações de criação, edição ou exclusão. Exclusão é permitida para os demais perfis, conforme regra de negócio definida; criação e edição ficam restritas a administradores.
- Mitigação: exclusões continuam registradas na auditoria e bloqueadas quando há histórico vinculado.

### OPS-01 — foto somente em incidente

- Severidade: alta para operação/custo de armazenamento
- Local: `servidor/api/sensor_eventos.php`, `servidor/api/leituras.php`, `servidor/api/camera_worker.php`
- Correção: evento do sensor apenas correlaciona o saco. A solicitação de câmera é criada quando o resultado é `SEM_LEITURA` ou `PRODUTO_INCORRETO`; leituras válidas não geram foto. A imagem grava o motivo no banco.

### OPS-02 — navegação sem recarregamento total

- Severidade: média para experiência do operador
- Local: `interface/js/aplicacao.js`
- Correção: menu passa a usar History API; os dados são recarregados somente para a tela aberta. A consulta de conectividade das Dalas ficou restrita às telas de Dala e é paralela.

### BUG-01 — atualização de produto

- Severidade: alta
- Local: `servidor/api/produtos.php`
- Correção: bloco `catch` e resposta da rota `PUT` foram restaurados à estrutura correta.

### OPS-03 — fila, retenção e healthcheck operacional

- Severidade: alta para confiabilidade
- Correção: `fila_sincronizacao` passou a guardar `company_id` obrigatório, com índice e chave estrangeira. Filas legadas sem vínculo inequívoco são preservadas em `fila_sincronizacao_orfas`.
- Correção: `sync-worker` reserva lotes com `SKIP LOCKED`, aplica timeout e backoff, registra `ENVIADO`/`ERRO` e recupera reservas abandonadas. Com `SYNC_REMOTE_BATCH_URL`, o envelope inteiro é enviado em uma requisição; sem essa configuração, o endpoint individual continua compatível.
- Correção: cada auditoria nova compartilha o `event_uuid` da outbox e recebe `delivered_at` na confirmação. A rotina diária mantém imagens e dados operacionais sem remover leituras ou eventos ainda referenciados; auditorias históricas sem vínculo exato ficam preservadas.
- Correção: `/api/health.php` passou a expor fila, idade do evento mais antigo, heartbeats, comandos travados, disco e versão.

### DEPLOY-01 — atualização remota fechada

- Correção: o timer systemd fica inerte sem `/etc/dallogix-trace/enable-auto-update`. O caminho de execução usa `scripts/update_trace.sh`, que valida manifesto assinado, SHA-256, backup do banco e dos arquivos, janela sem carregamento ativo, migrations controladas, healthcheck, rollback completo e marca de falha.

## Controles verificados

- Consultas SQL nas rotas revisadas usam `PDO::prepare()` com parâmetros.
- Sessão com cookie `HttpOnly`, `SameSite=Lax` e cookie `Secure` em produção.
- Token CSRF exigido para alterações em produção.
- CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` e `Permissions-Policy` configurados no Nginx.
- Senhas verificadas com `password_verify()` e regeneração de sessão no login.
- Registro de eventos operacionais e fila de sincronização para rastreabilidade.

## Pendências para homologação comercial

1. Trocar usuários e senhas de demonstração antes da instalação do cliente.
2. Configurar HTTPS, segredos de banco e tokens exclusivos por dispositivo no ambiente de produção.
3. Definir e homologar mapa Modbus e intertravamentos do Delta DVP14SS. O Trace só registra intenções de comando; o adaptador industrial e o CLP devem autorizar a ação física.
4. Executar teste de carga com scanner Elgin EL8600 e câmera IP reais, inclusive offline/reconexão.
5. Confirmar a política de retenção de imagens e dados operacionais no `.env`, mantendo exportação/congelamento quando a auditoria precisar ser preservada por prazo maior.

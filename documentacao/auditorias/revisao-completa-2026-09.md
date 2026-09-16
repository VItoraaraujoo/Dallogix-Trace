# Revisão completa do Dallogix Trace

Data: 01/09/2026

## Escopos revisados

Foram revisados o Prompt Mestre do Dallogix Trace e o escopo consolidado no histórico do projeto, além das regras operacionais, arquitetura, orçamento, implantação e relatórios de auditoria existentes.

## Resultado executivo

O Trace está organizado como uma aplicação local-first: a operação crítica permanece no PC industrial, com banco local e sincronização posterior. O servidor central é complementar para gestão, presença, relatórios e integrações.

O escopo de atualização remota ficou deliberadamente fora desta entrega, conforme decisão do projeto. A instalação inicial continua sendo responsabilidade da equipe técnica.

## Fluxos validados

### Acesso e perfis

- Sessão local com perfis Dallogix, administrador da empresa, supervisor e usuário.
- APIs validam sessão, perfil, empresa vinculada e CSRF em produção.
- Usuário operacional não recebe funções administrativas.

### Empresas e licenciamento

- Master gerencia empresas e licenças.
- Criação de empresa possui proteção contra duplicidade.
- Remoção exige dupla confirmação e respeita as dependências existentes.
- Licenciamento é manual, com bloqueio e desbloqueio, sem vencimento automático.

### Romaneios

- Cadastro manual e importação CSV/PDF.
- Validação de produto, código, quantidade e duplicidade.
- Data anterior ao dia do PC industrial é rejeitada no navegador e na API.
- Data e hora operacional são locais; o servidor registra o recebimento separadamente.

### Carregamento

Fluxo validado:

```text
Romaneio → caminhão → Dala → preparação → sensor → scanner → validação → contagem → finalização
```

Estados inválidos são rejeitados pela regra de negócio. Emergência e desbloqueio possuem autorização, justificativa e auditoria.

### Incidentes

- Sem leitura gera ocorrência e evidência.
- Produto incorreto gera ocorrência e evidência.
- Imagens normais não são persistidas.
- Excesso gera aviso e não é tratado como incidente.
- Imagens de incidentes possuem retenção configurada em 30 dias.

### Integração industrial

- CLP permanece responsável por controle determinístico e intertravamentos.
- O Trace não substitui emergência física ou relé de segurança.
- Solicitações de reversão passam por fila e gateway autenticado.
- O gateway virtual permite teste sem escrita em equipamento físico.
- Mapa de I/O e registradores reais continuam pendentes de confirmação industrial.

### Offline e sincronização

- A operação local não depende da internet.
- Eventos ficam na fila local quando não há conexão.
- Identificadores únicos e reprocessamento evitam duplicidade.
- A sincronização de produção ainda precisa de um contrato central definitivo antes da operação multiempresa em escala.

### Instalação Windows

- O pacote de instalação da equipe técnica é definido em `implantacao/windows/TraceSetup.iss`.
- O instalador reúne os componentes, solicita a identidade da máquina e inicia a preparação.
- O TraceLauncher abre o sistema em modo quiosque.
- O operador final vê somente a tela do Trace.
- Docker, `.env`, CLP, scanner, câmera e demais parâmetros devem ser homologados antes da entrega.

### Backup

- Backup consistente do MySQL usa transação única, rotinas, eventos e triggers.
- Cada backup recebe checksum SHA-256.
- Backups antigos podem ser removidos pela retenção configurada.
- O script de verificação valida checksum e estrutura básica do dump.
- Restauração deve continuar sendo testada em ambiente separado antes de qualquer uso em produção.

## Melhorias aplicadas nesta revisão

1. Instalador Windows estruturado para gerar `TraceSetup.exe`.
2. Configuração interativa da identidade e do modo da máquina.
3. Inclusão dos componentes Docker, Nginx, banco, integrações e scripts no pacote.
4. Backup com checksum.
5. Retenção configurável de backups.
6. Verificação de integridade e estrutura dos dumps.
7. Documentação de instalação e responsabilidades técnicas.
8. Preservação explícita do escopo sem atualização remota.

## Validações executadas

- Sintaxe PHP: aprovada.
- Sintaxe JavaScript: aprovada.
- Sintaxe dos scripts Bash: aprovada.
- JSON das integrações: aprovado.
- Referências órfãs do front: nenhuma encontrada.
- `git diff --check`: aprovado.
- Suíte de qualidade local: aprovada.

O instalador Inno Setup precisa ser compilado e executado em Windows. A integração física com CLP, scanner e câmera não pode ser considerada aprovada apenas por testes locais.

## Pendências controladas

- Compilar e testar o `TraceSetup.exe` em Windows limpo.
- Confirmar mapa de I/O, registradores, protocolo e comportamento seguro do CLP.
- Homologar interface física do scanner.
- Homologar protocolo, IP e latência da câmera.
- Definir contrato definitivo do servidor central e das integrações externas.
- Executar teste de restauração em máquina física de homologação.
- Validar operação com queda de energia e retorno controlado.

## Critério para liberar uma máquina

Uma máquina só deve ser entregue após concluir instalação, configuração, login, romaneio, leitura simulada ou real autorizada, emergência, offline, retorno da conexão, backup e restauração de teste.

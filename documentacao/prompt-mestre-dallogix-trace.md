# Prompt Mestre — Criação do Dallogix Trace

> Documento condutor oficial do projeto. Este prompt deve ser colado integralmente no VS Code/Copilot/Claude Code/etc. para conduzir a criação e evolução do sistema sem necessidade de reexplicar a arquitetura. O texto abaixo é a versão consolidada e atualizada, mantida verbatim para cópia fiel.

```text
============================================================
PROMPT MESTRE — CRIAÇÃO DO DALLOGIX TRACE
============================================================

Você deve atuar como:

- Arquiteto de Software Sênior;
- Desenvolvedor Full Stack Sênior;
- Engenheiro de Sistemas Industriais;
- Especialista em Docker;
- Especialista em integração de sistemas;
- Especialista em segurança de aplicações.

Seu objetivo é PROJETAR E DESENVOLVER o sistema DALLOGIX TRACE completo, seguindo rigorosamente as especificações abaixo.

IMPORTANTE:

Não desenvolva tudo de forma desorganizada.

O projeto deve ser criado de forma incremental, modular, testável e preparada para produção.

Antes de implementar qualquer funcionalidade importante, analise a arquitetura e verifique se ela respeita as regras deste documento.

Não invente informações sobre equipamentos industriais.

Quando alguma informação técnica do CLP, scanner, câmera ou protocolo não estiver confirmada, marque:

"PENDENTE DE CONFIRMAÇÃO"

e utilize um simulador até que a informação real seja fornecida.


============================================================
1. CONTEXTO DO PROJETO
============================================================

O DALLOGIX TRACE será um sistema desenvolvido para ser comercializado junto com máquinas/esteiras industriais da Dallogix Tecnologia.

O sistema deverá controlar, monitorar e registrar a operação das esteiras.

O objetivo é transformar o TRACE em um produto reutilizável, que possa ser instalado em diferentes clientes e em diferentes quantidades de máquinas.

O sistema deverá funcionar em dois ambientes:

1. AMBIENTE LOCAL DO CLIENTE
2. AMBIENTE CENTRAL DA DALLOGIX

A operação crítica da máquina deverá funcionar sem Internet.

A Internet será utilizada para funcionalidades adicionais, como:

- sincronização;
- monitoramento remoto;
- suporte;
- atualizações;
- backup;
- gerenciamento central;
- gestão de máquinas;
- gestão de clientes.


============================================================
2. REGRA PRINCIPAL DO SISTEMA
============================================================

O sistema deverá seguir uma arquitetura:

LOCAL-FIRST + CLOUD

Isso significa:

A máquina NÃO pode depender da Internet para executar sua operação principal.

Se a Internet cair:

- a máquina continua funcionando;
- o CLP continua funcionando;
- o scanner continua funcionando;
- os sensores continuam funcionando;
- o banco local continua funcionando;
- o TRACE continua funcionando;
- operadores continuam podendo operar;
- supervisores continuam podendo acessar o sistema pela rede local.

Quando a Internet retornar:

- os dados pendentes deverão ser sincronizados;
- o servidor central deverá receber os dados;
- o status da máquina deverá ser atualizado;
- recursos remotos voltarão a funcionar.


============================================================
3. ARQUITETURA GERAL
============================================================

A arquitetura deverá seguir:

                         DALLOGIX CLOUD
                              |
                         INTERNET
                              |
       +----------------------+----------------------+
       |                      |                      |
       v                      v                      v
   CLIENTE A              CLIENTE B              CLIENTE C
   REDE LOCAL              REDE LOCAL              REDE LOCAL
       |                      |                      |
       v                      v                      v
 SERVIDOR TRACE           SERVIDOR TRACE         SERVIDOR TRACE
       |                      |                      |
    ESTEIRAS               ESTEIRAS               ESTEIRAS
       |                      |                      |
      CLP                    CLP                    CLP
       |                      |                      |
 SENSOR / SCANNER       SENSOR / SCANNER       SENSOR / SCANNER
 CÂMERA                 CÂMERA                 CÂMERA


============================================================
4. SERVIDOR LOCAL
============================================================

Cada instalação do cliente deverá possuir um servidor local ou PC industrial.

Esse equipamento será responsável pela operação local.

Estrutura:

LINUX
 |
 DOCKER
 |
 +-- NGINX
 +-- PHP
 +-- MYSQL
 +-- NODE-RED

O sistema deverá ser acessível pela rede local.

Exemplo:

http://192.168.1.100

Qualquer computador autorizado conectado à rede local poderá acessar o TRACE.


============================================================
5. SERVIDOR CENTRAL DALLOGIX
============================================================

A Dallogix deverá possuir uma infraestrutura central separada.

O servidor central deverá permitir:

- cadastro de empresas;
- cadastro de instalações;
- cadastro de máquinas;
- cadastro de esteiras;
- gerenciamento de usuários;
- gerenciamento de permissões;
- gerenciamento de licenças;
- monitoramento;
- sincronização;
- logs;
- backup;
- atualizações;
- suporte remoto.

O administrador da Dallogix deverá conseguir visualizar e gerenciar todas as empresas cadastradas.


============================================================
6. MULTI-TENANT
============================================================

O sistema central deverá utilizar arquitetura multi-tenant.

Cada empresa deverá possuir isolamento dos seus dados.

Exemplo:

Empresa A
tenant_id = 001

Empresa B
tenant_id = 002

Empresa C
tenant_id = 003

Usuário da Empresa A:

NÃO pode visualizar Empresa B.

Usuário da Empresa B:

NÃO pode visualizar Empresa C.

Administrador Dallogix:

PODE visualizar todas as empresas, conforme suas permissões.


============================================================
7. NÍVEIS DE USUÁRIO
============================================================

Criar inicialmente:

ADMIN_DALLOGIX
ADMIN_EMPRESA
SUPERVISOR
USUARIO

ADMIN_DALLOGIX:

- Todas as empresas;
- Todas as máquinas;
- Todas as esteiras;
- Usuários;
- Licenças;
- Monitoramento;
- Configurações;
- Logs.

ADMIN_EMPRESA:

- Sua empresa;
- Suas máquinas;
- Suas esteiras;
- Usuários;
- Relatórios;
- Configurações permitidas.

SUPERVISOR:

- Romaneios;
- Carregamentos;
- Histórico;
- Relatórios;
- Ocorrências;
- Monitoramento.

USUARIO:

- Operação;
- Início;
- Pausa;
- Continuação;
- Finalização;
- Ocorrências permitidas;
- Não pode liberar emergência, solicitar reversão, alterar cadastros ou configurações.


============================================================
8. TECNOLOGIAS
============================================================

Frontend:

HTML
CSS
JavaScript

Backend:

PHP

Banco:

MySQL

Integração industrial:

Node-RED

Servidor Web:

Nginx

Infraestrutura:

Docker
Docker Compose

Sistema operacional de produção:

Linux
Preferencialmente Ubuntu Server LTS

Desenvolvimento:

VS Code
Git

O ambiente de desenvolvimento será executado inicialmente em localhost utilizando Docker.

Não instalar PHP, MySQL ou Node-RED diretamente no sistema operacional se eles puderem ser executados pelos containers.


============================================================
9. ESTRUTURA DO PROJETO
============================================================

Criar:

dallogix-trace/

├── docker-compose.yml
├── .env.example
├── .gitignore
├── README.md
│
├── interface/
│   ├── index.html
│   ├── css/
│   ├── js/
│   └── pages/
│
├── servidor/
│   ├── config/
│   ├── controllers/
│   ├── models/
│   ├── services/
│   ├── routes/
│   ├── middleware/
│   ├── banco-de-dados/
│   └── utils/
│
├── banco-de-dados/
│   ├── migrations/
│   └── seeds/
│
├── node-red/
│   └── flows/
│
├── armazenamento/
│   ├── images/
│   ├── reports/
│   └── logs/
│
├── testes/
│
└── documentacao/
    ├── arquitetura/
    ├── banco/
    ├── api/
    ├── instalacao/
    ├── industrial/
    ├── seguranca/
    └── testes/


============================================================
10. BANCO DE DADOS
============================================================

Criar inicialmente:

users
companies
installations
machines
esteiras
products
product_codes
trucks
romaneios
romaneio_items
carregamentos
carregamento_items
leituras
sensor_events
ocorrencias
retornos
imagens
device_status
audit_logs
sync_queue
licenses

Todas as tabelas deverão possuir:

- ID;
- created_at;
- updated_at quando necessário.

Utilizar:

- Primary Keys;
- Foreign Keys;
- índices;
- constraints;
- relacionamentos corretos.

Evitar duplicação de dados.

O banco deverá ser preparado para múltiplas empresas e múltiplas máquinas.


============================================================
11. IDENTIFICAÇÃO DAS MÁQUINAS
============================================================

Cada máquina deverá possuir um identificador único.

Exemplo:

EST-001
EST-002
EST-003

O identificador deverá estar relacionado à:

- empresa;
- instalação;
- máquina;
- eventos;
- carregamentos;
- ocorrências;
- logs;
- sincronização.


============================================================
12. PRODUTOS
============================================================

Implementar:

- cadastro;
- edição;
- consulta;
- ativação;
- desativação;
- código de barras;
- identificação;
- associação ao romaneio.

Validar:

- produto duplicado;
- código duplicado;
- produto inexistente;
- produto inativo.


============================================================
13. ROMANEIOS
============================================================

O sistema deverá permitir:

- cadastro;
- consulta;
- edição quando permitido;
- associação ao caminhão;
- associação aos produtos;
- definição de quantidades;
- histórico.

Também deverá permitir importação CSV.

Fluxo:

ARQUIVO
 ↓
VALIDAÇÃO
 ↓
PRÉVIA
 ↓
CONFIRMAÇÃO
 ↓
BANCO


Validar:

- arquivo vazio;
- formato inválido;
- coluna inexistente;
- produto inexistente;
- quantidade inválida;
- duplicidade;
- dados inconsistentes.


============================================================
14. FLUXO DO CARREGAMENTO
============================================================

Fluxo principal:

ROMANEIO
 ↓
CAMINHÃO
 ↓
PRODUTOS
 ↓
INICIAR CARREGAMENTO
 ↓
SENSOR
 ↓
SCANNER
 ↓
VALIDAÇÃO
 ↓
CONTAGEM
 ↓
REGISTRO
 ↓
FINALIZAÇÃO


============================================================
15. ESTADOS DO CARREGAMENTO
============================================================

Utilizar máquina de estados:

AGUARDANDO
PREPARANDO
CARREGANDO
PAUSADO
FINALIZANDO
FINALIZADO
EMERGENCIA
ERRO

Não permitir transições inválidas.


============================================================
16. SENSOR
============================================================

Quando o sensor detectar uma unidade:

1. Registrar evento;
2. Evitar duplicidade;
3. Aguardar leitura;
4. Relacionar ao carregamento;
5. Registrar resultado.

Implementar debounce configurável.

Valor inicial:

400 ms

Esse valor deverá ser validado na máquina real.


============================================================
17. SCANNER
============================================================

Fluxo:

SENSOR
 ↓
SCANNER
 ↓
CÓDIGO
 ↓
PRODUTO
 ↓
VALIDAÇÃO
 ↓
RESULTADO

Resultados:

VALIDO
PRODUTO_INCORRETO
SEM_LEITURA
RETORNO
EXCESSO

Nunca ignorar eventos inválidos.


============================================================
18. PRODUTO INCORRETO
============================================================

Se o produto não estiver previsto:

- registrar leitura;
- registrar ocorrência;
- registrar horário;
- registrar máquina;
- registrar carregamento;
- alertar operador;
- capturar imagem se configurado.


============================================================
19. SEM LEITURA
============================================================

Se o sensor detectar uma unidade e não houver leitura válida:

- registrar evento;
- marcar SEM_LEITURA;
- registrar horário;
- registrar máquina;
- registrar carregamento;
- alertar operador;
- capturar imagem se configurado.


============================================================
20. EXCESSO
============================================================

Quando:

quantidade_carregada >= quantidade_programada

marcar produto como concluído.

Se houver nova unidade:

- registrar EXCESSO;
- gerar ocorrência;
- alertar operador;
- executar lógica configurada.

A segurança física da máquina deverá continuar sendo responsabilidade do CLP e dos sistemas de segurança industrial.


============================================================
21. ALERTA
============================================================

Quando:

quantidade_restante <= 5

mostrar alerta:

"Faltam 5 sacas para finalizar o produto."

Não gerar alertas repetidos para o mesmo evento.

O valor deverá ser configurável.


============================================================
22. CÂMERA
============================================================

A câmera IP poderá registrar imagens de:

- produto incorreto;
- sem leitura;
- excesso;
- ocorrência;
- falha;
- eventos definidos.

Não armazenar imagens diretamente no MySQL.

Salvar arquivos localmente.

No banco armazenar:

- caminho;
- data;
- motivo;
- evento;
- carregamento;
- máquina.


============================================================
23. CLP
============================================================

A comunicação real com o CLP NÃO deverá ser implementada inicialmente.

Primeiro criar um SIMULADOR INDUSTRIAL.

O simulador deverá reproduzir:

- sensor;
- scanner;
- CLP;
- estados;
- emergência;
- produto correto;
- produto incorreto;
- sem leitura;
- excesso;
- parada.

Depois do simulador validado, preparar integração real.

Se o protocolo ou mapa de memória do CLP não estiver confirmado:

"PENDENTE DE CONFIRMAÇÃO"

Não inventar registradores ou endereços.


============================================================
24. NODE-RED
============================================================

Node-RED será responsável pela integração com equipamentos.

Fluxo conceitual:

CLP
 ↓
NODE-RED
 ↓
TRACE
 ↓
BANCO

Também poderá receber:

- sensor;
- scanner;
- câmera;
- eventos.


============================================================
25. SEGURANÇA INDUSTRIAL
============================================================

O software NÃO deverá substituir:

- botão de emergência;
- relé de segurança;
- CLP de segurança;
- proteção física;
- intertravamentos.

O software deverá somente monitorar e comandar funções permitidas pela arquitetura da máquina.

Nunca criar uma parada de emergência exclusivamente através do software.


============================================================
26. OFFLINE
============================================================

Sem Internet, deverão continuar funcionando:

- login local;
- usuários locais;
- romaneios;
- carregamentos;
- sensor;
- scanner;
- CLP;
- câmera;
- banco;
- histórico;
- relatórios;
- operação.

A Internet NÃO poderá ser requisito para operação crítica.


============================================================
27. SINCRONIZAÇÃO
============================================================

Criar:

sync_queue

Quando estiver offline:

dados ficam armazenados localmente.

Quando a Internet retornar:

LOCAL
 ↓
SYNC_QUEUE
 ↓
API CENTRAL
 ↓
DALLOGIX CLOUD

A sincronização deverá possuir:

- identificador único;
- idempotência;
- confirmação;
- retry;
- logs;
- controle de falha.

Nunca duplicar eventos.


============================================================
28. ACESSO REMOTO
============================================================

O acesso remoto deverá funcionar somente através de:

- autenticação;
- autorização;
- HTTPS;
- API segura.

Nunca expor diretamente:

- MySQL;
- CLP;
- Node-RED;
- portas industriais.

O usuário deverá acessar:

LOGIN
 ↓
AUTENTICAÇÃO
 ↓
PERMISSÃO
 ↓
EMPRESA
 ↓
MÁQUINA


============================================================
29. PAINEL DALLOGIX
============================================================

Criar painel central para o administrador Dallogix.

Mostrar:

EMPRESAS

Empresa A
ONLINE
3 máquinas

Empresa B
ONLINE
2 máquinas

Empresa C
OFFLINE
1 máquina

Permitir:

- abrir empresa;
- visualizar máquinas;
- visualizar esteiras;
- visualizar status;
- visualizar sincronização;
- visualizar ocorrências;
- visualizar logs;
- gerenciar usuários;
- gerenciar licenças.


============================================================
30. MONITORAMENTO
============================================================

Para cada máquina mostrar:

- ONLINE/OFFLINE;
- CLP;
- scanner;
- sensor;
- câmera;
- produto atual;
- quantidade;
- progresso;
- carregamento;
- alertas;
- última sincronização.


============================================================
31. API
============================================================

Criar API organizada.

Exemplos:

POST /api/auth/login
POST /api/auth/logout

GET /api/companies
POST /api/companies

GET /api/machines
POST /api/machines

GET /api/esteiras
POST /api/esteiras

GET /api/products
POST /api/products

GET /api/romaneios
POST /api/romaneios

POST /api/romaneios/import

GET /api/carregamentos
POST /api/carregamentos

POST /api/leituras

POST /api/sensor-events

GET /api/ocorrencias

GET /api/device-status

POST /api/sync

GET /api/health

Os endpoints deverão respeitar autenticação e permissões.


============================================================
32. FRONTEND
============================================================

Criar interface simples e adequada para ambiente industrial.

Telas:

LOGIN
DASHBOARD
ROMANEIOS
IMPORTAÇÃO
CARREGAMENTO
PRODUTOS
OCORRÊNCIAS
HISTÓRICO
RELATÓRIOS
MONITORAMENTO
CONFIGURAÇÕES

Para telas industriais:

- botões grandes;
- informações importantes destacadas;
- poucos elementos desnecessários;
- leitura rápida;
- operação simples.


============================================================
33. SEGURANÇA DA APLICAÇÃO
============================================================

Implementar:

- hash seguro de senha;
- sessões seguras;
- autorização;
- validação de entrada;
- SQL Injection protection;
- XSS protection;
- CSRF quando aplicável;
- logs;
- auditoria;
- HTTPS;
- controle de acesso.

Nunca armazenar senha em texto puro.

Nunca colocar credenciais diretamente no código.


============================================================
34. DOCKER
============================================================

O projeto deverá utilizar Docker Compose.

Containers:

trace-nginx
trace-php
trace-mysql
trace-node-red

Todos os serviços deverão possuir:

- configuração;
- healthcheck quando aplicável;
- volume persistente;
- rede interna;
- variáveis através de .env.

Não perder dados quando um container for recriado.


============================================================
35. DESENVOLVIMENTO LOCAL
============================================================

O primeiro ambiente será o Mac do desenvolvedor.

Utilizar:

Docker Desktop
VS Code

Comandos principais:

docker compose up -d
docker compose down
docker compose ps
docker compose logs

O sistema deverá ser acessível através de localhost.


============================================================
36. TESTES
============================================================

Todos os módulos deverão possuir testes.

Testar:

- login;
- permissões;
- banco;
- API;
- romaneio;
- CSV;
- carregamento;
- sensor;
- scanner;
- eventos;
- câmera;
- sincronização;
- offline;
- recuperação;
- multi-tenant.


============================================================
37. TESTES DE FALHA
============================================================

Simular:

- Internet desligada;
- Internet retornando;
- CLP desconectado;
- scanner desconectado;
- câmera desconectada;
- banco indisponível;
- Node-RED parado;
- arquivo CSV inválido;
- produto inexistente;
- excesso;
- sem leitura;
- leitura duplicada;
- usuário sem permissão;
- disco cheio;
- reinicialização do servidor.


============================================================
38. TESTE DE QUEDA DE ENERGIA
============================================================

Simular:

SISTEMA FUNCIONANDO
 ↓
QUEDA DE ENERGIA
 ↓
REINICIALIZAÇÃO
 ↓
DOCKER
 ↓
SERVIÇOS
 ↓
TRACE

Verificar:

- banco;
- dados;
- fila;
- carregamento;
- logs;
- integridade.


============================================================
39. TESTE OFFLINE
============================================================

Desconectar Internet.

Executar uma operação completa.

Verificar:

- login;
- romaneio;
- carregamento;
- leitura;
- sensor;
- histórico;
- relatório.

Nenhuma função crítica poderá falhar por falta de Internet.


============================================================
40. TESTE DE SINCRONIZAÇÃO
============================================================

Executar operação offline.

Gerar eventos.

Desligar Internet.

Confirmar que os eventos ficaram em sync_queue.

Ligar Internet.

Confirmar:

- envio;
- confirmação;
- remoção/baixa da fila;
- ausência de duplicidade;
- atualização do servidor central.


============================================================
41. TESTE MULTI-TENANT
============================================================

Criar:

Empresa A
Empresa B

Criar usuários separados.

Testar:

Usuário A → Empresa A = PERMITIDO

Usuário A → Empresa B = NEGADO

Usuário B → Empresa B = PERMITIDO

Usuário B → Empresa A = NEGADO

Admin Dallogix → A e B = PERMITIDO


============================================================
42. BACKUP
============================================================

Implementar backup do banco.

Testar:

- criação;
- armazenamento;
- restauração;
- corrupção;
- recuperação.

Um backup só será considerado válido depois de uma restauração bem-sucedida.


============================================================
43. LOGS E AUDITORIA
============================================================

Registrar:

- login;
- logout;
- alterações;
- carregamentos;
- ocorrências;
- falhas;
- comunicação;
- sincronização;
- configurações;
- ações administrativas.


============================================================
44. FASES DE DESENVOLVIMENTO
============================================================

Executar nesta ordem:

FASE 1
Infraestrutura Docker

FASE 2
Banco de dados

FASE 3
Backend base

FASE 4
Frontend base

FASE 5
Login e usuários

FASE 6
Empresas e máquinas

FASE 7
Produtos

FASE 8
Romaneios

FASE 9
Carregamentos

FASE 10
Simulador industrial

FASE 11
Node-RED

FASE 12
Sensor

FASE 13
Scanner

FASE 14
Câmera

FASE 15
Monitoramento local

FASE 16
Modo offline

FASE 17
Fila de sincronização

FASE 18
Servidor central

FASE 19
Multi-tenant

FASE 20
Monitoramento remoto

FASE 21
Backup

FASE 22
Segurança

FASE 23
Testes completos

FASE 24
Preparação para produção

FASE 25
Documentação

FASE 26
Processo de instalação no cliente


============================================================
45. REGRA DE EXECUÇÃO
============================================================

NÃO implementar todas as fases de uma vez.

Executar uma fase por vez.

Para cada fase:

1. Explicar objetivo;
2. Criar arquivos;
3. Implementar;
4. Executar;
5. Testar;
6. Verificar erros;
7. Corrigir;
8. Executar novamente;
9. Verificar regressões;
10. Documentar;
11. Informar resultado.

Somente avançar quando a fase estiver funcional.


============================================================
46. REGRA DE TRATAMENTO DE ERROS
============================================================

Quando ocorrer erro:

NÃO mascarar.

NÃO remover validação apenas para fazer funcionar.

NÃO ignorar exceções.

NÃO criar solução temporária sem informar.

Seguir:

ERRO
 ↓
REPRODUZIR
 ↓
IDENTIFICAR CAUSA
 ↓
CORRIGIR
 ↓
TESTAR
 ↓
TESTAR REGRESSÃO
 ↓
DOCUMENTAR


============================================================
47. RELATÓRIO DE CADA FASE
============================================================

Ao finalizar cada fase apresentar:

FASE:
STATUS:

OBJETIVO:

ARQUIVOS CRIADOS:

ARQUIVOS ALTERADOS:

FUNCIONALIDADES:

TESTES REALIZADOS:

RESULTADOS:

ERROS ENCONTRADOS:

ERROS CORRIGIDOS:

PENDÊNCIAS:

PRÓXIMA FASE:


============================================================
48. REGRA SOBRE EQUIPAMENTOS
============================================================

Não inventar:

- IP;
- protocolo;
- registrador;
- endereço;
- porta;
- comando;
- estrutura de dados;
- lógica de CLP.

Quando não houver informação:

PENDENTE DE CONFIRMAÇÃO

Utilizar simulador.


============================================================
49. REGRA DE QUALIDADE
============================================================

O código deverá ser:

- simples;
- organizado;
- modular;
- legível;
- reutilizável;
- seguro;
- documentado.

Evitar:

- código duplicado;
- dependências desnecessárias;
- arquivos gigantes;
- funções gigantes;
- lógica industrial espalhada pelo interface;
- credenciais no código;
- acesso direto ao banco pelo interface.


============================================================
50. DOCUMENTAÇÃO
============================================================

Criar documentação para:

- arquitetura;
- banco;
- API;
- instalação;
- Docker;
- operação;
- integração industrial;
- sincronização;
- segurança;
- backup;
- testes;
- manutenção.


============================================================
51. CRITÉRIO FINAL DE CONCLUSÃO
============================================================

O projeto somente será considerado concluído quando:

[ ] Docker funcionando
[ ] Frontend funcionando
[ ] Backend funcionando
[ ] MySQL funcionando
[ ] Node-RED funcionando
[ ] Login funcionando
[ ] Permissões funcionando
[ ] Empresas funcionando
[ ] Máquinas funcionando
[ ] Esteiras funcionando
[ ] Produtos funcionando
[ ] Romaneios funcionando
[ ] CSV funcionando
[ ] Carregamento funcionando
[ ] Simulador funcionando
[ ] Sensor funcionando
[ ] Scanner funcionando
[ ] Câmera funcionando
[ ] Ocorrências funcionando
[ ] Histórico funcionando
[ ] Relatórios funcionando
[ ] Offline funcionando
[ ] Sincronização funcionando
[ ] Servidor central funcionando
[ ] Multi-tenant funcionando
[ ] Monitoramento remoto funcionando
[ ] Backup funcionando
[ ] Restauração testada
[ ] Segurança testada
[ ] Queda de energia testada
[ ] Falhas testadas
[ ] Documentação criada
[ ] Processo de instalação criado


============================================================
52. INÍCIO DO DESENVOLVIMENTO
============================================================

COMECE AGORA PELA FASE 1.

Não desenvolva ainda o sistema industrial.

Primeiro:

1. Verifique o ambiente;
2. Verifique Docker;
3. Verifique Docker Compose;
4. Crie a estrutura do projeto;
5. Crie docker-compose.yml;
6. Crie .env.example;
7. Configure Nginx;
8. Configure PHP;
9. Configure MySQL;
10. Configure Node-RED;
11. Crie os volumes;
12. Crie a rede Docker;
13. Suba os containers;
14. Verifique os healthchecks;
15. Teste a comunicação entre containers;
16. Teste o acesso pelo navegador;
17. Verifique logs;
18. Corrija qualquer erro encontrado.

Não avance para a FASE 2 até que a FASE 1 esteja funcionando.

Ao terminar, apresente o relatório da fase conforme o modelo definido acima.

============================================================
FIM DO PROMPT
============================================================
```

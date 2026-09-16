# Dallogix Trace — Histórico, requisitos e resumo do desenvolvimento

> Documento consolidado das conversas, decisões e alterações realizadas no projeto.

## 1. Objetivo do projeto

O Dallogix Trace é um sistema industrial para controlar e acompanhar o carregamento de sacos em esteiras, integrando:

- CLP Delta DVP14SS;
- scanner Elgin EL8600;
- câmera IP;
- computador industrial com operação offline;
- banco de dados local e sincronização com banco/cloud;
- dashboard empresarial para acompanhamento das máquinas;
- integração futura com ERP, WMS, TMS e outros sistemas.

O sistema deve registrar o romaneio, validar produtos, contar volumes, identificar incidentes, permitir acompanhamento operacional e preservar os dados mesmo quando não houver internet.

## 2. Requisitos consolidados do escopo

### Operação

- O operador seleciona ou importa um romaneio.
- O romaneio contém: romaneio, data, placa, produto e quantidade.
- O saco passa pela esteira e é detectado pelo sensor.
- O CLP registra os sinais da máquina e comunica os eventos ao sistema.
- O scanner permanece sempre ativo para leitura do código de barras.
- O sistema valida o código lido contra o produto esperado.
- O sistema contabiliza os sacos aprovados.
- Ao atingir a quantidade planejada, a máquina deve avisar que o carregamento foi concluído.
- Não é necessária aprovação para finalizar o carregamento.

### Falha de leitura

- Se o scanner não ler o código, o saco não retorna.
- A câmera IP deve registrar a imagem imediatamente, sem atraso perceptível.
- O sistema cria uma ocorrência de incidente com a imagem associada.
- A imagem fica disponível para auditoria.
- Não haverá segunda tentativa física, porque o saco já passou pelo sensor.

### Produto incorreto e excesso

- Produto incorreto gera incidente e registro fotográfico.
- Excesso não deve ser tratado como incidente.
- Ao atingir a quantidade do romaneio, a máquina deve emitir um aviso de carregamento completo.

### Emergência e desbloqueio

- Usuários autorizados a liberar emergência: todos, exceto operador.
- Deve existir botão de desbloqueio da máquina.
- Toda ação de emergência, desbloqueio ou retorno deve ser auditada.

### Retenção de imagens

- Imagens de incidentes devem ser mantidas por 30 dias.

### Operação offline

- O computador industrial deve continuar registrando dados sem internet.
- A operação local deve continuar disponível.
- Quando a conexão voltar, os dados pendentes devem ser sincronizados com o banco/cloud.
- A sincronização deve ser idempotente, evitando duplicidade.

## 3. Equipamentos definidos

### CLP

- Modelo informado: Delta DVP14SS.
- A comunicação prevista no projeto é Ethernet/Modbus TCP.
- Caso a variante física não possua Ethernet integrada, será necessário utilizar o módulo/interface compatível ou adotar Modbus RTU como fallback.
- O CLP controla a parte física da máquina: sensores, atuadores, esteira e intertravamentos.
- O software Trace lê estados e envia comandos operacionais autorizados ao CLP.

### Scanner

- Modelo: Elgin EL8600.
- Interfaces possíveis: USB ou RS232.
- Operação: scanner sempre ativo.
- O modo de conexão definitivo depende da instalação física e do driver escolhido no computador industrial.

### Câmera

- Tipo: câmera IP/TCP.
- Uso principal: registrar rapidamente a imagem de sacos sem leitura ou com produto incorreto.
- A imagem deve ser capturada somente para incidentes, conforme a regra definida.

### Computador industrial

- Executa a interface do Trace.
- Mantém o banco local e a fila offline.
- Faz a comunicação com scanner, câmera e gateway industrial.
- Continua operando sem internet.

### Tablet

- Android de aproximadamente 10 polegadas.
- Uso destinado ao monitoramento e acompanhamento.
- Não deve controlar funções críticas da máquina.

## 4. Arquitetura definida

```text
Scanner / sensores / câmera
          │
          ▼
CLP Delta + gateway industrial / Node-RED
          │ Ethernet / Modbus TCP
          ▼
Trace no computador industrial
          │
          ├── Banco local MySQL
          ├── Fila offline e sincronização
          └── Interface web operacional
                    │
                    ▼
              API/cloud
                    │
                    ├── Dashboard empresarial
                    ├── Integrações ERP/WMS/TMS
                    └── n8n para automações
```

### Responsabilidade do CLP

O CLP é responsável pelo controle determinístico e seguro da máquina. O software não deve substituir os intertravamentos do CLP nem assumir funções de emergência de segurança.

### Responsabilidade do Trace

O Trace recebe sinais, interpreta eventos, valida códigos, registra contagens, captura incidentes, exibe a operação e envia comandos operacionais permitidos.

### Responsabilidade do Node-RED

O Node-RED pode atuar no computador industrial como middleware para Modbus, scanner, câmera e filas locais de baixa latência.

### Responsabilidade do n8n

O n8n deve ficar na camada de integração/cloud, executando:

- sincronização com sistemas externos;
- webhooks;
- notificações;
- rotinas de ERP/WMS/TMS;
- exportações e relatórios;
- retries e automações administrativas.

O n8n não deve controlar diretamente motor, emergência ou intertravamentos da máquina, nem acessar o MySQL diretamente sem uma API controlada.

## 5. Estrutura do projeto

```text
Dallogix Trace/
├── servidor/
│   ├── config/
│   └── api/              # endpoints PHP da API
├── banco-de-dados/
│   └── migrations/          # migrations do banco
├── interface/
│   ├── css/                 # tema e estilos
│   ├── js/
│   │   ├── classes/         # classes e estado da aplicação
│   │   └── telas/           # telas separadas
│   └── index.html
├── integracoes/
│   └── industrial/          # integração CLP/gateway/mapa de registradores
├── testes/                   # testes automatizados por etapa
├── documentacao/
│   ├── arquitetura/
│   ├── pendencias/
│   └── seguranca/
├── docker-compose.yml
└── .env.example
```

## 6. Funcionalidades implementadas

### Interface web

- Login e sessão.
- Tema claro baseado na referência analisada.
- Menu modular por área.
- Tela inicial/Home.
- Dashboard empresarial.
- Romaneios do dia.
- Importação de CSV.
- Divisão por caminhão.
- Tela de trabalho.
- Alertas.
- Ocorrências.
- Resumo final.
- Histórico.
- Monitor de tablet.
- Cadastro de produtos.
- Emergência.
- Desbloqueio da máquina.
- Configurações do sistema.

### Dashboard empresarial

A tela Dashboard apresenta:

- total de máquinas;
- máquinas online;
- máquinas sem sinal;
- situação do carregamento;
- lista de equipamentos;
- nome e identificador;
- IP do CLP;
- protocolo;
- porta do CLP;
- porta externa;
- status;
- último sinal recebido.

### Configuração de máquinas/Dalas

Foi criada a estrutura para cadastrar:

- identificador da máquina;
- nome da máquina;
- IP do CLP;
- porta do CLP;
- porta externa;
- protocolo;
- status.

### Gateway e configurações

Foi criada a configuração para:

- IP público do gateway;
- URL remota de sincronização;
- mapeamento dos campos de PDF/romaneio;
- cadastro das máquinas.

## 7. Banco de dados e API

Foram adicionadas migrations e endpoints para:

- equipamentos/Dalas;
- configurações da empresa;
- romaneios;
- produtos;
- carregamentos;
- leituras;
- eventos de sensor;
- ocorrências;
- desbloqueio;
- emergência;
- sincronização;
- estado operacional.

A API usa PHP e o banco local usa MySQL no ambiente Docker.

## 8. Segurança aplicada

Foram aplicados:

- cabeçalhos de segurança HTTP;
- proteção de sessão;
- cookies seguros em produção;
- proteção CSRF em operações mutáveis;
- limitação de tentativas de login em produção;
- remoção de detalhes sensíveis das respostas de health check;
- validação de caminhos de imagens;
- bloqueio de tokens padrão de câmera em produção;
- limite de tamanho e quantidade de linhas no CSV;
- portas Docker limitadas ao localhost por padrão;
- CSP e Permissions-Policy no Nginx;
- auditoria de operações administrativas.

Antes da produção ainda devem ser configurados secrets reais, HTTPS, VPN, usuários individuais, backup, monitoramento e política definitiva de retenção.

## 9. Docker e execução local

Serviços previstos:

- MySQL;
- PHP;
- Nginx;
- Node-RED.

Comandos principais:

```bash
docker compose up -d
docker compose ps
```

Aplicação local:

```text
http://localhost:8080
```

O erro anterior do teste ocorreu porque o comando `rg` não estava instalado no Mac. Isso foi tratado no ambiente de execução dos testes e não representa falha da aplicação.

## 10. Testes executados

Os testes automatizados das etapas 1 a 21 foram executados com sucesso após as últimas alterações.

Também foram validados:

- sintaxe dos arquivos PHP;
- configuração do Docker Compose;
- estrutura do interface;
- login;
- operação offline;
- câmera e incidente de não leitura;
- finalização;
- simulador;
- desbloqueio;
- configurações;
- dashboard;
- ausência de erros de whitespace no diff.

## 11. Git e versão atual

Branch principal utilizada:

```text
master
```

Último commit registrado:

```text
971afef feat: adiciona dashboard empresarial em tema claro
```

O trabalho foi enviado para o repositório remoto configurado:

```text
git@github.com:VItoraaraujoo/Dallogix-Trace.git
```

## 12. Pendências técnicas para conexão física

Antes de ligar a máquina real, ainda é necessário validar em campo:

1. variante exata do Delta DVP14SS e módulo de comunicação;
2. IP, máscara e porta do CLP;
3. mapa final de entradas, saídas e registradores;
4. lógica de handshake entre CLP e Trace;
5. sinal do sensor de passagem;
6. comando de máquina pronta, carregamento completo e emergência;
7. conexão física do EL8600 em USB ou RS232;
8. endereço, usuário e senha da câmera IP;
9. testes em bancada antes da esteira produtiva;
10. validação com eletricista/automação responsável pela máquina.

Nenhum teste de conexão física deve ser feito diretamente em produção sem confirmar o mapa de sinais e os intertravamentos.

## 13. Próximas etapas recomendadas

### Etapa A — bancada

- instalar o CLP e o gateway em bancada;
- configurar IPs;
- testar leitura de entradas;
- testar comandos simulados;
- confirmar registradores;
- testar scanner e câmera.

### Etapa B — integração real controlada

- conectar o Trace ao CLP em rede isolada;
- testar somente leitura;
- comparar sinais com o software do fabricante;
- habilitar comandos não críticos;
- validar ocorrências e fotos.

### Etapa C — piloto

- executar com poucos romaneios;
- comparar contagem física e contagem do sistema;
- validar operação sem internet;
- validar sincronização;
- acompanhar logs e alertas.

### Etapa D — produção

- configurar cloud, backups e HTTPS;
- liberar perfis de usuário;
- configurar retenção de imagens por 30 dias;
- integrar sistemas externos via API/n8n;
- documentar o procedimento operacional.

## 14. Decisões importantes registradas

- Máquina e esteira são tratados como um conjunto operacional no sistema.
- O saco não retorna quando o código não é lido.
- A foto é capturada imediatamente após a falha de leitura.
- Apenas incidentes devem gerar imagem armazenada.
- Excesso gera aviso de quantidade concluída, não incidente.
- O CLP mantém o controle físico e seguro da máquina.
- O Trace faz supervisão, validação, registro e comandos permitidos.
- O sistema deve funcionar offline no computador industrial.
- O cloud poderá iniciar na AWS e futuramente migrar para servidor próprio.
- O dashboard empresarial usa tema claro.

## 15. Estado atual

O protótipo funcional local está estruturado e executável via Docker, com interface modular, API PHP, banco MySQL, dashboard empresarial, configurações de máquinas e base para integração industrial.

O ponto que depende de informações externas é a parametrização final do CLP físico e dos equipamentos reais. O software já possui a estrutura necessária para receber essa configuração, mas o mapa definitivo de sinais precisa ser confirmado na máquina.

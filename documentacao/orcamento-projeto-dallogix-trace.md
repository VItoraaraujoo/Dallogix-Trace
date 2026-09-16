# Orçamento preliminar — Dallogix Trace

**Data-base:** 31/08/2026  
**Versão:** 0.1 — estimativa para validação comercial  
**Moeda:** Real brasileiro (R$)

## 1. Objetivo

Implantar o Dallogix Trace para controle e rastreabilidade do carregamento de sacarias em uma esteira industrial, com operação local-first, leitura por scanner, tratamento de incidentes com câmera, integração com CLP, auditoria e sincronização remota.

Este documento é uma estimativa preliminar. O preço fechado depende da confirmação do mapa de I/O, variante do CLP, interface do scanner, protocolo da câmera e arquitetura cloud.

## 2. Situação atual considerada

O projeto já possui uma base funcional relevante:

- interface operacional local, dashboard, romaneios, importação, carregamento, alertas, ocorrências e histórico;
- APIs PHP, autenticação, perfis de acesso, auditoria e regras de operação;
- banco local com 18 migrations;
- fila offline e estrutura de sincronização;
- gateway industrial e fluxos Node-RED em modo seguro/simulado;
- retenção de imagens, relatórios e rotinas de backup/restauração;
- suíte de qualidade/regressão com 40 scripts e teste Modbus virtual.

O sistema ainda não deve ser considerado pronto para produção: a ligação física ao CLP, a homologação dos dispositivos reais, o servidor central/cloud e alguns testes de campo continuam pendentes.

## 3. Estimativa do saldo para piloto em uma Dala

Premissa de referência: **R$ 200/hora técnica**, equipe com desenvolvimento de software, integração industrial e implantação. As horas abaixo são faixas de planejamento.

| Etapa | Escopo | Horas | Estimativa |
| --- | --- | ---: | ---: |
| 1. Levantamento e especificação de campo | confirmar CLP, I/O, Ladder, rede, scanner, câmera e critérios de aceite | 24–32 | R$ 4.800–6.400 |
| 2. Integração real do CLP | adaptador Modbus, estados, sensores, comandos autorizados, perda de comunicação e intertravamentos | 60–90 | R$ 12.000–18.000 |
| 3. Scanner e câmera | driver/interface do EL8600, buffer de leitura, captura de incidente e testes de latência | 32–48 | R$ 6.400–9.600 |
| 4. Produção, sincronização e segurança | configuração de servidor, segredos, HTTPS, backup, observabilidade, sincronização e retenção | 60–90 | R$ 12.000–18.000 |
| 5. Bancada e homologação | testes funcionais, offline/reconexão, carga, falhas de leitura, emergência e reversão | 48–72 | R$ 9.600–14.400 |
| 6. Implantação e treinamento | instalação no PC industrial, configuração da Dala, treinamento e acompanhamento inicial | 40–64 | R$ 8.000–12.800 |
| 7. Gestão, documentação e aceite | plano de implantação, evidências, manual e encerramento | 24–36 | R$ 4.800–7.200 |
| **Subtotal** |  | **288–432** | **R$ 57.600–86.400** |
| Reserva técnica de risco (15%) | variações de protocolo, cabeamento lógico, ajustes de campo e retrabalho de homologação |  | **R$ 8.640–12.960** |
| **Faixa recomendada para o piloto** |  |  | **R$ 66.240–99.360** |

### Valor comercial sugerido

Para apresentação ao cliente, uma proposta objetiva pode ser estruturada em **R$ 79.800,00** para o piloto de uma Dala, condicionada às premissas deste documento.

Esse valor inclui a reserva técnica e deve ser dividido por marcos de entrega, por exemplo:

- 30% na contratação e levantamento;
- 30% após integração em bancada;
- 25% na instalação e início do piloto;
- 15% após aceite do piloto.

## 4. Valor de referência do software já desenvolvido

Para fins de composição comercial e reconhecimento do ativo existente, a base atual pode ser apresentada separadamente como **R$ 80.000 a R$ 120.000** em valor de desenvolvimento, sujeito a auditoria técnica detalhada.

Esse valor não substitui o custo de homologação industrial. Código, simuladores e testes locais reduzem o risco e o prazo, mas não comprovam a operação segura com o CLP, scanner e câmera reais.

## 5. Itens opcionais

| Item | Faixa indicativa |
| --- | ---: |
| Servidor central/cloud, painel multiempresa e sincronização validada | R$ 24.000–40.000 |
| Integração com cada ERP/WMS/TMS | R$ 8.000–16.000 por conector |
| Replicação para cada Dala adicional, com validação em campo | R$ 3.200–6.400 |
| Suporte evolutivo mensal | R$ 3.000–8.000/mês |

## 6. Não inclusos

- CLP, módulo Ethernet/RS-485, scanner, câmera, PC industrial, tablet, cabeamento, rede e nobreak;
- alterações no programa Ladder ou na instalação elétrica da máquina;
- deslocamento, hospedagem e despesas de viagem;
- mensalidade de AWS, domínio, certificados, armazenamento, backups externos e serviços de terceiros;
- licenças de ERP/WMS/TMS, Node-RED, n8n ou ferramentas externas;
- adequações de segurança de máquina que sejam responsabilidade da instalação industrial;
- operação assistida além do período de piloto definido no contrato.

## 7. Premissas e riscos comerciais

- O cliente fornecerá o mapa oficial de I/O, programa Ladder ou documentação equivalente, IP, porta, unit ID e comportamento seguro do CLP.
- A câmera terá protocolo acessível e posição adequada para capturar o incidente sem atraso perceptível.
- O scanner será disponibilizado com a interface física escolhida e formato de leitura documentado.
- O piloto será realizado em uma única Dala e em uma única instalação.
- O software não substitui intertravamentos, emergência ou lógica determinística do CLP.
- Mudanças de escopo, novos equipamentos ou integrações serão orçados como aditivo.

## 8. Critério de aceite do piloto

O piloto será considerado aceito após demonstrar, em ambiente controlado:

1. operação normal com romaneio e contagem;
2. produto incorreto e ausência de leitura com ocorrência e imagem;
3. operação offline e sincronização idempotente após reconexão;
4. bloqueio seguro quando o CLP ficar indisponível;
5. auditoria de emergência, desbloqueio, retorno e encerramento;
6. backup e restauração testados;
7. treinamento dos usuários indicados pelo cliente.

## 9. Próximos dados para fechar o preço

1. confirmar se o objetivo é somente o piloto ou também o servidor central/cloud;
2. enviar modelo/sufixo exato do Delta DVP14SS e mapa de registradores;
3. confirmar USB ou RS-232 no Elgin EL8600;
4. informar modelo, protocolo e IP da câmera;
5. definir prazo desejado, local da instalação e quantidade de Dalas;
6. definir valor da hora técnica, margem e regime tributário da proposta.

# Organização do front-end — Dallogix Trace

Este documento registra a organização adotada para o front conforme o escopo oficial do projeto.

## Navegação principal

O menu permanente fica reduzido aos módulos que representam áreas estáveis do sistema:

### Operação

- **Dashboard / Operação por Dala**: visão somente informativa das Dalas, romaneios em andamento, estado, carga e progresso. Cada card possui acesso às estatísticas detalhadas da Dala.
- **Romaneios**: romaneios do dia, consulta, visualização e abertura de novo romaneio.

### Cadastros

- **Produtos**: cadastro, edição e lista de produtos.
- **Dalas**: cadastro, edição, visualização e conectividade das esteiras.

### Sistema

- **Configurações**: rede do cliente, visão das Dalas, importação de parâmetros e gerenciamento de usuários.

## Fluxos internos da operação

Estas telas não ficam expostas como itens permanentes do menu. Elas são abertas a partir do fluxo do romaneio ou da operação selecionada:

| Tela do escopo | Implementação atual | Entrada prevista |
| --- | --- | --- |
| HOME | Dashboard / Operação por Dala | Menu Dashboard |
| ROMANEIOS DO DIA | Romaneiros | Menu Romaneios |
| IMPORTAR CSV | Importação dentro de Novo romaneio | Novo romaneio |
| DIVISÃO POR CAMINHÃO | Preparar carregamento | Visualização do romaneio |
| TELA DE TRABALHO | Tela de Trabalho | Operação selecionada |
| ALERTAS | Alertas e sincronização | Estado da operação |
| OCORRÊNCIAS | Ocorrências | Operação em andamento |
| RESUMO FINAL | Resumo final | Encerramento da operação |
| HISTÓRICO | Histórico operacional | Acompanhamento da operação |
| CADASTRO DE PRODUTO | Formulário dentro de Produtos | Menu Produtos |
| LISTA DE PRODUTOS | Lista dentro de Produtos | Menu Produtos |
| EMERGÊNCIA | Tela de emergência | Estado crítico da operação |

## Itens mantidos por necessidade do sistema

**Dalas** e **Configurações** não aparecem como telas independentes na lista resumida do escopo, mas permanecem no menu porque são necessários para cadastrar a esteira, configurar a rede do cliente e acompanhar sua conectividade.

As telas de empresas, usuários e administração global continuam disponíveis conforme o perfil de acesso, mas não aparecem no menu operacional da empresa.

## Decisões aplicadas

- A Home legada foi removida; o Dashboard é a única visão geral da operação.
- A entrada padrão de usuários da empresa agora é o Dashboard / Operação por Dala.
- O menu principal não exibe fluxos intermediários nem ações industriais diretamente.
- O Dashboard não controla máquinas; seus cards são somente informativos.
- O controle de carregamento fica no fluxo do Romaneio e na Tela de Trabalho.
- A visualização detalhada da Dala exibe suas estatísticas operacionais.
- A organização visual permanece no tema claro solicitado.
- Nenhuma ação de produção, exclusão, retomada ou ligação de máquina foi executada.

## Próxima etapa

Revisar cada fluxo interno, nesta ordem: Romaneio → Importação CSV → Divisão por caminhão → Tela de trabalho → Alertas/Ocorrências → Resumo final/Histórico → Emergência.

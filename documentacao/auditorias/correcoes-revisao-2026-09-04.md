# Correções verificadas em 04/09/2026

- Navegação executa somente as consultas da página solicitada e valida ID antes de iniciá-las. Leituras pendentes aguardam o carregamento ativo.
- Administrador de empresa não pode modificar contas administrativas por envio direto à API.
- Gatilhos recusam ações pertencentes a outra Dala; resolução durante leitura também filtra empresa e equipamento.
- Criação de ação mantém o ID da ação após gravar auditoria e fila.
- Status da sincronização não apresenta conexão confirmada só porque há uma URL.
- Reserva do retry usa atualização condicional e verifica o resultado antes de enviar. Intervalo available_at passa a ser respeitado.

Testes isolados executam a função real de navegação e controladores PHP com banco simulado; cobrem página única, ausência de ID, sequência de leitura, conta administrativa, ação alheia, ID de criação e reserva recusada sem envio externo. Foram adicionados ao workflow de qualidade. Não substituem testes com MySQL real e concorrência entre processos.

Continuam pendentes os demais achados do relatório de análise, incluindo contagem por produto/transações, MFA/revogação de sessão, mascaramento de logs, worker automático, recuperação de reservas, isolamento de gateway e integração física. Nenhum mapa de CLP foi liberado nesta alteração.

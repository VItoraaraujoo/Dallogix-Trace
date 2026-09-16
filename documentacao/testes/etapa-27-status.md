# Status dos testes — Etapa 27

## STATUS

IMPLEMENTADA — fluxo de liberação de emergência corrigido.

## CORREÇÕES

- A tela `emergency.html` agora consulta o carregamento ativo.
- O botão `Desbloquear máquina` aparece para perfis autorizados.
- O desbloqueio recarrega o estado persistido após a resposta da API.
- A tela informa que a confirmação dos intertravamentos físicos continua sendo responsabilidade do CLP.

## TESTE

`bash testes/etapa27.sh`

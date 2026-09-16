# Teste de bancada do CLP

Este procedimento deve ser executado antes de conectar a esteira em produção.

## 1. Preparação

- Confirmar o sufixo completo do Delta DVP14SS.
- Separar o programa Ladder e o mapa elétrico.
- Confirmar tensão da fonte, aterramento e proteção do painel.
- Conectar o PC industrial e o módulo Ethernet do CLP à mesma rede industrial (cabo Ethernet/switch industrial).
- Usar USB–RS485 somente se a variante instalada não disponibilizar Ethernet; nesse caso, confirmar a configuração Modbus RTU.
- Conectar o Elgin EL8600 ao PC por USB ou RS-232.
- Não conectar o motor da esteira durante o primeiro teste.

## 2. Comunicação sem comandos

1. Configurar IP fixo, porta Modbus TCP e rota entre o PC industrial e o CLP. Em RS-485 alternativo, configurar velocidade, paridade, bits, stop bits e endereço.
2. Iniciar somente a leitura dos registradores de diagnóstico.
3. Confirmar heartbeat do CLP no painel do Dallogix.
4. Desconectar e reconectar o cabo para validar recuperação automática.
5. Desligar a rede externa e confirmar que os eventos continuam no banco local.

## 3. Scanner contínuo

1. Iniciar o serviço do scanner.
2. Passar um código válido e confirmar que ele aparece no buffer.
3. Passar um código incorreto e confirmar a classificação.
4. Acionar o sensor do CLP e confirmar que a leitura mais próxima é associada ao evento.
5. Acionar o sensor sem apresentar código e confirmar `SEM_LEITURA` e captura imediata.
6. Confirmar que não existe segunda tentativa nem retorno do saco.

## 4. Saídas controladas

Só depois dos testes anteriores:

1. testar comando de iniciar com o motor desacoplado;
2. testar pausa;
3. testar alarme;
4. testar emergência física;
5. testar desbloqueio somente com a emergência liberada e os intertravamentos satisfeitos.

O Dallogix não deve forçar uma saída de segurança. O CLP e o circuito elétrico devem negar o comando quando houver condição insegura.

## 5. Critérios de aprovação

- Nenhum evento duplicado após reconexão.
- Nenhuma leitura associada ao saco errado.
- Falha sem leitura registrada sem atraso intencional da câmera.
- Operação local preservada sem internet.
- Comando de desbloqueio auditado.
- Parada de emergência funcionando independentemente do PC.

O mapa de registradores só pode sair de `CONFIRMAR` após os testes de I/O e a validação do responsável elétrico.

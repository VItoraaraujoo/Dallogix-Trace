# Desbloqueio da máquina

Quando o carregamento está em `EMERGENCIA`, a tela **Emergência** e a **Tela de trabalho** mostram o botão **Desbloquear máquina**.

O comando é permitido para `ADMIN_EMPRESA` e `SUPERVISOR`. O perfil `USUARIO` não pode liberar a emergência; o Administrador Dallogix acompanha a operação, sem executar comandos da empresa.

Ao confirmar o desbloqueio:

1. o carregamento retorna para `PREPARANDO`;
2. o comando `DESBLOQUEAR_MAQUINA` é auditado;
3. o evento entra na fila local de sincronização;
4. o futuro adaptador do CLP poderá consumir o comando e liberar a saída física conforme os intertravamentos.

Endpoint local:

```http
POST /api/desbloquear_maquina.php
Content-Type: application/json

{"carregamento_id": 1}
```

O endpoint não substitui a emergência elétrica ou os intertravamentos do CLP. A liberação física deve ser autorizada pelo CLP e pelos dispositivos de segurança.

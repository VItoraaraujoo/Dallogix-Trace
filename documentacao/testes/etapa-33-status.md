# Etapa 33 — RBAC com quatro perfis

Perfis oficiais:

1. `ADMIN_DALLOGIX`: visão global de empresas e acompanhamento de suas operações.
2. `ADMIN_EMPRESA`: administração integral da própria empresa.
3. `SUPERVISOR`: condução e supervisão operacional da própria empresa.
4. `USUARIO`: operação diária, sem cadastros, reversão ou liberação de emergência.

Compatibilidade: registros antigos `MANUTENCAO` foram migrados para `SUPERVISOR` e `OPERADOR` para `USUARIO`.

```bash
bash testes/etapa33.sh
```

# Etapa 34 — Importação CSV aprimorada

O modelo disponível em `interface/assets/modelo-romaneio.csv` usa ponto e vírgula e suporta:

- obrigatórios: `romaneio`, `data`, `placa`, `produto`, `quantidade`;
- opcionais: `motorista`, `expedidor`;
- data em `DD/MM/AAAA` ou `AAAA-MM-DD`;
- soma automática de produto repetido no mesmo romaneio e caminhão.

```bash
bash testes/etapa34.sh
```

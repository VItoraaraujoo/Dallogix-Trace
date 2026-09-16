# Simulador local de CLP

O projeto possui um simulador que usa as APIs reais, sem criar um caminho paralelo de teste.

## Leitura válida

```bash
bash scripts/simulate_clp.sh 7898250782592
```

## Falha de leitura

```bash
bash scripts/simulate_clp.sh SEM_LEITURA
```

O simulador executa:

1. mudança do estado para `CARREGANDO`;
2. evento do sensor;
3. leitura do scanner;
4. ocorrência e pedido imediato de captura de câmera somente quando houver `SEM_LEITURA` ou produto incorreto;
5. fila local para sincronização quando houver falha.

Variáveis opcionais:

```bash
TRACE_BASE_URL=http://localhost:8080
TRACE_LOADING_ID=1
TRACE_EQUIPMENT_ID=1
```

Esse simulador não substitui o teste com CLP real; ele valida o fluxo de software até os adaptadores físicos serem configurados.

# Implantação em servidor físico

O ambiente local integra o Trace com o `modbus-virtual` e com as abas de teste do Node-RED. A simulação valida o transporte TCP e a fila local, mas não representa o mapa do CLP da fábrica.

## Pré-requisitos

- Linux 64-bit com Docker Engine e Docker Compose Plugin;
- IP fixo ou reserva DHCP na rede industrial;
- armazenamento persistente para MySQL e `armazenamento/`;
- acesso restrito à rede local, sem publicar MySQL, Node-RED ou Modbus na Internet;
- backup externo e relógio sincronizado.

## Configuração

1. Copie `.env.example` para `.env`.
2. Defina `APP_ENV=production`, `SESSION_SECURE=true` e segredos aleatórios para banco, câmera, CLP e sincronização.
3. Não use valores `change-me-*` em produção; os endpoints internos recusam tokens padrão nesse ambiente.
4. Cadastre cada Dala com o IP e a porta confirmados do CLP. O fluxo descobre automaticamente todas as Dalas, sem limite fixo.
5. Mantenha `modbus-virtual` apenas para homologação. Em campo, use o endereço do CLP depois de confirmar variante, unit ID, registradores e intertravamentos.

Antes de subir o ambiente físico, exporte o `.env` e execute `bash scripts/check_production_env.sh`. A verificação falha se houver segredo padrão, URL sem HTTPS, sessão insegura ou sincronização remota sem token.

O administrador Master controla manualmente o status de cada empresa, podendo bloqueá-la ou desbloqueá-la. Não existe vencimento, cobrança automática, gateway de pagamento, cartão, boleto ou PIX no sistema. Quando a empresa está bloqueada, o Trace impede novas preparações, início de carregamento e comandos operacionais. A carga já iniciada permanece disponível para tratamento seguro e não é desligada pelo sistema.

## Homologação antes da troca

Confirme em bancada o mapa de I/O, IP, porta TCP, unit ID, estado, emergência, fins de curso, perda de comunicação e comandos de pausa/reversão. Até essa homologação, o gateway responde `REJEITADO` e não executa escrita física.

## Operação

```bash
docker compose up -d --build
bash testes/qualidade.sh
bash testes/regressao_completa.sh
python3 scripts/test_modbus_virtual.py
bash scripts/backup_db.sh armazenamento/backups
```

Copie os backups para armazenamento externo. Restauração em produção exige `TRACE_ALLOW_RESTORE=1` definido conscientemente e janela de manutenção. O Node-RED deve apontar para a API local e receber tokens somente por variáveis de ambiente.

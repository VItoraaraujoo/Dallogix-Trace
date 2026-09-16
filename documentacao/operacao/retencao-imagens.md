# Retenção de imagens

- Prazo definido: **30 dias**.
- Somente imagens de incidentes são persistidas.
- O script `scripts/prune_images.php` remove registros e arquivos locais expirados.
- O serviço `image-retention` executa o script automaticamente ao iniciar e depois a cada 24 horas.
- No mesmo ciclo, `scripts/prune_operational_data.php` retém leituras, eventos de sensor, auditoria com entrega confirmada, fila enviada e logs de erro. Registros ainda referenciados ou sem confirmação de sincronização são mantidos.
- Para instalação fora do Docker, execute o script diariamente por cron, launchd ou agendador do servidor.

Exemplo no container PHP:

```bash
docker compose exec php php /var/www/scripts/prune_images.php
```

Para produção, o job deve usar credenciais do banco por ambiente e backup antes da limpeza. Ajuste os prazos com `READING_RETENTION_DAYS`, `SENSOR_EVENT_RETENTION_DAYS`, `AUDIT_RETENTION_DAYS`, `SYNC_SENT_RETENTION_DAYS` e `ERROR_LOG_RETENTION_DAYS`.

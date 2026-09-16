# Checklist de go-live

## 1. Preparação do ambiente
- [ ] Criar `.env` real de produção a partir de `.env.production.example`.
- [ ] Definir senhas fortes para `MYSQL_PASSWORD` e `MYSQL_ROOT_PASSWORD`.
- [ ] Provisionar um token distinto para cada CLP, câmera e outro dispositivo técnico.
- [ ] Confirmar `APP_ENV=production`.
- [ ] Confirmar `SESSION_SECURE=true`.
- [ ] Confirmar `APP_URL` com HTTPS.
- [ ] Confirmar `WEB_BIND_ADDRESS` e `BIND_ADDRESS` restritos.

## 2. Infraestrutura e rede
- [ ] Validar acesso local do PC industrial.
- [ ] Confirmar isolamento das portas internas do MySQL, Node-RED, Modbus e interface web.
- [ ] Confirmar que o servidor central é o ponto de coordenação e não um ambiente local ativo.
- [ ] Validar a rede do CLP, scanner e câmera.

## 3. Banco e backup
- [ ] Validar conexão com MySQL em produção.
- [ ] Executar backup completo antes do rollout.
- [ ] Validar a integridade do backup.
- [ ] Definir retenção e rotina de backup automático.
- [ ] Confirmar `SYNC_REMOTE_URL` ou `SYNC_REMOTE_BATCH_URL` com contrato central homologado e `SYNC_REMOTE_TOKEN` exclusivo.
- [ ] Definir `READING_RETENTION_DAYS`, `SENSOR_EVENT_RETENTION_DAYS`, `AUDIT_RETENTION_DAYS`, `SYNC_SENT_RETENTION_DAYS` e `ERROR_LOG_RETENTION_DAYS`.
- [ ] Confirmar que o `sync-worker` e o `image-retention` estão ativos e que a fila não possui erros sem responsável.
- [ ] Testar restauração de backup em ambiente controlado.

## 4. Segurança da aplicação
- [ ] Executar `bash scripts/check_production_env.sh`.
- [ ] Executar `php scripts/auditoria_controle_acesso.php`.
- [ ] Executar `bash scripts/api_smoke_test.sh`.
- [ ] Confirmar headers HTTP, sessão segura e proteção CSRF.
- [ ] Confirmar rate limiting persistido de login e tokens de dispositivo válidos.

## 5. Fluxos críticos do negócio
- [ ] Login válido e inválido testado.
- [ ] Empresa criada e consultada.
- [ ] Usuário criado com perfil permitido.
- [ ] Romaneio criado e validado.
- [ ] Romaneio atualizado e cancelado quando permitido.
- [ ] Carregamento e leitura de produto testados.
- [ ] Ocorrências e alertas avaliados.

## 6. Integridade industrial
- [ ] Validar CLP em bancada.
- [ ] Validar scanner em bancada.
- [ ] Validar câmera e armazenamento de imagens.
- [ ] Validar I/O e Ladder do equipamento.
- [ ] Validar alarme e status de dispositivos.

## 7. Deploy e rollback
- [ ] Rodar `docker compose config`.
- [ ] Rodar `docker compose up -d --build` em ambiente controlado.
- [ ] Validar healthchecks e logs.
- [ ] Conferir `/api/health.php`: versão, fila, heartbeats, comandos travados e espaço livre.
- [ ] Confirmar que os serviços sobem corretamente.
- [ ] Preparar rollback com backup e imagem anterior.
- [ ] Testar rollback restaurando também o dump SQL da versão anterior.
- [ ] Manter o timer systemd de atualização automática desabilitado, salvo aprovação formal e arquivo de habilitação criado na janela de manutenção.

## 8. Aprovação final
- [ ] Execução da suíte de validação concluída.
- [ ] `bash testes/qualidade.sh` OK.
- [ ] Revisão final do responsável técnico.
- [ ] Autorização para go-live registrada.

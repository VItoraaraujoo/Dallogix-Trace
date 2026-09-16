# Plano de entrega para produção

## Objetivo
Preparar o sistema Dallogix Trace para operação real em ambiente industrial com foco em segurança, confiabilidade, testes e rollout controlado.

## Prioridade 1 — Ambiente de produção
- Criar `.env` de produção real a partir de `.env.production.example`.
- Definir senhas fortes e tokens internos reais.
- Garantir `APP_ENV=production`, `SESSION_SECURE=true` e `APP_URL` com HTTPS.
- Manter `WEB_BIND_ADDRESS` e `BIND_ADDRESS` restritos.
- Validar com `bash scripts/check_production_env.sh`.

## Prioridade 2 — Banco e backup
- Confirmar conexão com MySQL em ambiente real.
- Executar backup consistente antes de qualquer rollover.
- Validar backup com `bash scripts/verify_backup.sh`.
- Definir política de retenção de arquivos e backup automático.

## Prioridade 3 — Segurança e camada de API
- Confirmar que o bootstrap continua ativo e com headers seguros.
- Validar login, sessão, CSRF e rate limiting.
- Reexecutar `php scripts/auditoria_controle_acesso.php`.
- Reexecutar `bash scripts/api_smoke_test.sh`.

## Prioridade 4 — Fluxos de negócio críticos
- Testar login real com usuário da empresa.
- Criar e consultar empresa.
- Criar usuário com perfil permitido.
- Criar romaneio e validar itens.
- Atualizar e cancelar romaneio em status permitido.
- Validar carregamento e leitura de produto.
- Validar ocorrências e alertas.

## Prioridade 5 — Integrações fisicas
- Validar CLP Delta DVP14SS no ambiente real.
- Validar scanner Elgin EL8600 em USB ou serial.
- Validar câmera IP e armazenamento local.
- Validar I/O e Ladder no PC industrial.
- Confirmar que o servidor central permanece apenas como orquestrador e não como estação local ativa.

## Prioridade 6 — Deploy controlado
- Rodar `docker compose config`.
- Rodar `docker compose up -d --build` em ambiente controlado.
- Validar container healthchecks e logs.
- Confirmar acessos e portas abertas.
- Preparar rollback com backup e versão anterior.

## Prioridade 7 — Validação final antes do go-live
- `bash scripts/check_production_env.sh`
- `bash scripts/api_smoke_test.sh`
- `php scripts/auditoria_controle_acesso.php`
- `bash testes/qualidade.sh`
- Verificação manual do fluxo principal do operador
- Aprovação final por responsável técnico

## Critérios de liberação
O sistema pode seguir para produção quando:
- `.env` de produção real foi gerado e validado.
- backups e restauração foram testados.
- autenticação e autorização passaram nos testes.
- fluxo principal do usuário foi validado em ambiente real.
- integrações industriais foram validadas no equipamento real.
- rollback foi praticado ou documentado.

## Risco residual
A maior parte do risco residual não está no código, e sim na integração com hardware, redes e ambientes físicos industriais. Isso deve ser validado em bancada antes da implantação final.

# Checklist de produção

## 1. Ambiente e segredos
- Copiar o arquivo `.env.production.example` para `.env`.
- Substituir todos os valores padrão por segredos reais e únicos.
- Garantir que `APP_ENV=production` e `SESSION_SECURE=true`.
- Confirmar que `APP_URL` usa HTTPS e não HTTP.
- Manter `WEB_BIND_ADDRESS=127.0.0.1` e `BIND_ADDRESS=127.0.0.1` para ambientes de quiosque ou rede local restrita.

## 2. Rede e exposição
- Não expor MySQL, Node-RED, Modbus ou interface local para internet.
- Usar apenas servidores e endpoints internos ou de borda controlados.
- Validar que o mesmo ambiente não tenha portas públicas desnecessárias.

## 3. Segurança da aplicação
- Rodar `bash scripts/check_production_env.sh` antes do deploy.
- Garantir que o bootstrap e a API usem CSRF, sessão segura e headers rígidos.
- Verificar que tokens internos de câmera e CLP foram trocados.
- Confirmar autenticação por sessão com cookie `HttpOnly` e `SameSite=Lax`.

## 4. Banco de dados
- Usar senhas fortes para `MYSQL_PASSWORD` e `MYSQL_ROOT_PASSWORD`.
- Executar backup consistente antes de qualquer atualização.
- Validar integridade do backup com o script de verificação antes de restauração.

## 5. Deploy e rollback
- Executar `docker compose config` para validar o compose final.
- Iniciar com `docker compose up -d --build` em ambiente controlado.
- Confirmar healthcheck e status dos serviços.
- Preparar rollback com backup do banco e imagem/manifesto de atualização.

## 6. Validação final
- Rodar `bash testes/qualidade.sh`.
- Rodar `bash scripts/check_production_env.sh` com o `.env` de produção final.
- Confirmar que a API responde corretamente e que o front-end acessa a aplicação esperada.

## 7. Continuidade operacional
- Registrar usuário, chave, backup e versão em documentação de operação.
- Reforçar auditoria e logs de erro para rastreabilidade.
- Configurar retenção de imagens, backup e revisão periódica de segredos.

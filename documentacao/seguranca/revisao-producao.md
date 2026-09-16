# Revisão de segurança para produção

## Correções aplicadas

- cookies de sessão passam a usar `Secure` em produção;
- headers `X-Content-Type-Options`, `X-Frame-Options` e `Referrer-Policy`;
- token CSRF para mutações quando `APP_ENV=production`;
- healthcheck não retorna detalhes do erro do banco;
- limite de CSV de 5 MB e 10.000 linhas;
- portas do Docker vinculadas a `127.0.0.1` por padrão;
- token padrão da câmera recusado em produção;
- senha não fica mais preenchida na tela de login;
- Content Security Policy no Nginx.
- rate limit de login no PHP para produção, por identidade e endereço de origem.
- modo estrito de sessão, cookies `SameSite=Strict` e revalidação do usuário ativo em cada requisição autenticada;
- limites de requisição e conexões no Nginx, limite de corpo de 6 MB e rate limit específico para login;
- headers de segurança também aplicados aos arquivos estáticos;
- validação de produção carrega o `.env` antes de verificar `APP_ENV`, segredos e HTTPS.

## Obrigatório antes da publicação externa

- trocar todos os segredos e credenciais locais;
- usar HTTPS no proxy de produção;
- configurar bloqueio progressivo e armazenamento compartilhado do rate limit quando houver múltiplas réplicas;
- validar caminhos de imagens contra diretório permitido;
- restringir acesso ao Node-RED e ao banco por rede/firewall;
- adicionar backup, logs centralizados e alertas;
- configurar CORS somente para domínios autorizados;
- revisar permissões por empresa e por equipamento;
- executar teste de invasão e análise de dependências.
- colocar o serviço web público atrás de WAF/proxy com proteção DDoS; os limites do Nginx são apenas uma camada local;
- definir tokens internos por instalação/equipamento antes de operar múltiplas empresas no mesmo ambiente.

Somente o modo explicitamente local (`APP_ENV=local`) mantém os testes sem exigir CSRF e rate limit. Ambientes ausentes, de homologação ou produção usam proteção por padrão; a produção deve sempre usar `APP_ENV=production` e segredos fornecidos pelo ambiente de execução.

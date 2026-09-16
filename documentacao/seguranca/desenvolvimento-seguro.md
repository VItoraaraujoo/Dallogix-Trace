# Desenvolvimento seguro

## Controles aplicados

- Sessões com cookie `HttpOnly`, `SameSite=Strict` e modo estrito.
- CSRF nas operações que alteram dados.
- Autorização por perfil e isolamento por empresa em todas as APIs operacionais.
- Consultas SQL parametrizadas.
- Escape de conteúdo dinâmico no frontend.
- Tokens internos somente por variável de ambiente para gateway, CLP e câmera.
- URL e credenciais de sincronização somente no backend.
- Logs operacionais sem senhas, tokens ou conteúdo de formulários.
- Respostas de API sem cache e rejeição de JSON inválido ou acima de 1 MB.
- Limitação de tentativas de login, requisições e conexões no Nginx.
- Cabeçalhos CSP, anti-frame, anti-MIME sniffing, referrer e permissions policy.
- Backup com checksum, verificação e bloqueio de restauração acidental em produção.

## Verificações automáticas

O workflow `Security checks` executa:

1. testes de sintaxe e referências do projeto;
2. varredura de credenciais privadas e segredos fixos de alto risco;
3. análise CodeQL do JavaScript;
4. geração de SBOM em formato SPDX.

Qualquer alerta deve ser revisado antes do merge ou deploy.

## Controles de infraestrutura

MFA, WAF, SIEM, proteção DDoS e cofre de segredos não são implementados apenas pelo código da aplicação. Antes da produção, devem ser configurados no provedor de infraestrutura, sem colocar credenciais no repositório.

O backup deve ser executado por agendamento do servidor, armazenado fora do volume principal e restaurado periodicamente em ambiente de teste. O resultado da restauração deve ser registrado nos logs operacionais.

## Regra de revisão

Código produzido com auxílio de IA é considerado não confiável até passar por revisão humana, testes de autorização, validação de entradas e verificação do workflow de segurança.

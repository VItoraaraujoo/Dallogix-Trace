# Plano de hardening do Dallogix Trace

## Medidas já aplicadas

- Sessão com `HttpOnly`, `SameSite=Strict`, `Secure` configurável e expiração
  por inatividade de 30 minutos.
- Regeneração de sessão no login e invalidação no logout.
- CSRF para mutações em produção, RBAC por empresa e tokens internos para
  gateway industrial e câmera.
- CSP, `X-Frame-Options`, `nosniff`, políticas de origem cruzada e rate limit
  no Nginx e no login.
- Consultas parametrizadas, limites de upload, MIME real, proteção contra
  conclusão duplicada de capturas e fila autenticada para comandos físicos.
- Backup com checksum, validação de completude antes da restauração e
  retenção configurável.
- SAST CodeQL para JavaScript, SBOM e varredura de segredos no código e no
  histórico do GitHub Actions.

## Ativação obrigatória antes de exposição pública

1. Use o domínio `santocloud.com.br` e instale o certificado no servidor de
   origem. O modelo está em `nginx/https.conf.example`. No Cloudflare, use
   SSL/TLS em modo **Full (strict)**, crie os registros `A` para o IP público
   real do servidor e redirecione HTTP para HTTPS. Só então defina
   `SESSION_SECURE=true`.
2. Mantenha o Nginx, MySQL, Node-RED e Modbus presos à rede local. O acesso
   remoto deve passar por VPN ou bastion; não publique portas de OT.
3. Troque todos os valores `change-me-*` e valide o ambiente com
   `scripts/check_production_env.sh`.
4. Configure MFA no provedor de identidade ou no bastion para acessos
   administrativos. O login local atual não possui um segundo fator.
5. Envie `logs_auditoria`, `logs_erros`, tentativas de login e comandos do CLP
   para armazenamento central com retenção e controle de alteração.
6. Mantenha uma cópia de backup fora do host, de preferência offline ou
   imutável, e faça restauração de teste em periodicidade definida.

## Resposta a incidentes

Em suspeita de invasão: bloquear o acesso remoto, preservar os logs, revogar
tokens internos, colocar o gateway em modo seguro, impedir novos comandos,
fazer backup forense do banco e só liberar a operação após validação do CLP e
dos intertravamentos por responsável de automação.

O procedimento deve ser ensaiado antes da primeira operação pública e revisado
após qualquer incidente ou mudança de rede.

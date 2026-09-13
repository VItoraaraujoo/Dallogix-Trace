# Teste local com configuração de produção

Execute `python3 scripts/production_test.py` na raiz do projeto e abra
<https://localhost:8443>. O endereço <http://localhost:18080> redireciona para HTTPS.
O certificado é autossinado para homologação e vence em 30 dias; o navegador
pode mostrar um aviso. Não é o certificado para publicação em domínio público.

O ambiente usa `APP_ENV=production`, cookies seguros e CSRF ativo, banco e
armazenamento separados, senhas aleatórias e portas web restritas ao computador.
MySQL não publica porta. Não há Node-RED nem gateway ligado a equipamento físico.
O ambiente atual da porta 8080 e seu banco não são modificados pelo preparador.

Os quatro acessos de teste estão em `armazenamento/producao-teste/acessos.json`.
As senhas padrão documentadas para desenvolvimento não funcionam nesse ambiente.
Esse arquivo, as chaves e `.env.production-test` ficam fora do Git; não compartilhe.
A preparação reaproveita o banco e registra as migrations aplicadas. Não substitua
`.env.production-test` enquanto o volume existir: mudar o arquivo não altera as
senhas já cadastradas no MySQL.

## Verificação

```bash
bash testes/qualidade.sh
python3 testes/production_smoke.py
python3 testes/production_backup.py
```

O smoke test verifica o certificado TLS, saúde, autenticação, rejeição da senha
padrão, atributos de cookies, proteção CSRF, criação/leitura de produto, logout
e restrição do operador. Cria um produto identificado como teste por execução.
O teste de backup valida checksum e restaura em um banco temporário, comparando
as contagens de todas as tabelas. O banco temporário é removido ao final.
A suíte legada `regressao_completa.sh` usa senhas padrão e não envia CSRF;
não deve ser executada contra este ambiente de produção.

Para parar preservando os dados:

```bash
docker compose --env-file .env.production-test -f docker-compose.production-test.yml down
```

Para iniciar novamente, execute o preparador. Não use `down -v` se quiser manter
os dados de homologação.

## Servidor real

Para uma instalação nova, `bash scripts/setup_production_env.sh /caminho/novo.env https://seu-dominio`
gera segredos e valida o arquivo, recusando sobrescrever configurações existentes.
O modelo `nginx/https.conf.example` é completo: substitua `__APP_URL__` pela origem
HTTPS e monte certificado/chave válidos em `/etc/nginx/tls/`. O Compose padrão
continua HTTP para desenvolvimento; o modelo TLS precisa ser instalado no servidor.

Ainda dependem do destino: domínio/DNS, certificado confiável e sua renovação,
acesso ao servidor, contas reais, backup externo e monitoramento. Para operação
industrial, homologue CLP, scanner, câmera, perda de comunicação e intertravamentos.
Os testes locais acima não comprovam capacidade sob carga nem operação física.

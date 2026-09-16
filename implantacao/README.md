# Implantação do PC industrial

O PC industrial com interface gráfica usa o Trace em modo quiosque: o operador vê somente `http://127.0.0.1:8080` e não acessa a área de trabalho ou outros aplicativos pelo fluxo normal.

## Instalação

1. Copie o projeto para `/opt/dallogix-trace`.
2. Crie o `.env` e valide-o com `bash scripts/check_physical_deployment.sh`. Mantenha `WEB_BIND_ADDRESS=127.0.0.1`: nenhum acesso remoto entra no PC industrial. Todo gerenciamento remoto ocorre no servidor central. `BIND_ADDRESS=127.0.0.1` permanece obrigatório para MySQL, Node-RED e Modbus.
3. Instale o Chromium/Chrome e configure o usuário operador com login automático no ambiente gráfico.
4. Copie os serviços para `/etc/systemd/system/` e habilite:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now dallogix-trace.service
sudo systemctl enable --now dallogix-trace-kiosk.service
```

O serviço do Trace sobe os containers; o serviço quiosque abre o navegador em tela cheia e o reinicia se ele fechar. O arquivo de serviço assume um usuário Linux chamado `trace-operator`, sessão gráfica em `DISPLAY=:0` e Xauthority em `/home/trace-operator/.Xauthority`; ajuste esses valores se a distribuição usar outra sessão. A conta administrativa do Linux deve ser separada da conta operador e acessível somente à equipe técnica.

## Manutenção

```bash
sudo systemctl status dallogix-trace.service dallogix-trace-kiosk.service
docker compose ps
docker compose logs --tail=100
```

Não exponha as portas do MySQL, Node-RED, Modbus ou da interface web local fora do PC industrial. O PC inicia as conexões de saída HTTPS para o servidor central; não é necessário port forwarding no roteador da empresa. A comunicação PC industrial ↔ CLP continua local e a operação permanece disponível sem internet.

## Atualizações remotas

As atualizações são assinadas, verificadas por SHA-256, bloqueadas durante qualquer carregamento e protegidas por backup e rollback. Configure os campos `UPDATE_*` no `.env` e siga [documentacao/operacao/atualizacoes-remotas.md](../documentacao/operacao/atualizacoes-remotas.md). O timer deve ser instalado somente pela equipe técnica.

## Sincronização com GitHub

O repositório do servidor deve ter o remote `empresa` apontando para `https://github.com/VItoraaraujoo/Dallogix-Trace-System.git`, autenticado por SSH ou pelo gerenciador de credenciais do sistema. Para preparar/verificar a sincronização sem alterar nada, execute `TRACE_GITHUB_DRY_RUN=1 bash scripts/sync_github.sh`. A execução normal aceita somente fast-forward, preserva `.env` e `armazenamento/`, reconstrói o Compose e exige healthcheck. Não use essa sincronização durante carregamentos ativos; para produção, prefira o fluxo de pacote assinado descrito acima.

O workflow `.github/workflows/deploy-test.yml` executa esse sincronizador automaticamente quando os checks de qualidade e segurança concluem com sucesso para um commit da `master`. No ambiente `test` do GitHub, cadastre `TEST_SERVER_HOST`, `TEST_SERVER_USER`, `TEST_SERVER_PATH`, `TEST_SERVER_SSH_PORT`, `TEST_SERVER_SSH_KEY` e `TEST_SERVER_KNOWN_HOSTS`. A chave privada deve ser exclusiva do deploy, e o usuário remoto deve ter somente as permissões necessárias para o diretório do Trace e o Docker Compose.

A máquina industrial de produção não acompanha commits diretamente. Ela usa o timer de `update_trace.sh` para instalar somente releases assinadas publicadas pelo workflow `.github/workflows/release-production.yml`. O ambiente `production-release` do GitHub deve exigir aprovação técnica, e a máquina deve ter `UPDATE_MANIFEST_URL`, `UPDATE_PUBLIC_KEY_FILE` e `UPDATE_CHANNEL` configurados no `.env`.

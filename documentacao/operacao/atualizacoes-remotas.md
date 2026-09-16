# Atualizações remotas

O Trace pode receber versões remotamente, mas a máquina industrial só instala uma versão quando não existe carregamento em andamento. Não há cobrança automática nem ação remota sobre o CLP.

## Fluxo seguro

1. O servidor de distribuição publica um pacote imutável e um manifesto assinado.
2. A máquina consulta o manifesto por HTTPS e valida canal, versão, SHA-256 e assinatura com uma chave pública instalada localmente.
3. O agente verifica no banco se há carregamento `PREPARANDO`, `CARREGANDO`, `PAUSADO`, `FINALIZANDO` ou `EMERGENCIA`. Se houver, a atualização é recusada e pode ser tentada novamente depois.
4. Sem operação ativa, cria backup do banco e dos arquivos, instala a versão em uma área de releases e reinicia os serviços.
5. O healthcheck precisa responder. Caso contrário, a versão anterior é restaurada automaticamente.

O horário recomendado é uma janela de manutenção, por exemplo 03:30. O operador não precisa abrir terminal e não vê serviços técnicos; a equipe técnica continua podendo executar `TRACE_UPDATE_DRY_RUN=1` para validar um pacote sem instalar.

## Contrato do manifesto

```json
{
  "channel": "stable",
  "version": "2026.08.30.1",
  "artifact_url": "https://updates.exemplo.dallogix/trace-2026.08.30.1.tar.gz",
  "sha256": "064c...256 caracteres hexadecimais...e91a",
  "signature": "BASE64_DA_ASSINATURA_RSA_SHA256"
}
```

A assinatura é RSA-SHA256 sobre exatamente estas três linhas, terminadas por uma quebra de linha:

```text
version
artifact_url
sha256
```

A chave privada fica somente no servidor de distribuição. A máquina recebe apenas o arquivo de chave pública configurado em `UPDATE_PUBLIC_KEY_FILE`. O pacote deve conter `docker-compose.yml` e uma estrutura compatível com a instalação atual.

## Configuração Linux

No `.env` da máquina:

```dotenv
UPDATE_MANIFEST_URL=https://updates.exemplo.dallogix/stable/manifest.json
UPDATE_MANIFEST_TOKEN=token-da-maquina-ou-do-canal
UPDATE_PUBLIC_KEY_FILE=/opt/dallogix-trace/armazenamento/updates/trace-update-public.pem
UPDATE_CHANNEL=stable
```

Instale os serviços `dallogix-trace-sync.service` e `dallogix-trace-sync.timer` em `/etc/systemd/system/`. O timer permanece desabilitado por padrão; só habilite-o depois de criar `/etc/dallogix-trace/enable-auto-update`, configurar uma janela de manutenção e obter aprovação operacional. O timer apenas consulta e instala seguindo as regras acima. Para testar sem alterar nada:

```bash
TRACE_UPDATE_DRY_RUN=1 bash scripts/update_trace.sh
```

## Publicação pelo GitHub

O workflow `.github/workflows/release-production.yml` publica uma versão de produção quando uma tag SemVer (`v1.2.3`, por exemplo) é enviada ao GitHub. Antes de criar a release, ele confirma que a tag aponta para um commit da `master`, executa as validações de qualidade e segurança, monta um pacote somente com arquivos rastreados e publica o manifesto assinado junto com o artefato.

Configure no ambiente protegido `production-release` do GitHub o segredo `UPDATE_SIGNING_PRIVATE_KEY`. A chave privada nunca deve entrar no repositório. Instale somente a chave pública na máquina industrial e configure o `.env` da Dala para consultar o manifesto da última release estável:

```dotenv
UPDATE_MANIFEST_URL=https://github.com/VItoraaraujoo/Dallogix-Trace-System/releases/latest/download/manifest.json
UPDATE_PUBLIC_KEY_FILE=/opt/dallogix-trace/armazenamento/updates/trace-update-public.pem
UPDATE_CHANNEL=stable
```

O procedimento de publicação é:

```bash
git checkout master
git pull --ff-only origin master
git tag -a v1.2.3 -m "Dallogix Trace v1.2.3"
git push origin v1.2.3
```

O envio de um commit comum não atualiza a produção. A Dala só instala uma tag publicada, dentro da janela configurada no timer, depois de verificar assinatura, integridade, backup, ausência de carregamento ativo e healthcheck. O ambiente `production-release` deve exigir aprovação da equipe técnica antes de cada publicação.

## Sincronização remota em lote

O endpoint individual `SYNC_REMOTE_URL` continua sendo compatível com instalações
existentes: cada requisição recebe um evento JSON. Para reduzir conexões em uma
instalação com servidor central homologado, configure também `SYNC_REMOTE_BATCH_URL`.
Nesse modo, o worker envia uma requisição `POST` com este envelope:

```json
{
  "events": [
    {
      "event_uuid": "...",
      "company_id": 7,
      "aggregate_type": "leitura",
      "aggregate_id": 42,
      "payload": {"action": "LEITURA_REGISTRADA"}
    }
  ]
}
```

O servidor central só deve responder `2xx` depois de aceitar o lote inteiro e
deve tratar `event_uuid` como chave idempotente. Qualquer resposta fora de `2xx`
faz todos os eventos do lote voltar para `ERRO` com backoff; não há confirmação
parcial implícita. O token é enviado como `Authorization: Bearer` nos dois modos.

## Windows industrial

O mesmo contrato pode ser executado pelo atualizador do Windows dentro da janela de manutenção. O launcher continuará abrindo o Trace em quiosque após a atualização. A conta do operador não deve ter permissão para executar o atualizador; a tarefa agendada deve rodar com a conta técnica do equipamento.

## Limites importantes

- Não atualizar durante carregamento, retorno, emergência ou intervenção.
- Não aceitar HTTP, pacote sem assinatura ou chave pública recebida junto com o pacote.
- Não atualizar firmware do CLP por este mecanismo.
- Revogar um token remoto impede novas consultas, mas não interrompe uma operação já iniciada.

# Manual de instalação — PC industrial Windows

Este procedimento instala o Trace como operação local de produção. O banco e a interface permanecem no PC industrial; o servidor central é usado para gestão e sincronização quando houver internet.

## 1. Pré-requisitos

- Windows 10/11 Pro ou IoT Enterprise 64 bits, atualizado fora do turno;
- conta técnica local com permissão de administrador;
- Docker Desktop instalado e iniciado;
- Edge ou Chrome instalado;
- disco com pelo menos 30 GB livres e Wi-Fi industrial estável;
- IP, porta e protocolo do CLP confirmados; a câmera pode ser configurada depois.

Crie duas contas Windows: `trace-operator`, sem privilégios administrativos, e `trace-tech`, exclusiva para manutenção. A conta técnica não deve ser usada na operação diária.

### Wi-Fi industrial

Use uma rede Wi-Fi industrial dedicada, protegida por WPA2/WPA3, com reserva DHCP ou IP previsível para o PC. Posicione o ponto de acesso para manter sinal estável na área da esteira e desative a economia de energia do adaptador Wi-Fi no Windows. O PC deve reconectar automaticamente após reinício ou perda de sinal.

Mesmo conectado por Wi-Fi, a interface do Trace permanece limitada a `127.0.0.1`: outros dispositivos da rede não acessam o sistema local. O PC usa a rede apenas para sincronizar com o servidor central por HTTPS.

## 2. Copiar e configurar o pacote

1. Copie o pacote aprovado para `C:\ProgramData\DallogixTrace`.
2. Abra PowerShell como administrador.
3. Entre na pasta da instalação e crie o arquivo de ambiente:

```powershell
Copy-Item .env.example .env
notepad .env
```

No `.env`, defina senhas aleatórias para `MYSQL_PASSWORD` e `MYSQL_ROOT_PASSWORD`. Defina também `TRACE_DEVICE_TOKEN` e `CAMERA_DEVICE_TOKEN` com valores fortes e distintos; depois provisione cada dispositivo com `scripts/provision_device.php`. Nunca use valores `change-me-*`.

Mantenha `WEB_BIND_ADDRESS=127.0.0.1` e `BIND_ADDRESS=127.0.0.1`. Assim a interface, MySQL e serviços técnicos não ficam expostos na rede industrial.

Defina os perfis conforme o uso:

```dotenv
# Produção no PC industrial
COMPOSE_PROFILES=industrial

# Somente em bancada, quando o simulador for necessário
# COMPOSE_PROFILES=industrial,simulation
```

Mantenha `APP_ENV=local` enquanto o quiosque acessar `http://127.0.0.1:8080`. Esse valor permite a sessão segura no endereço local sem exigir certificado HTTPS. O PC ainda é uma instalação de produção operacional.

## 3. Registrar a máquina

Execute:

```powershell
.\implantacao\windows\Setup-TraceMachine.ps1
```

Informe o código da esteira, por exemplo `EST-001`, o ID cadastrado no servidor central, `https://trace.santocloud.com.br` como servidor central e a credencial exclusiva da máquina. Na primeira instalação, responda `N` para CLP físico habilitado.

O instalador inicia os containers e registra o agente do Trace para iniciar automaticamente com o Windows.

## 4. Validar a instalação local

No navegador técnico, abra:

```text
http://127.0.0.1:8080/api/health.php
```

O resultado deve ser HTTP 200 com `php=true`, `mysql=true` e os blocos `checks.queue`, `checks.heartbeats`, `checks.commands` e `checks.disk`. `status=degraded` exige análise da fila, heartbeat, comando travado ou disco antes do go-live. Em seguida, abra `http://127.0.0.1:8080`, entre com um login autorizado e confirme cadastro de Dala, romaneio e logs.

Para bancada, habilite o perfil `simulation` e execute o teste Modbus virtual. Não conecte comandos físicos nessa etapa.

## 5. Modo quiosque do operador

Teste o launcher com a conta técnica:

```powershell
.\implantacao\windows\TraceLauncher.cmd
```

Ele inicia os serviços locais, aguarda o healthcheck e abre o Trace em tela cheia. Configure `trace-operator` no Assigned Access ou Shell Launcher do Windows para iniciar esse launcher automaticamente. Se o navegador fechar, ele é reaberto.

O operador não deve ter acesso ao desktop, Explorer, configurações ou terminal. A manutenção ocorre somente após entrar com `trace-tech`.

## 6. Homologar CLP e câmera

Antes de habilitar CLP físico, registre e valide: modelo, IP, porta, protocolo, Unit ID, mapa de registradores, permissivos, retorno de estado, timeout, parada de emergência e reversão. A aplicação nunca substitui os intertravamentos do CLP.

Depois da homologação em bancada, altere a configuração para habilitar o CLP físico e reinicie os serviços em uma janela de manutenção. Configure a câmera com endereço, protocolo e credencial fora da interface do operador.

## 7. Backup e atualização

Faça um backup inicial após a homologação. As atualizações devem ocorrer fora do turno, sem carregamento ativo. O agente de atualização valida pacote assinado, cria backup e executa healthcheck antes de liberar a nova versão.

## 8. Diagnóstico rápido

```powershell
docker compose ps
Invoke-WebRequest http://127.0.0.1:8080/api/health.php
Get-ScheduledTask -TaskName "Dallogix Trace Agent"
```

Se a operação estiver parada e o healthcheck falhar, entre com `trace-tech`, preserve os logs em `C:\ProgramData\DallogixTrace\logs` e acione a manutenção.

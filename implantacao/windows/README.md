# Implantação Windows do Trace

O Windows pode apresentar o Trace como um único aplicativo para o operador. O arquivo `TraceLauncher.cmd` inicia os containers locais, aguarda o healthcheck e abre Edge/Chrome/Chromium em modo quiosque. Se o navegador fechar, ele é reaberto automaticamente.

## Instalação

O pacote de instalação da equipe técnica é compilado a partir de `TraceSetup.iss` com o Inno Setup e gera `TraceSetup.exe`. O operador final não recebe esse arquivo.

1. Instale Windows 10/11 IoT Enterprise ou Windows Pro, Docker Desktop e Edge/Chrome.
2. Copie o repositório para `C:\ProgramData\DallogixTrace`.
3. Copie `.env.example` para `.env` e preencha os valores reais da instalação.
   Mantenha `WEB_BIND_ADDRESS=127.0.0.1`. Nenhum acesso remoto entra no PC industrial; todo gerenciamento remoto ocorre no servidor central.
4. Teste `TraceLauncher.cmd` com a conta técnica.
5. Configure a conta `trace-operator` para login automático e use Windows Assigned Access/Shell Launcher para iniciar `TraceLauncher.cmd`.
6. Mantenha uma conta técnica administrativa separada da conta do operador.

O sistema será apresentado ao operador como `TraceLauncher`, equivalente ao `Trace.exe`. A transformação opcional do `.cmd` em um `.exe` deve ser feita somente no instalador oficial da empresa; o comportamento e os serviços continuam os mesmos.

Para a preparação técnica, a Dallogix entrega um `machine.json` preenchido e executa `Install-TraceMachine.ps1` com uma conta administrativa. O instalador sobe o Trace, registra o `Dallogix Agent` como tarefa automática do Windows e vincula a máquina ao servidor central. O arquivo `machine-config.example.json` é apenas um modelo; tokens reais nunca devem entrar no repositório.

## Segurança do PC

- Desative suspensão, hibernação e reinicialização automática durante o turno.
- Programe atualizações do Windows fora da operação.
- Mantenha a porta web vinculada a `127.0.0.1`; não libere a interface local para a rede industrial.
- Não exponha MySQL, Node-RED ou Modbus; o Compose já os mantém em `127.0.0.1` por padrão.
- Não habilite escrita física no CLP sem mapa de I/O, intertravamentos e teste de bancada homologados.

## Diagnóstico

```powershell
docker compose ps
docker compose logs --tail=100
Invoke-WebRequest http://127.0.0.1:8080/api/health.php
```

O launcher não cobra, não bloqueia o Windows e não altera dados de produção. O bloqueio mensal continua sendo manual pelo administrador Master dentro do Trace.

Enquanto o mapa de I/O não estiver aprovado, `operation_mode` deve permanecer `SIMULATED`, `physical_clp_enabled` deve ser `false` e `io_map_status` deve ser `CONFIRMAR`. Nessa condição, o Agent envia presença e saúde, mas nenhuma escrita física no CLP é permitida.

O `TraceAgent` mantém a presença da máquina no servidor central por HTTPS/443, iniciado pelo PC industrial. O heartbeat não depende de conexões recebidas e não exige abertura de porta no roteador. Se a internet cair, o Agent registra a falha, enquanto o Trace continua operando localmente com o CLP e a fila de sincronização.

## Atualizações remotas

O arquivo `TraceUpdater.ps1` valida o manifesto por HTTPS, a assinatura e o SHA-256, bloqueia a instalação quando há carregamento ativo, faz backup do banco e só registra a versão depois do healthcheck. Cadastre-o como tarefa agendada da conta técnica em uma janela de manutenção; o operador não deve ter permissão para executá-lo. O contrato completo está em [documentacao/operacao/atualizacoes-remotas.md](../../documentacao/operacao/atualizacoes-remotas.md).

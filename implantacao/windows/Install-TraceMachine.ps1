param(
    [string]$PackageRoot = (Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))),
    [string]$MachineConfig = (Join-Path $PSScriptRoot "machine-config.json")
)
$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Execute a preparação inicial com uma conta técnica administradora."
}
$compose = Join-Path $PackageRoot "docker-compose.yml"
$envFile = Join-Path $PackageRoot ".env"
if (-not (Test-Path $compose)) { throw "Pacote sem docker-compose.yml: $PackageRoot" }
if (-not (Test-Path $envFile)) { throw "O .env deve ser entregue previamente pela Dallogix." }
if (-not (Test-Path $MachineConfig)) { throw "machine.json deve ser criado pela Dallogix antes da instalação." }
if (-not (Get-Command docker.exe -ErrorAction SilentlyContinue)) { throw "Docker Desktop não está instalado." }

$config = Get-Content $MachineConfig -Raw | ConvertFrom-Json
if (-not $config.machine_id -or -not $config.central_api_url -or -not $config.device_token) { throw "machine.json sem identidade ou token do dispositivo SERVER." }
if ($config.physical_clp_enabled -eq $true -and $config.io_map_status -ne "APPROVED") { throw "Instalação física bloqueada sem mapa de I/O aprovado." }

$installRoot = "C:\ProgramData\DallogixTrace"
$configDir = Join-Path $installRoot "config"
New-Item -ItemType Directory -Force -Path $configDir | Out-Null
Copy-Item $MachineConfig (Join-Path $configDir "machine.json") -Force
Set-Location $PackageRoot
& docker.exe compose up -d

$agentScript = Join-Path $PackageRoot "implantacao\windows\TraceAgent.ps1"
$taskAction = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-NoLogo -NoProfile -ExecutionPolicy Bypass -File `"$agentScript`" -ConfigPath `"$configDir\machine.json`""
$taskTrigger = New-ScheduledTaskTrigger -AtStartup
$taskPrincipal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName "Dallogix Trace Agent" -Action $taskAction -Trigger $taskTrigger -Principal $taskPrincipal -Force | Out-Null
Start-ScheduledTask -TaskName "Dallogix Trace Agent"
Write-Output "Máquina preparada: $($config.machine_id). Modo: $($config.operation_mode). CLP físico habilitado: $($config.physical_clp_enabled)."

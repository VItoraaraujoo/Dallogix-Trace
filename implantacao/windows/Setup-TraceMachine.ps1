param(
    [string]$PackageRoot = (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))
)
$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Execute o instalador como administrador."
}
if (-not (Get-Command docker.exe -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop precisa estar instalado antes do TraceSetup.exe."
}

$configDir = Join-Path $PackageRoot "config"
$envPath = Join-Path $PackageRoot ".env"
New-Item -ItemType Directory -Force -Path $configDir | Out-Null
if (-not (Test-Path $envPath)) {
    Copy-Item (Join-Path $PackageRoot ".env.example") $envPath
}

function Ask([string]$Label, [string]$Default = "") {
    $suffix = if ($Default) { " [$Default]" } else { "" }
    $value = Read-Host "$Label$suffix"
    if (-not $value -and $Default) { return $Default }
    return $value
}

$machineId = Ask "Identificação da máquina" "EST-001"
$equipmentId = Ask "ID do equipamento no cadastro central" "1"
$equipmentCode = Ask "Código do equipamento" $machineId
$centralUrl = Ask "URL HTTPS do servidor central"
$deviceToken = Read-Host "Token do dispositivo SERVER da máquina"
$traceUrl = Ask "URL local do Trace" "http://127.0.0.1:8080"
$physical = (Ask "CLP físico habilitado? (S/N)" "N").ToUpperInvariant() -eq "S"
$ioStatus = if ($physical) { "APPROVED" } else { "CONFIRMAR" }

$machineConfig = [ordered]@{
    machine_id = $machineId
    equipment_id = [int]$equipmentId
    equipment_code = $equipmentCode
    central_api_url = $centralUrl
    device_token = $deviceToken
    trace_url = $traceUrl
    heartbeat_seconds = 30
    operation_mode = if ($physical) { "PHYSICAL" } else { "SIMULATED" }
    physical_clp_enabled = $physical
    io_map_status = $ioStatus
}
$machineConfig | ConvertTo-Json -Depth 5 | Set-Content -Path (Join-Path $configDir "machine.json") -Encoding UTF8

& powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PackageRoot "implantacao\windows\Install-TraceMachine.ps1") -PackageRoot $PackageRoot -MachineConfig (Join-Path $configDir "machine.json")
Write-Output "Configuração concluída. O Trace será iniciado automaticamente com o Windows."

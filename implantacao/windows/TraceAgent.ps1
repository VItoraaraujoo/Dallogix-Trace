param(
    [string]$ConfigPath = "C:\ProgramData\DallogixTrace\config\machine.json"
)
$ErrorActionPreference = "Stop"

function Write-AgentLog([string]$Message) {
    $logDir = "C:\ProgramData\DallogixTrace\logs"
    New-Item -ItemType Directory -Force -Path $logDir | Out-Null
    Add-Content -Path (Join-Path $logDir "agent.log") -Value ("{0:u} {1}" -f (Get-Date), $Message)
}

if (-not (Test-Path $ConfigPath)) { throw "Configuração da máquina não encontrada: $ConfigPath" }
$config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
if (-not $config.machine_id -or -not $config.equipment_id -or -not $config.central_api_url -or -not $config.device_token) { throw "machine.json incompleto." }
if ([string]$config.central_api_url -notmatch '^https://') { throw "central_api_url deve usar HTTPS." }
if ($config.physical_clp_enabled -eq $true -and $config.io_map_status -ne "APPROVED") {
    throw "CLP físico bloqueado: o mapa de I/O não está aprovado."
}
$interval = [Math]::Max(5, [int]$config.heartbeat_seconds)
$heartbeatUrl = "$($config.central_api_url.TrimEnd('/'))/api/device_heartbeat.php"

while ($true) {
    $traceOnline = $false
    try {
        $health = Invoke-WebRequest -Uri "$($config.trace_url.TrimEnd('/'))/api/health.php" -UseBasicParsing -TimeoutSec 5
        $traceOnline = $health.StatusCode -eq 200
    } catch {
        Write-AgentLog "Trace local indisponível: $($_.Exception.Message)"
    }
    $status = if ($traceOnline) { "ONLINE" } else { "ERRO" }
    $body = @{
        equipment_id = [int]$config.equipment_id
        device_type = "SERVER"
        status = $status
        details = @{
            machine_id = [string]$config.machine_id
            equipment_code = [string]$config.equipment_code
            operation_mode = [string]$config.operation_mode
            physical_clp_enabled = [bool]$config.physical_clp_enabled
            io_map_status = [string]$config.io_map_status
        }
    } | ConvertTo-Json -Depth 5
    try {
        Invoke-RestMethod -Uri $heartbeatUrl -Method Post -Headers @{ "X-Device-Token" = [string]$config.device_token } -ContentType "application/json" -Body $body -TimeoutSec 10 | Out-Null
    } catch {
        Write-AgentLog "Servidor central indisponível; operação local preservada: $($_.Exception.Message)"
    }
    Start-Sleep -Seconds $interval
}

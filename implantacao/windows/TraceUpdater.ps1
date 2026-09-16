$ErrorActionPreference = "Stop"

# Atualizador nativo do PC Windows. Deve rodar por tarefa técnica, nunca pela
# conta restrita do operador.
$InstallRoot = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path))
$StateRoot = Join-Path $InstallRoot "armazenamento\updates"
$MaintenanceFile = Join-Path $InstallRoot "armazenamento\.maintenance"
$EnvPath = Join-Path $InstallRoot ".env"
if (Test-Path $EnvPath) {
    Get-Content $EnvPath | Where-Object { $_ -match '^\s*([A-Za-z_][A-Za-z0-9_]*)=(.*)$' } | ForEach-Object {
        $name = $Matches[1]; $value = $Matches[2].Trim().Trim('"').Trim("'")
        if (-not (Get-Item "Env:$name" -ErrorAction SilentlyContinue)) { Set-Item "Env:$name" $value }
    }
}
$ManifestUrl = $env:UPDATE_MANIFEST_URL
$PublicKey = $env:UPDATE_PUBLIC_KEY_FILE
$Channel = if ($env:UPDATE_CHANNEL) { $env:UPDATE_CHANNEL } else { "stable" }
if (-not $ManifestUrl -or -not $PublicKey) { throw "Defina UPDATE_MANIFEST_URL e UPDATE_PUBLIC_KEY_FILE no .env." }
if ($ManifestUrl -notmatch '^https://') { throw "O manifesto deve usar HTTPS." }
if (-not (Test-Path $PublicKey)) { throw "Chave pública não encontrada: $PublicKey" }
if (-not (Get-Command docker.exe -ErrorAction SilentlyContinue)) { throw "Docker Desktop não está disponível." }
if (-not (Get-Command openssl.exe -ErrorAction SilentlyContinue)) { throw "openssl.exe é necessário para validar a assinatura." }
if (-not (Get-Command tar.exe -ErrorAction SilentlyContinue)) { throw "tar.exe é necessário para extrair o pacote." }

New-Item -ItemType Directory -Force -Path (Join-Path $StateRoot "releases"), (Join-Path $StateRoot "backups") | Out-Null
$Lock = Join-Path $StateRoot ".install.lock"
try { New-Item -ItemType Directory -Path $Lock -ErrorAction Stop | Out-Null } catch { throw "Já existe uma atualização em execução." }
try {
    $headers = @{}
    if ($env:UPDATE_MANIFEST_TOKEN) { $headers.Authorization = "Bearer $($env:UPDATE_MANIFEST_TOKEN)" }
    $manifest = Invoke-RestMethod -Uri $ManifestUrl -Headers $headers -TimeoutSec 20
    $manifestChannel = if ($manifest.channel) { $manifest.channel } else { $Channel }
    if ($manifestChannel -ne $Channel) { throw "Manifesto de canal diferente do configurado." }
    foreach ($field in @("version", "artifact_url", "sha256", "signature")) { if (-not $manifest.$field) { throw "Manifesto sem $field." } }
    if ($manifest.artifact_url -notmatch '^https://') { throw "O pacote deve usar HTTPS." }
    if ($manifest.version -notmatch '^[A-Za-z0-9._-]+$') { throw "Versão inválida." }
    if ($manifest.sha256 -notmatch '^[0-9a-fA-F]{64}$') { throw "SHA-256 inválido." }

    $work = Join-Path $StateRoot (".staging-" + [guid]::NewGuid().ToString("N"))
    New-Item -ItemType Directory -Force -Path $work | Out-Null
    $payload = Join-Path $work "payload"
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($payload, "$($manifest.version)`n$($manifest.artifact_url)`n$($manifest.sha256.ToLower())`n", $utf8)
    $sig = Join-Path $work "signature.bin"
    [IO.File]::WriteAllBytes($sig, [Convert]::FromBase64String($manifest.signature))
    & openssl.exe dgst -sha256 -verify $PublicKey -signature $sig $payload | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Assinatura do pacote rejeitada." }

    Set-Location $InstallRoot
    New-Item -ItemType File -Force -Path $MaintenanceFile | Out-Null
    $active = (& docker.exe compose exec -T mysql mysql -N -B -utrace "-p$($env:MYSQL_PASSWORD)" "$($env:MYSQL_DATABASE)" -e "SELECT COUNT(*) FROM carregamentos WHERE state IN ('PREPARANDO','CARREGANDO','PAUSADO','FINALIZANDO','EMERGENCIA');" 2>$null | Out-String).Trim()
    if ($active -and $active -ne "0") { throw "Atualização adiada: existe carregamento ativo ou em intervenção." }
    $current = Join-Path $StateRoot "current_version"
    if ((Test-Path $current) -and ((Get-Content $current -Raw).Trim() -eq $manifest.version)) { Write-Output "Trace já está na versão $($manifest.version)."; return }

    # O workflow de release publica tar.gz (o formato é o mesmo usado pelo
    # atualizador Linux); não tente abrir esses bytes como ZIP.
    $artifact = Join-Path $work ("trace-" + $manifest.version + ".tar.gz")
    Invoke-WebRequest -Uri $manifest.artifact_url -Headers $headers -OutFile $artifact -TimeoutSec 120
    if ((Get-FileHash $artifact -Algorithm SHA256).Hash.ToLower() -ne $manifest.sha256.ToLower()) { throw "Integridade do pacote rejeitada." }
    $release = Join-Path $StateRoot ("releases\" + $manifest.version)
    if (Test-Path $release) { Remove-Item $release -Recurse -Force }
    & tar.exe -xzf $artifact -C $release
    if ($LASTEXITCODE -ne 0) { throw "Não foi possível extrair o pacote." }
    $source = if (Test-Path (Join-Path $release "trace\docker-compose.yml")) { Join-Path $release "trace" } else { $release }
    if (-not (Test-Path (Join-Path $source "docker-compose.yml"))) { throw "Pacote sem docker-compose.yml." }

    $backup = Join-Path $StateRoot ("backups\pre-" + $manifest.version + "-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".sql")
    $dbArgs = @("compose", "exec", "-T", "mysql", "mysqldump", "--single-transaction", "--routines", "--events", "--triggers", "--no-tablespaces", "-utrace", "-p$($env:MYSQL_PASSWORD)", "$($env:MYSQL_DATABASE)")
    $proc = Start-Process docker.exe -ArgumentList $dbArgs -WorkingDirectory $InstallRoot -NoNewWindow -PassThru -RedirectStandardOutput $backup
    $proc.WaitForExit(); if ($proc.ExitCode -ne 0) { throw "Backup do banco falhou." }
    & docker.exe compose stop | Out-Null
    Get-ChildItem $source -Force | Where-Object { $_.Name -notin @(".env", "armazenamento", ".git") } | Copy-Item -Destination $InstallRoot -Recurse -Force
    & docker.exe compose up -d --build | Out-Null
    $healthy = $false
    1..30 | ForEach-Object { if (-not $healthy) { try { $r = Invoke-WebRequest "http://127.0.0.1:8080/api/health.php" -TimeoutSec 3; if ($r.StatusCode -eq 200) { $healthy = $true } } catch { Start-Sleep -Seconds 2 } } }
    if (-not $healthy) { throw "A nova versão não passou no healthcheck. Mantenha a máquina fora de operação e acione a equipe técnica para rollback." }
    Set-Content -Path $current -Value $manifest.version -NoNewline
    Write-Output "Trace atualizado com sucesso para $($manifest.version). Backup: $backup"
} finally {
    if (Test-Path $Lock) { Remove-Item $Lock -Recurse -Force }
    if (Test-Path $MaintenanceFile) { Remove-Item $MaintenanceFile -Force }
}

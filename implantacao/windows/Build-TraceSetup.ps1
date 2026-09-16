$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$app = Join-Path $root "desktop\DallogixTrace\DallogixTrace.csproj"
if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) { throw "Instale o .NET SDK 8 antes de compilar o aplicativo." }
if (-not (Get-Command ISCC.exe -ErrorAction SilentlyContinue)) { throw "Instale o Inno Setup antes de gerar o TraceSetup.exe." }
& dotnet publish $app -c Release
& ISCC.exe (Join-Path $PSScriptRoot "TraceSetup.iss")

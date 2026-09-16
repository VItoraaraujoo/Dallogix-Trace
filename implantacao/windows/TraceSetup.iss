; Compilar com Inno Setup em uma máquina Windows da equipe técnica.
; O pacote não contém credenciais reais; o .env e o machine.json são fornecidos
; durante a instalação homologada.
[Setup]
AppId={{8E47F2D6-7E85-4A2E-9A74-000000000001}}
AppName=Dallogix Trace
AppVersion=1.0.0
DefaultDirName={commonappdata}\DallogixTrace
DisableProgramGroupPage=yes
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64
OutputBaseFilename=TraceSetup
Compression=lzma2
SolidCompression=yes

[Files]
Source: "..\..\docker-compose.yml"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\..\interface\*"; DestDir: "{app}\interface"; Flags: recursesubdirs ignoreversion
Source: "..\..\servidor\*"; DestDir: "{app}\servidor"; Flags: recursesubdirs ignoreversion
Source: "..\..\integracoes\*"; DestDir: "{app}\integracoes"; Flags: recursesubdirs ignoreversion
Source: "..\..\docker\*"; DestDir: "{app}\docker"; Flags: recursesubdirs ignoreversion
Source: "..\..\nginx\*"; DestDir: "{app}\nginx"; Flags: recursesubdirs ignoreversion
Source: "..\..\banco-de-dados\*"; DestDir: "{app}\banco-de-dados"; Flags: recursesubdirs ignoreversion
Source: "..\..\scripts\*"; DestDir: "{app}\scripts"; Flags: recursesubdirs ignoreversion
Source: "..\..\.env.example"; DestDir: "{app}"; DestName: ".env.example"; Flags: ignoreversion
Source: "TraceLauncher.ps1"; DestDir: "{app}\implantacao\windows"; Flags: ignoreversion
Source: "TraceUpdater.ps1"; DestDir: "{app}\implantacao\windows"; Flags: ignoreversion
Source: "TraceAgent.ps1"; DestDir: "{app}\implantacao\windows"; Flags: ignoreversion
Source: "Build-TraceSetup.ps1"; DestDir: "{app}\implantacao\windows"; Flags: ignoreversion
Source: "..\..\desktop\DallogixTrace\bin\Release\net8.0-windows\win-x64\publish\*"; DestDir: "{app}"; Flags: recursesubdirs ignoreversion
Source: "TraceLauncher.cmd"; DestDir: "{app}\implantacao\windows"; Flags: ignoreversion
Source: "Install-TraceMachine.ps1"; DestDir: "{app}\implantacao\windows"; Flags: ignoreversion
Source: "Setup-TraceMachine.ps1"; DestDir: "{app}\implantacao\windows"; Flags: ignoreversion

[Dirs]
Name: "{app}\armazenamento"
Name: "{app}\config"
Name: "{app}\logs"

[Run]
Filename: "powershell.exe"; Parameters: "-NoLogo -NoProfile -ExecutionPolicy Bypass -File \"{app}\implantacao\windows\Setup-TraceMachine.ps1\" -PackageRoot \"{app}\""; WorkingDir: "{app}"; Flags: waituntilterminated; Description: "Configurar o Trace nesta máquina"; StatusMsg: "Configurando os serviços locais do Trace..."

<#
.SYNOPSIS
    Installs the Student Registration System into XAMPP.

.DESCRIPTION
    1. Finds XAMPP (C:\xampp or <drive>:\xampp on any drive, or -XamppPath).
    2. Starts MySQL and Apache if they are not already running.
    3. Copies the project into xampp\htdocs\<AppName>. An existing copy is
       renamed to a timestamped backup; an existing folder link is replaced.
    4. Creates the database, tables and admin account (runs install.php).
    5. Removes install.php from the installed copy and opens the site.

    The easiest way to run it is to double-click setup.bat.

.EXAMPLE
    .\setup.ps1
.EXAMPLE
    .\setup.ps1 -XamppPath "D:\xampp" -AppName srms -DbPassword "secret"
#>
param(
    [string]$XamppPath,
    [string]$AppName = 'srms',
    [string]$DbPassword = '',
    [switch]$KeepInstaller,
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'
$Source = $PSScriptRoot

function Write-Step([string]$m) { Write-Host "`n==> $m" -ForegroundColor Cyan }
function Write-Ok([string]$m)   { Write-Host "    $m" -ForegroundColor Green }
function Write-Note([string]$m) { Write-Host "    $m" -ForegroundColor Yellow }
function Stop-Setup([string]$m) { Write-Host "`nERROR: $m" -ForegroundColor Red; exit 1 }

function Test-Port([int]$Port) {
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $client.Connect('127.0.0.1', $Port)
        $client.Close()
        return $true
    } catch {
        return $false
    }
}

function Wait-Port([int]$Port, [int]$Seconds = 20) {
    for ($i = 0; $i -lt $Seconds; $i++) {
        if (Test-Port $Port) { return $true }
        Start-Sleep -Seconds 1
    }
    return $false
}

Write-Host 'Student Registration and Academic Management System - Setup' -ForegroundColor White

if ($AppName -notmatch '^[A-Za-z0-9_-]+$') {
    Stop-Setup "AppName may only contain letters, numbers, '-' and '_'."
}
if (-not (Test-Path (Join-Path $Source 'includes\config.php'))) {
    Stop-Setup "Run this script from the project folder (includes\config.php was not found next to it)."
}

# ---------------------------------------------------------------- 1. XAMPP
Write-Step 'Looking for XAMPP'
if (-not $XamppPath) {
    $candidates = @('C:\xampp') + @(Get-PSDrive -PSProvider FileSystem | ForEach-Object { Join-Path $_.Root 'xampp' })
    $XamppPath = $candidates | Where-Object { Test-Path (Join-Path $_ 'php\php.exe') } | Select-Object -First 1
}
if (-not $XamppPath -or -not (Test-Path (Join-Path $XamppPath 'php\php.exe'))) {
    Stop-Setup 'XAMPP was not found. Install it from https://www.apachefriends.org, or run: setup.ps1 -XamppPath "D:\xampp"'
}
$XamppPath = (Resolve-Path $XamppPath).Path
$Php  = Join-Path $XamppPath 'php\php.exe'
$Dest = Join-Path (Join-Path $XamppPath 'htdocs') $AppName
Write-Ok "Found XAMPP at $XamppPath"

if ($Dest.TrimEnd('\') -ieq $Source.TrimEnd('\')) {
    Stop-Setup "The project is already in $Dest. Run setup from your own copy of the project, not from htdocs."
}

# ------------------------------------------------------- 2. MySQL / Apache
Write-Step 'Checking MySQL and Apache'
if (Test-Port 3306) {
    Write-Ok 'MySQL is running.'
} else {
    Write-Note 'MySQL is not running. Starting it...'
    $mysqld = Join-Path $XamppPath 'mysql\bin\mysqld.exe'
    $myIni  = Join-Path $XamppPath 'mysql\bin\my.ini'
    Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=`"$myIni`"", '--standalone' -WindowStyle Hidden
    if (-not (Wait-Port 3306)) {
        Stop-Setup 'MySQL did not start. Open the XAMPP Control Panel, click Start next to MySQL, then run setup again.'
    }
    Write-Ok 'MySQL started.'
}

if (Test-Port 80) {
    Write-Ok 'Apache is running.'
} else {
    Write-Note 'Apache is not running. Starting it...'
    Start-Process -FilePath (Join-Path $XamppPath 'apache\bin\httpd.exe') -WorkingDirectory $XamppPath -WindowStyle Hidden
    if (Wait-Port 80) {
        Write-Ok 'Apache started.'
    } else {
        Write-Note 'Apache did not start (port 80 may be used by another program). Start it from the XAMPP Control Panel.'
    }
}

# ------------------------------------------------------------ 3. Copy files
Write-Step "Copying the project to $Dest"
if (Test-Path $Dest) {
    $existing = Get-Item $Dest -Force
    if ($existing.Attributes -band [System.IO.FileAttributes]::ReparsePoint) {
        # rmdir on a junction removes only the link, never the folder it points to
        cmd /c rmdir "$Dest"
        Write-Ok 'Replaced the existing folder link (the folder it pointed to is untouched).'
    } else {
        $backupName = $AppName + '_backup_' + (Get-Date -Format 'yyyyMMdd_HHmmss')
        Rename-Item -Path $Dest -NewName $backupName
        Write-Ok "Moved the existing copy to htdocs\$backupName"
    }
}

robocopy "$Source" "$Dest" /E /XF setup.ps1 setup.bat build-portable.ps1 /XD portable docs .git .vscode /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) {
    Stop-Setup "Copying failed (robocopy exit code $LASTEXITCODE)."
}
$fileCount = (Get-ChildItem $Dest -Recurse -File).Count
Write-Ok "Copied $fileCount files."

if ($DbPassword) {
    $cfg = Join-Path $Dest 'includes\config.php'
    $escaped = $DbPassword.Replace('\', '\\').Replace("'", "\'")
    $text = [System.IO.File]::ReadAllText($cfg)
    $text = $text.Replace("define('DB_PASS', '');", "define('DB_PASS', '$escaped');")
    [System.IO.File]::WriteAllText($cfg, $text)   # UTF-8 without BOM, so PHP headers keep working
    Write-Ok 'Saved the MySQL password in includes\config.php.'
}

# --------------------------------------------------------- 4. Database
Write-Step 'Creating the database and admin account'
Push-Location $Dest
try {
    $output = (& $Php -d display_errors=1 install.php) | Out-String
} finally {
    Pop-Location
}
$messages = [regex]::Matches($output, 'class="alert[^"]*">([^<]+)<') |
    ForEach-Object { [System.Net.WebUtility]::HtmlDecode($_.Groups[1].Value.Trim()) }
foreach ($m in $messages) { Write-Ok $m }
if ($output -notmatch 'Setup is complete') {
    if (-not $messages) { Write-Host $output }
    Stop-Setup 'Database setup failed. If your MySQL root user has a password, run: setup.ps1 -DbPassword "yourpassword"'
}

if ($KeepInstaller) {
    Write-Note 'install.php was kept. Delete it from the installed copy when you no longer need it.'
} else {
    Remove-Item (Join-Path $Dest 'install.php')
    Write-Ok 'Removed install.php from the installed copy.'
}

# ------------------------------------------------------------- 5. Done
$url = "http://localhost/$AppName/"
Write-Host "`nSetup complete." -ForegroundColor Green
Write-Host "Open $url and log in. The default login is in README.md."
Write-Host "Note: the site now runs from $Dest. Run setup again after you change the project files."
if (-not $NoBrowser) { Start-Process $url }
exit 0

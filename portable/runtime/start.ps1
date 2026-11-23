<#
    Starts the portable Student Registration System:
    the bundled MariaDB (MySQL) server and the PHP web server, then opens the browser.
    Run it through Start.bat. Nothing is installed on the computer.
#>
param(
    [switch]$NoBrowser,   # do not open the browser
    [switch]$NoWait       # start the servers and return (used by the build script)
)

$ErrorActionPreference = 'Stop'
$Runtime = $PSScriptRoot
$Root    = Split-Path $Runtime -Parent
$App     = Join-Path $Root 'app'
$Php     = Join-Path $Runtime 'php\php.exe'
$PhpExt  = Join-Path $Runtime 'php\ext'
$Mysqld  = Join-Path $Runtime 'mysql\bin\mysqld.exe'
$MysqlBase = Join-Path $Runtime 'mysql'
$Data    = Join-Path $Runtime 'mysql\data'
$Tmp     = Join-Path $Runtime 'tmp'
$Logs    = Join-Path $Runtime 'logs'
$PortsFile = Join-Path $Tmp 'ports.txt'

function Test-Port([int]$Port) {
    try {
        $c = New-Object System.Net.Sockets.TcpClient
        $c.Connect('127.0.0.1', $Port)
        $c.Close()
        return $true
    } catch { return $false }
}
function Get-FreePort([int]$From) {
    for ($p = $From; $p -lt $From + 100; $p++) {
        if (-not (Test-Port $p)) { return $p }
    }
    throw "No free port found near $From."
}
function Wait-Port([int]$Port, [int]$Seconds) {
    for ($i = 0; $i -lt $Seconds * 2; $i++) {
        if (Test-Port $Port) { return $true }
        Start-Sleep -Milliseconds 500
    }
    return $false
}
function Show-Error([string]$Message, [string]$Log) {
    Write-Host "`nERROR: $Message" -ForegroundColor Red
    if ($Log -and (Test-Path $Log)) {
        Write-Host "Last lines of $Log :" -ForegroundColor Yellow
        Get-Content $Log -Tail 15 | ForEach-Object { Write-Host "  $_" }
    }
    & (Join-Path $Runtime 'stop.ps1') -Quiet
    exit 1
}

Write-Host 'Student Registration and Academic Management System (portable)' -ForegroundColor White
Write-Host "Folder: $Root"

foreach ($required in $Php, $Mysqld, $Data, (Join-Path $App 'index.php')) {
    if (-not (Test-Path $required)) { Show-Error "Missing file or folder: $required. Copy the whole package again." }
}
New-Item -ItemType Directory -Force -Path $Tmp, $Logs | Out-Null

# Stop anything left running from an earlier start of this same folder
& (Join-Path $Runtime 'stop.ps1') -Quiet

# Free ports, so a MySQL or web server already on this PC does not get in the way
$dbPort  = Get-FreePort 3307
$webPort = Get-FreePort 8080
$pmaPort = Get-FreePort ($webPort + 10)
Set-Content -Path $PortsFile -Value "$dbPort`r`n$webPort`r`n$pmaPort" -Encoding ASCII

# ---------------------------------------------------------- MariaDB (MySQL)
Write-Host "`nStarting MySQL (MariaDB) on port $dbPort..."
$mysqlLog = Join-Path $Logs 'mysql.err'
$mysqlArgs = @(
    '--no-defaults',
    "--basedir=`"$MysqlBase`"",
    "--datadir=`"$Data`"",
    "--port=$dbPort",
    '--bind-address=127.0.0.1',
    "--log-error=`"$mysqlLog`"",
    '--innodb-buffer-pool-size=64M',
    '--character-set-server=utf8mb4',
    '--collation-server=utf8mb4_general_ci'
)
Start-Process -FilePath $Mysqld -ArgumentList $mysqlArgs -NoNewWindow
if (-not (Wait-Port $dbPort 40)) { Show-Error 'MySQL did not start.' $mysqlLog }
Write-Host '  MySQL is running.' -ForegroundColor Green

# ---------------------------------------------------------- PHP web servers
$PmaDir = Join-Path $Runtime 'phpmyadmin'
$hasPma = Test-Path (Join-Path $PmaDir 'index.php')
$env:SRMS_DB_PORT = "$dbPort"                                         # read by the app and phpMyAdmin
$env:SRMS_PMA_URL = if ($hasPma) { "http://127.0.0.1:$pmaPort/" } else { '' }   # the app's "Database" link
function Start-PhpServer([int]$Port, [string]$DocRoot, [string]$Name, [string[]]$Extensions) {
    $phpArgs = @('-n', "-dextension_dir=`"$PhpExt`"")
    $phpArgs += $Extensions | ForEach-Object { "-dextension=$_" }
    $phpArgs += @(
        '-dopcache.enable=0',   # built into PHP 8.5; a second running copy would crash on its shared cache
        "-dsession.save_path=`"$Tmp`"",
        '-ddisplay_errors=0',
        '-dlog_errors=1',
        "-derror_log=`"$(Join-Path $Logs "$Name-errors.log")`"",
        '-dmemory_limit=256M',
        '-S', "127.0.0.1:$Port",
        '-t', "`"$DocRoot`""
    )
    $log = Join-Path $Logs "$Name-requests.log"
    Start-Process -FilePath $Php -ArgumentList $phpArgs -NoNewWindow `
        -RedirectStandardError $log -RedirectStandardOutput (Join-Path $Logs "$Name-output.log")
    if (-not (Wait-Port $Port 20)) { Show-Error "The $Name web server did not start." $log }
}

Write-Host "Starting the web server on port $webPort..."
Start-PhpServer $webPort $App 'app' @('mysqli')
Write-Host '  Web server is running.' -ForegroundColor Green

$pmaUrl = ''
if ($hasPma) {
    New-Item -ItemType Directory -Force -Path (Join-Path $Tmp 'pma') | Out-Null
    Write-Host "Starting phpMyAdmin (database viewer) on port $pmaPort..."
    Start-PhpServer $pmaPort $PmaDir 'phpmyadmin' @('mysqli', 'openssl', 'mbstring', 'sodium')
    $pmaUrl = $env:SRMS_PMA_URL
    Write-Host '  phpMyAdmin is running.' -ForegroundColor Green
}

$url = "http://127.0.0.1:$webPort/"
if ($NoWait) { Write-Host "Running at $url"; exit 0 }

if (-not $NoBrowser) { Start-Process $url }

Write-Host ''
Write-Host '=================================================================' -ForegroundColor Cyan
Write-Host "  The system is running at:  $url" -ForegroundColor Cyan
if ($pmaUrl) { Write-Host "  Database viewer:           $pmaUrl" -ForegroundColor Cyan }
Write-Host '  Login and instructions: see USER GUIDE.pdf.' -ForegroundColor Cyan
Write-Host '  KEEP THIS WINDOW OPEN while you use the system.' -ForegroundColor Cyan
Write-Host '  Press ENTER here to stop the system safely.' -ForegroundColor Cyan
Write-Host '=================================================================' -ForegroundColor Cyan
[void](Read-Host)

& (Join-Path $Runtime 'stop.ps1')

<#
    Stops the portable system's MySQL and PHP servers that were started from
    this folder. Other MySQL or web servers on the computer are not touched.
#>
param([switch]$Quiet)

$Runtime = $PSScriptRoot
$Php     = Join-Path $Runtime 'php\php.exe'
$PortsFile = Join-Path $Runtime 'tmp\ports.txt'

function Get-OwnProcesses([string]$Name) {
    Get-Process -Name $Name -ErrorAction SilentlyContinue |
        Where-Object { $_.Path -and $_.Path.StartsWith($Runtime, [System.StringComparison]::OrdinalIgnoreCase) }
}

$web = @(Get-OwnProcesses 'php')
$db  = @(Get-OwnProcesses 'mysqld')
if (-not $web -and -not $db) {
    if (-not $Quiet) { Write-Host 'The system is not running.' }
    return
}

if (-not $Quiet) { Write-Host "`nStopping the system..." }
$web | Stop-Process -Force -ErrorAction SilentlyContinue

if ($db) {
    # Ask MariaDB to shut down cleanly so no data is lost
    if (Test-Path $PortsFile) {
        $dbPort = (Get-Content $PortsFile)[0]
        $code = "try { (new mysqli('127.0.0.1', 'root', '', '', $dbPort))->query('SHUTDOWN'); } catch (Throwable `$e) {}"
        & $Php -n "-dextension_dir=$(Join-Path $Runtime 'php\ext')" -dextension=mysqli -r $code 2>$null
    }
    foreach ($p in $db) {
        if (-not $p.WaitForExit(20000)) { $p | Stop-Process -Force -ErrorAction SilentlyContinue }
    }
}

if (-not $Quiet) { Write-Host 'Stopped. You can close this window.' -ForegroundColor Green }

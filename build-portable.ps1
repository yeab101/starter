<#
.SYNOPSIS
    Builds a fully portable copy of the system (PHP + MySQL included) from this
    computer's XAMPP. The result runs on any 64-bit Windows PC with nothing installed.

.DESCRIPTION
    Creates ..\SRMS-Portable next to this project folder (or -Output) with:
        Start.bat, Stop.bat, README.txt
        app\              the application
        runtime\php       php.exe + mysqli only
        runtime\mysql     MariaDB server + a ready database with the admin account
    An existing package is renamed to a timestamped backup first.

.EXAMPLE
    .\build-portable.ps1 -Zip
#>
param(
    [string]$XamppPath,
    [string]$Output,
    [switch]$Zip
)

$ErrorActionPreference = 'Stop'
$Source = $PSScriptRoot
if (-not $Output) { $Output = Join-Path (Split-Path $Source -Parent) 'SRMS-Portable' }

function Write-Step([string]$m) { Write-Host "`n==> $m" -ForegroundColor Cyan }
function Write-Ok([string]$m)   { Write-Host "    $m" -ForegroundColor Green }
function Stop-Build([string]$m) { Write-Host "`nERROR: $m" -ForegroundColor Red; exit 1 }
function Copy-Tree([string]$From, [string]$To, [string[]]$Extra = @()) {
    robocopy "$From" "$To" /E /NFL /NDL /NJH /NJS /NP @Extra | Out-Null
    if ($LASTEXITCODE -ge 8) { Stop-Build "Copying $From failed (robocopy code $LASTEXITCODE)." }
}

# ---------------------------------------------------------------- XAMPP
Write-Step 'Looking for XAMPP (source of PHP and MariaDB)'
if (-not $XamppPath) {
    $candidates = @('C:\xampp') + @(Get-PSDrive -PSProvider FileSystem | ForEach-Object { Join-Path $_.Root 'xampp' })
    $XamppPath = $candidates | Where-Object { Test-Path (Join-Path $_ 'mysql\bin\mysqld.exe') } | Select-Object -First 1
}
if (-not $XamppPath) { Stop-Build 'XAMPP was not found. Pass -XamppPath "D:\xampp".' }
$XPhp   = Join-Path $XamppPath 'php'
$XMysql = Join-Path $XamppPath 'mysql'
Write-Ok "Using $XamppPath"

$Runtime = Join-Path $Output 'runtime'

# ---------------------------------------------------------------- Output folder
Write-Step "Preparing $Output"
if (Test-Path $Output) {
    $oldStop = Join-Path $Output 'runtime\stop.ps1'
    if (Test-Path $oldStop) { & $oldStop -Quiet }
    $backup = (Split-Path $Output -Leaf) + '_backup_' + (Get-Date -Format 'yyyyMMdd_HHmmss')
    Rename-Item -Path $Output -NewName $backup
    Write-Ok "Existing package moved to $backup"
}
New-Item -ItemType Directory -Force -Path $Output | Out-Null

# ---------------------------------------------------------------- Files
Write-Step 'Updating USER GUIDE.pdf from docs\user-guide.html'
$guideHtml = Join-Path $Source 'docs\user-guide.html'
$guidePdf  = Join-Path $Source 'portable\USER GUIDE.pdf'
$edge = @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe") |
    Where-Object { Test-Path $_ } | Select-Object -First 1
if ($edge -and (Test-Path $guideHtml)) {
    $edgeProfile = Join-Path $env:TEMP ('srms-edge-' + [guid]::NewGuid())
    $p = Start-Process -FilePath $edge -PassThru -WindowStyle Hidden -ArgumentList @(
        '--headless=new', '--disable-gpu', "--user-data-dir=`"$edgeProfile`"", '--no-pdf-header-footer',
        "--print-to-pdf=`"$guidePdf`"", "`"$(([Uri]$guideHtml).AbsoluteUri)`"")
    $p.WaitForExit(60000) | Out-Null
    Remove-Item $edgeProfile -Recurse -Force -ErrorAction SilentlyContinue
    Write-Ok 'PDF updated.'
} else {
    Write-Host '    Microsoft Edge not found; using the existing USER GUIDE.pdf.' -ForegroundColor Yellow
}

Write-Step 'Copying launcher and application'
Copy-Tree (Join-Path $Source 'portable') $Output
Copy-Tree $Source (Join-Path $Output 'app') @('/XF', 'setup.ps1', 'setup.bat', 'build-portable.ps1', '/XD', 'portable', 'docs', '.git', '.vscode')
Write-Ok 'Done.'

Write-Step 'Copying portable PHP (php.exe + mysqli)'
$phpDir = Join-Path $Runtime 'php'
New-Item -ItemType Directory -Force -Path (Join-Path $phpDir 'ext') | Out-Null
Copy-Item (Join-Path $XPhp 'php.exe'), (Join-Path $XPhp 'php8ts.dll') $phpDir
Copy-Item (Join-Path $XPhp 'ext\php_mysqli.dll') (Join-Path $phpDir 'ext')
# Visual C++ runtime next to php.exe, so PCs without the VC++ redistributable work too
Copy-Item (Join-Path $XMysql 'bin\vcruntime140*.dll'), (Join-Path $XMysql 'bin\msvcp140.dll') $phpDir
if (Test-Path (Join-Path $XPhp 'license.txt')) { Copy-Item (Join-Path $XPhp 'license.txt') (Join-Path $phpDir 'PHP-LICENSE.txt') }
$check = & (Join-Path $phpDir 'php.exe') -n "-dextension_dir=$(Join-Path $phpDir 'ext')" -dextension=mysqli -r "echo class_exists('mysqli') ? 'ok' : 'missing';"
if ($check -ne 'ok') { Stop-Build "Portable PHP check failed: $check" }
Write-Ok 'PHP works with mysqli.'

Write-Step 'Copying phpMyAdmin (database viewer)'
$pmaSrc = Join-Path $XamppPath 'phpMyAdmin'
if (Test-Path (Join-Path $pmaSrc 'index.php')) {
    # Extensions phpMyAdmin needs, plus the libraries they load
    foreach ($ext in 'php_openssl.dll', 'php_mbstring.dll', 'php_sodium.dll') {
        Copy-Item (Join-Path $XPhp "ext\$ext") (Join-Path $phpDir 'ext')
    }
    Copy-Item (Join-Path $XPhp 'libcrypto-3-x64.dll'), (Join-Path $XPhp 'libssl-3-x64.dll'), (Join-Path $XPhp 'libsodium.dll') $phpDir
    $pmaDir = Join-Path $Runtime 'phpmyadmin'
    Copy-Tree $pmaSrc $pmaDir @('/XF', 'config.inc.php', '/XD', 'setup', 'tmp')
    Move-Item (Join-Path $Runtime 'phpmyadmin-config.inc.php') (Join-Path $pmaDir 'config.inc.php') -Force
    $check = & (Join-Path $phpDir 'php.exe') -n "-dextension_dir=$(Join-Path $phpDir 'ext')" -dextension=openssl -dextension=mbstring -dextension=sodium `
        -r "echo extension_loaded('openssl') && extension_loaded('mbstring') && extension_loaded('sodium') ? 'ok' : 'missing';"
    if ($check -ne 'ok') { Stop-Build "phpMyAdmin PHP extensions check failed: $check" }
    Write-Ok 'phpMyAdmin included.'
} else {
    Remove-Item (Join-Path $Runtime 'phpmyadmin-config.inc.php') -ErrorAction SilentlyContinue
    Write-Host '    phpMyAdmin not found in XAMPP; the package will not include a database viewer.' -ForegroundColor Yellow
}

Write-Step 'Copying portable MariaDB (MySQL)'
$mysqlBin = Join-Path $Runtime 'mysql\bin'
New-Item -ItemType Directory -Force -Path $mysqlBin | Out-Null
Copy-Item (Join-Path $XMysql 'bin\mysqld.exe'), (Join-Path $XMysql 'bin\*.dll') $mysqlBin
Copy-Tree (Join-Path $XMysql 'share') (Join-Path $Runtime 'mysql\share')
Write-Ok 'Done.'

# ---------------------------------------------------------------- Database
Write-Step 'Creating a fresh MySQL data folder'
$data = Join-Path $Runtime 'mysql\data'
& (Join-Path $XMysql 'bin\mariadb-install-db.exe') "--datadir=$data" --silent
if ($LASTEXITCODE -ne 0) { Stop-Build "mariadb-install-db failed (code $LASTEXITCODE)." }
Remove-Item (Join-Path $data 'my.ini') -ErrorAction SilentlyContinue   # contains this PC's absolute paths
Write-Ok 'Done.'

Write-Step 'Creating the srms database and admin account'
& (Join-Path $Runtime 'start.ps1') -NoWait -NoBrowser
if ($LASTEXITCODE -ne 0) { Stop-Build 'The portable servers did not start.' }
$appDir = Join-Path $Output 'app'
Push-Location $appDir
try {
    $out = (& (Join-Path $phpDir 'php.exe') -n "-dextension_dir=$(Join-Path $phpDir 'ext')" -dextension=mysqli `
        "-dsession.save_path=$(Join-Path $Runtime 'tmp')" install.php) | Out-String
} finally {
    Pop-Location
}
& (Join-Path $Runtime 'stop.ps1') -Quiet
if ($out -notmatch 'Setup is complete') {
    Write-Host $out
    Stop-Build 'Database setup failed.'
}
Remove-Item (Join-Path $appDir 'install.php')
Write-Ok 'Database ready; install.php removed from the package.'

# Clean run-time leftovers so the package starts fresh
Get-ChildItem (Join-Path $Runtime 'tmp'), (Join-Path $Runtime 'logs') -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force

# ---------------------------------------------------------------- Summary
$sizeMB = (Get-ChildItem $Output -Recurse -File | Measure-Object Length -Sum).Sum / 1MB
Write-Host ("`nPortable package ready: {0}  ({1:N0} MB)" -f $Output, $sizeMB) -ForegroundColor Green

if ($Zip) {
    $zipPath = "$Output.zip"
    Remove-Item $zipPath -ErrorAction SilentlyContinue
    Compress-Archive -Path $Output -DestinationPath $zipPath -CompressionLevel Optimal
    Write-Host ("Zip file: {0}  ({1:N0} MB)" -f $zipPath, ((Get-Item $zipPath).Length / 1MB)) -ForegroundColor Green
}
Write-Host 'To run it: open the folder and double-click Start.bat.'
exit 0

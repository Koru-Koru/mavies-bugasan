# Start the app on http://127.0.0.1:8000
#
# Kills any stale `artisan serve` / `php -S` left on the port first. Several
# orphaned servers all bound to 127.0.0.1:8000 is what makes the site return a
# blank 500, so the cleanup is the important part of this script.
#
# Usage:  powershell -ExecutionPolicy Bypass -File "D:\rice-and-shine\scripts\start.ps1"

param(
    [string]$MysqlD = 'D:\XAMPP\mysql\bin\mysqld.exe',
    [string]$MyIni  = 'D:\XAMPP\mysql\bin\my.ini',
    [string]$Php    = 'D:\tools\php83\php.exe',
    [string]$Host_  = '127.0.0.1',
    [int]   $Port    = 8000,
    [int]   $DbPort  = 3306
)

$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $ProjectRoot

# --- database -------------------------------------------------------------
if (-not (Test-NetConnection -ComputerName 127.0.0.1 -Port $DbPort -InformationLevel Quiet -WarningAction SilentlyContinue)) {
    Write-Host "MySQL not running - starting it..."
    Start-Process $MysqlD -ArgumentList "--defaults-file=$MyIni" -WindowStyle Hidden
    Start-Sleep -Seconds 8
}
Write-Host "OK  database up"

# --- clear stale servers on the port --------------------------------------
Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
    Where-Object { $_.CommandLine -like "*artisan serve*" -or $_.CommandLine -like "*127.0.0.1:$Port*" } |
    ForEach-Object {
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        Write-Host "OK  stopped stale server (pid $($_.ProcessId))"
    }
Start-Sleep -Seconds 2

# --- start one clean server -----------------------------------------------
Start-Process $Php `
    -ArgumentList 'artisan', 'serve', "--host=$Host_", "--port=$Port" `
    -WorkingDirectory $ProjectRoot `
    -WindowStyle Hidden

Start-Sleep -Seconds 5

$url = "http://$Host_`:$Port"
try {
    $code = (Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 15).StatusCode
    Write-Host ''
    Write-Host "OK  server running (HTTP $code)"
    Write-Host ''
    Write-Host "  Customer site   $url/"
    Write-Host "  Admin panel     $url/admin"
} catch {
    throw "Server did not come up on $url - $($_.Exception.Message)"
}
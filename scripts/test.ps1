# One-command test runner for the Mavies Bugasan project.
#
# Handles the two things that always break a fresh terminal:
#   1. being in the wrong directory
#   2. the MySQL/MariaDB server not running
#
# Usage (from anywhere):
#   powershell -ExecutionPolicy Bypass -File "D:\rice-and-shine\scripts\test.ps1"
#
# Pass extra PHPUnit arguments through, e.g. to run one file:
#   .\scripts\test.ps1 tests\Feature\ReportTest.php
#   .\scripts\test.ps1 --filter test_summary_agrees

param(
    [string]$MysqlD  = 'D:\XAMPP\mysql\bin\mysqld.exe',
    [string]$MyIni   = 'D:\XAMPP\mysql\bin\my.ini',
    [string]$Mysql   = 'D:\XAMPP\mysql\bin\mysql.exe',
    [int]   $Port     = 3306,
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$PhpUnitArgs
)

$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $ProjectRoot

Write-Host "Project: $ProjectRoot"

# --- 1. database server must be up, or the tests cannot connect -----------
$up = Test-NetConnection -ComputerName 127.0.0.1 -Port $Port -InformationLevel Quiet -WarningAction SilentlyContinue

if (-not $up) {
    if (-not (Test-Path -LiteralPath $MysqlD)) { throw "mysqld not found at $MysqlD" }
    if (-not (Test-Path -LiteralPath $MyIni))  { throw "my.ini not found at $MyIni" }

    Write-Host "MySQL not running on port $Port - starting it..."
    Start-Process $MysqlD -ArgumentList "--defaults-file=$MyIni" -WindowStyle Hidden
    Start-Sleep -Seconds 8

    $up = Test-NetConnection -ComputerName 127.0.0.1 -Port $Port -InformationLevel Quiet -WarningAction SilentlyContinue
    if (-not $up) { throw "MySQL failed to start. Open XAMPP and click Start on MySQL." }
}

Write-Host "OK  database up on 127.0.0.1:$Port"

# --- 2. the test database must exist --------------------------------------
& $Mysql -u root -h 127.0.0.1 -P $Port `
    -e "CREATE DATABASE IF NOT EXISTS mavies_bugasan_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) { throw "Could not reach MySQL as user 'root'." }

Write-Host "OK  mavies_bugasan_test exists"

# --- 3. run the suite ------------------------------------------------------
Write-Host ''
if ($PhpUnitArgs -and $PhpUnitArgs.Count -gt 0) {
    & vendor\bin\phpunit @PhpUnitArgs
} else {
    & php artisan test
}

$code = $LASTEXITCODE

Write-Host ''
if ($code -eq 0) {
    Write-Host 'OK  all tests passed'
} else {
    Write-Host "FAIL  tests failed (exit code $code)"
}

exit $code
# Daily backup of the mavies_bugasan database.
#
# Usage (daily, via Task Scheduler):
#   powershell -ExecutionPolicy Bypass -File "D:\rice-and-shine\scripts\backup.ps1"
#
# Writes:  storage\app\backups\mavies_bugasan-YYYYMMDD-HHmmss.sql
# Keeps:   the most recent $Keep backups, prunes the rest.
# Verify:  run scripts\restore-check.ps1 at least weekly (prove the restore).

param(
    [string]$MysqlDump = 'D:\XAMPP\mysql\bin\mysqldump.exe',
    [string]$BackupDir  = 'D:\rice-and-shine\storage\app\backups',
    [string]$Database   = 'mavies_bugasan',
    [string]$User       = 'root',
    [string]$Password   = '',
    [int]   $Keep       = 14
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $MysqlDump)) {
    throw "mysqldump not found at $MysqlDump"
}
if (-not (Test-Path -LiteralPath $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
}

# Set MYSQL_PWD instead of -p so the password never shows in the process list.
if ($Password) {
    $env:MYSQL_PWD = $Password
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$file  = Join-Path $BackupDir "$Database-$stamp.sql"

# --result-file makes mysqldump write the bytes itself; PowerShell's `>`
# redirection would mangle them into UTF-16.
& $MysqlDump `
    --default-character-set=utf8mb4 `
    --single-transaction `
    --routines `
    --triggers `
    --add-drop-table `
    --result-file=$file `
    --user=$User `
    $Database

if ($LASTEXITCODE -ne 0) {
    throw "mysqldump failed with exit code $LASTEXITCODE"
}

if ($Password) {
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}

$size = [math]::Round((Get-Item -LiteralPath $file).Length / 1KB, 1)

# Prune old backups.
Get-ChildItem -LiteralPath $BackupDir -Filter "$Database-*.sql" |
    Sort-Object Name -Descending |
    Select-Object -Skip $Keep |
    Remove-Item -Force

$left = @(Get-ChildItem -LiteralPath $BackupDir -Filter "$Database-*.sql").Count

Write-Host "OK  backup  $file  ($size KB)"
Write-Host "OK  kept    $left backups (prune older than $Keep)"
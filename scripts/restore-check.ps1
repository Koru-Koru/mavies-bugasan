# Restore test for the mavies_bugasan backups.
#
# Restores the most recent backup into a throwaway database, proves it by
# comparing the restored object count against the live database, then drops
# the throwaway database. Exits non-zero on any failure -> "the backup is not
# restorable".
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File "D:\rice-and-shine\scripts\restore-check.ps1"

param(
    [string]$Mysql     = 'D:\XAMPP\mysql\bin\mysql.exe',
    [string]$BackupDir = 'D:\rice-and-shine\storage\app\backups',
    [string]$Database  = 'mavies_bugasan',
    [string]$User      = 'root',
    [string]$Password  = ''
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $Mysql)) {
    throw "mysql client not found at $Mysql"
}

$latest = Get-ChildItem -LiteralPath $BackupDir -Filter "$Database-*.sql" |
    Sort-Object Name -Descending |
    Select-Object -First 1

if (-not $latest) {
    throw "No backup found under $BackupDir (run scripts\backup.ps1 first)"
}

if ($Password) {
    $env:MYSQL_PWD = $Password
}

$checkDb = $Database + '_restore_check'

function Invoke-MySql([string]$Sql) {
    & $Mysql --user=$User --execute=$Sql
    if ($LASTEXITCODE -ne 0) {
        Cleanup
        throw "mysql command failed: $Sql"
    }
}

function Cleanup {
    & $Mysql --user=$User --execute="DROP DATABASE IF EXISTS $checkDb" 2>$null | Out-Null
    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
}

Write-Host "Restoring $($latest.Name) into $checkDb ..."

Invoke-MySql "DROP DATABASE IF EXISTS $checkDb; CREATE DATABASE $checkDb"

# Streamed restore (cmd.exe handles < input redirection; PS 5.1 cannot).
cmd /c "`"$Mysql`" --user=`"$User`" `"$checkDb`" < `"$($latest.FullName)`""
if ($LASTEXITCODE -ne 0) {
    Cleanup
    throw "restore into $checkDb failed with exit code $LASTEXITCODE"
}

# Compare object count (tables + views) against the live database.
$liveCount  = & $Mysql --user=$User --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$Database';"
$checkCount = & $Mysql --user=$User --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$checkDb';"

if ([int]$liveCount -ne [int]$checkCount) {
    Cleanup
    throw "Object count mismatch: live=$liveCount restored=$checkCount"
}

Cleanup

Write-Host "OK  restore verified: $($latest.Name) -> $checkCount objects (live: $liveCount)"
Write-Host "OK  throwaway database dropped"
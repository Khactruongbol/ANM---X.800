[CmdletBinding()]
param(
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$Database = 'machtin_db',
    [string]$MysqlUser = 'root',
    [string]$MysqlHost = '127.0.0.1'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$migrationRoot = Join-Path $projectRoot 'app\website-tin-tuc\backend\database\migrations'

if (-not (Test-Path -LiteralPath $MysqlPath)) { throw "Khong tim thay mysql.exe: $MysqlPath" }
if (-not (Test-Path -LiteralPath $migrationRoot)) { throw "Khong tim thay thu muc migration: $migrationRoot" }

$migrationFiles = Get-ChildItem -LiteralPath $migrationRoot -Filter '*.sql' -File | Sort-Object Name
if (-not $migrationFiles) { Write-Host 'Khong co migration de chay.'; exit 0 }

Write-Host "Ap dung $($migrationFiles.Count) migration vao $Database@$MysqlHost ..."
foreach ($migrationFile in $migrationFiles) {
    Write-Host "- $($migrationFile.Name)"
    Get-Content -LiteralPath $migrationFile.FullName -Raw | & $MysqlPath --protocol=tcp --host=$MysqlHost --user=$MysqlUser $Database
    if ($LASTEXITCODE -ne 0) { throw "Migration that bai: $($migrationFile.Name)" }
}
Write-Host 'Migration hoan tat.'

[CmdletBinding()]
param([string]$Python = 'python')
$ErrorActionPreference = 'Stop'
$repoPath = Split-Path -Parent $PSScriptRoot
Push-Location $repoPath
try {
    & $Python -X utf8 tests/database/check_mysql.py
    if ($LASTEXITCODE -ne 0) { throw 'Echec des tests MySQL.' }
    & docker compose cp tests/database/check_mongodb.js mongodb:/tmp/check_mongodb.js
    if ($LASTEXITCODE -ne 0) { throw 'Copie du test MongoDB impossible.' }
    $result = & docker compose exec -T mongodb mongosh --quiet --file /tmp/check_mongodb.js
    $code = $LASTEXITCODE
    [IO.File]::WriteAllText((Join-Path $repoPath 'tmp/database-tests/mongodb.json'), ($result -join "`n"), (New-Object Text.UTF8Encoding($false)))
    if ($code -ne 0) { throw 'Echec des tests MongoDB ; consulter le rapport local.' }
    $report = ($result -join "`n") | ConvertFrom-Json
    if (@($report.tests | Where-Object { -not $_.reussi }).Count -gt 0) { throw 'MongoDB : test non valide.' }
    Write-Output "MongoDB : $($report.tests.Count) controles reussis. Rapports dans tmp/database-tests."
} finally { Pop-Location }

[CmdletBinding()]
param([string]$Python='python')
$ErrorActionPreference='Stop'
Push-Location (Split-Path -Parent $PSScriptRoot)
try {
    & ./scripts/Start-Databases.ps1
    & $Python -X utf8 scripts/provision-api-accounts.py
    if ($LASTEXITCODE -ne 0) { throw 'Comptes API non prepares. Verifier la creation de la demonstration.' }
    $composeArgs=@('compose','-f','compose.yaml','-f','compose.app.yaml')
    & docker @composeArgs build api
    if ($LASTEXITCODE -ne 0) { throw 'Construction PHP echouee.' }
    & docker @composeArgs run --rm --no-deps api composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Dependances PHP non installees.' }
    & docker @composeArgs run --rm --no-deps web npm ci
    if ($LASTEXITCODE -ne 0) { throw 'Dependances Vue non installees.' }
    & docker @composeArgs up -d --wait --wait-timeout 90
    if ($LASTEXITCODE -ne 0) { throw 'Demarrage non confirme ; consulter les journaux.' }
    Write-Output 'Application locale : http://127.0.0.1:5173 ; ecran provisoire uniquement.'
} finally { Pop-Location }

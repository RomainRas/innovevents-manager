[CmdletBinding()]
param([string]$Python='python')
$ErrorActionPreference='Stop'
Push-Location (Split-Path -Parent $PSScriptRoot)
try {
    & ./scripts/Start-Databases.ps1
    & $Python -X utf8 scripts/provision-api-accounts.py
    if ($LASTEXITCODE -ne 0) { throw 'Comptes API non prepares. Verifier la creation de la demonstration.' }
    & $Python -X utf8 scripts/provision-mail.py
    if ($LASTEXITCODE -ne 0) { throw 'Migration courriels non terminee.' }
    & $Python -X utf8 scripts/provision-quotes.py
    if ($LASTEXITCODE -ne 0) { throw 'Migration demandes non terminee.' }
    & $Python -X utf8 scripts/provision-prospects.py
    if ($LASTEXITCODE -ne 0) { throw 'Migration prospects non terminee.' }
    & $Python -X utf8 scripts/provision-conversion.py
    if ($LASTEXITCODE -ne 0) { throw 'Migration conversion non terminee.' }
    & $Python -X utf8 scripts/provision-events.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement événements interrompu' }
    & $Python -X utf8 scripts/provision-event-images.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement images non termine.' }
    & $Python -X utf8 scripts/provision-quote-drafts.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement devis interrompu' }
    & $Python -X utf8 scripts/provision-quote-pdf.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement PDF interrompu' }
    & $Python -X utf8 scripts/provision-quote-send.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement transmission interrompu' }
    & $Python -X utf8 scripts/provision-quote-decisions.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement decisions interrompu' }
    & $Python -X utf8 scripts/provision-public-pages.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement pages publiques interrompu' }
    & $Python -X utf8 scripts/provision-client-space.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement espace client interrompu' }
    & $Python -X utf8 scripts/provision-admin-accounts.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement administration interrompu' }
    & $Python -X utf8 scripts/provision-team-space.py
    if ($LASTEXITCODE -ne 0) { throw 'Provisionnement espace employe interrompu' }
    $composeArgs=@('compose','-f','compose.yaml','-f','compose.app.yaml')
    & docker @composeArgs build api
    if ($LASTEXITCODE -ne 0) { throw 'Construction PHP echouee.' }
    & docker @composeArgs run --rm --no-deps api composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Dependances PHP non installees.' }
    & docker @composeArgs run --rm --no-deps web npm ci
    if ($LASTEXITCODE -ne 0) { throw 'Dependances Vue non installees.' }
    & docker @composeArgs run --rm --no-deps mobile npm ci
    if ($LASTEXITCODE -ne 0) { throw 'Dependances application mobile non installees.' }
    & docker @composeArgs up -d --wait --wait-timeout 90
    if ($LASTEXITCODE -ne 0) { throw 'Demarrage non confirme ; consulter les journaux.' }
    Write-Output 'Application locale : http://127.0.0.1:5173 ; demandes : /demande-devis ; application mobile : /mobile/ ; courriels de test : http://127.0.0.1:8025.'
} finally { Pop-Location }

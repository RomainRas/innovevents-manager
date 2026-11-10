[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$repoPath = Split-Path -Parent $PSScriptRoot
Push-Location $repoPath
try {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw 'Docker absent du terminal. Installer puis ouvrir Docker Desktop, et rouvrir le terminal.'
    }
    & docker info --format '{{.ServerVersion}}'
    if ($LASTEXITCODE -ne 0) { throw 'Le moteur Docker ne répond pas. Vérifier Docker Desktop et WSL.' }
    & (Join-Path $PSScriptRoot 'Initialize-LocalSecrets.ps1')
    & docker compose config --quiet
    if ($LASTEXITCODE -ne 0) { throw 'Configuration Compose invalide ; aucun démarrage demandé.' }
    & docker compose up -d --wait --wait-timeout 240
    if ($LASTEXITCODE -ne 0) {
        throw 'Initialisation non confirmée. Consulter docker compose ps et les journaux ; ne pas supprimer les volumes.'
    }
    & docker compose ps
    Write-Output 'Les deux services sont démarrés et leurs contrôles de santé ont réussi. Les tests métier restent distincts.'
} finally { Pop-Location }

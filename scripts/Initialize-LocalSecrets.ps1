# Compatible PowerShell 5.1+. Aucune valeur secrete n'est affichee.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$repoPath = Split-Path -Parent $PSScriptRoot
$secretsPath = Join-Path $repoPath '.secrets'
[System.IO.Directory]::CreateDirectory($secretsPath) | Out-Null
$utf8 = New-Object System.Text.UTF8Encoding($false)

function Write-NewSecretFile([string]$Path, [string]$Content) {
    $stream = [System.IO.File]::Open($Path, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write)
    try {
        $bytes = $utf8.GetBytes($Content)
        $stream.Write($bytes, 0, $bytes.Length)
    } finally { $stream.Dispose() }
}
foreach ($filename in @('mysql-root.txt', 'mongo-root.txt')) {
    $path = Join-Path $secretsPath $filename
    if (-not (Test-Path -LiteralPath $path)) {
        $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        try {
            $bytes = New-Object byte[] 32
            $random.GetBytes($bytes)
            $secret = [System.BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
            Write-NewSecretFile $path $secret
        } finally { $random.Dispose() }
    }
}
$mysqlSecret = [System.IO.File]::ReadAllText((Join-Path $secretsPath 'mysql-root.txt')).Trim()
if ($mysqlSecret -notmatch '^[a-f0-9]{64}$') { throw 'Format du secret MySQL local inattendu ; aucune rotation automatique.' }
$clientPath = Join-Path $secretsPath 'mysql-client.cnf'
$clientContent = "[client]`nuser=root`npassword=$mysqlSecret`nhost=127.0.0.1`nprotocol=TCP`n"
if (Test-Path -LiteralPath $clientPath) {
    if ([System.IO.File]::ReadAllText($clientPath) -ne $clientContent) {
        throw 'Configuration client differente du secret existant ; verifier avant de remplacer.'
    }
} else { Write-NewSecretFile $clientPath $clientContent }
Write-Output 'Secrets locaux prets dans .secrets ; valeurs non affichees, fichiers exclus de Git.'

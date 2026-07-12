param(
    [string] $CommitMessage,
    [switch] $SkipLocalValidation,
    [switch] $FullLocalValidation,
    [switch] $RunRemoteTests,
    [switch] $RunMigrations,
    [switch] $SkipCommit,
    [string] $Server = 'higor@192.168.17.65',
    [string] $IdentityFile = 'work\runtime\ssh\farmfort_dev_ed25519',
    [string] $RemoteRepo = '/home/higor/Sistema-ERP-rural-Laravel'
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'activate.ps1') -Quiet

function Invoke-Checked {
    param(
        [string] $Name,
        [scriptblock] $Command
    )

    Write-Host ""
    Write-Host "==> $Name"
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Name failed with exit code $LASTEXITCODE."
    }
}

Push-Location $Script:FarmfortRepoRoot
try {
    $branch = (git branch --show-current).Trim()
    if ($branch -eq '') {
        throw 'Nao foi possivel identificar a branch atual.'
    }

    if ($branch -eq 'main') {
        throw 'Deploy rapido direto da main bloqueado. Use uma branch de desenvolvimento.'
    }

    if ($branch -notmatch '^[A-Za-z0-9._\/-]+$') {
        throw "Nome de branch nao suportado para deploy automatico: $branch"
    }

    if (-not $SkipLocalValidation) {
        $mode = if ($FullLocalValidation) { 'full' } else { 'fast' }
        Invoke-Checked "validacao local $mode" {
            & (Join-Path $PSScriptRoot 'validate.ps1') -Mode $mode -KeepDatabase:$FullLocalValidation
        }
    }

    $dirty = @(git status --porcelain)
    if ($dirty.Count -gt 0) {
        if ($SkipCommit) {
            throw 'Existem alteracoes locais. Remova -SkipCommit ou commite manualmente antes do deploy.'
        }

        if ([string]::IsNullOrWhiteSpace($CommitMessage)) {
            throw 'Informe -CommitMessage "mensagem curta" para commitar e subir as alteracoes.'
        }

        Invoke-Checked 'git add' { git add -A }
        $staged = @(git diff --cached --name-only)
        if ($staged.Count -gt 0) {
            Invoke-Checked 'git commit' { git commit -m $CommitMessage }
        }
    } elseif (-not [string]::IsNullOrWhiteSpace($CommitMessage)) {
        Write-Host 'Sem alteracoes locais para commitar.'
    }

    Invoke-Checked 'git push' { git push -u origin $branch }

    $identityPath = Join-Path $Script:FarmfortRepoRoot $IdentityFile
    if (-not (Test-Path -LiteralPath $identityPath)) {
        throw "Chave SSH nao encontrada: $identityPath"
    }

    $remoteTests = if ($RunRemoteTests) { '1' } else { '0' }
    $remoteMigrations = if ($RunMigrations) { '1' } else { '0' }
    $remoteCommand = "cd '$RemoteRepo' && git fetch --prune origin && git checkout '$branch' && git pull --ff-only origin '$branch' && RUN_TESTS=$remoteTests RUN_MIGRATIONS=$remoteMigrations ./deploy/development.sh 'origin/$branch'"

    Invoke-Checked "deploy rapido em $Server" {
        ssh -i $identityPath -o BatchMode=yes -o StrictHostKeyChecking=accept-new $Server $remoteCommand
    }

    Write-Host ""
    Write-Host "Deploy rapido concluido na branch $branch."
} finally {
    Pop-Location
}

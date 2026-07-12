param(
    [ValidateSet('fast', 'full')]
    [string] $Validation = 'full',

    [switch] $SyncSchema,
    [string] $CommitMessage,
    [switch] $StageAll,
    [switch] $Push,
    [switch] $DeployProduction,
    [switch] $ConfirmProduction,
    [string] $Server = 'higor@192.168.17.65',
    [string] $IdentityFile = 'work\runtime\ssh\farmfort_dev_ed25519'
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'activate.ps1') -Quiet

Push-Location $Script:FarmfortRepoRoot
try {
    if ($SyncSchema) {
        & (Join-Path $PSScriptRoot 'sync-schema.ps1') -Server $Server -IdentityFile $IdentityFile
    }

    & (Join-Path $PSScriptRoot 'validate.ps1') -Mode $Validation

    $branch = (git branch --show-current).Trim()
    if ($branch -eq '') {
        throw 'Could not determine current Git branch.'
    }

    if ($CommitMessage) {
        if ($StageAll) {
            git add -A
        }

        $staged = @(git diff --cached --name-only)
        if ($staged.Count -gt 0) {
            git commit -m $CommitMessage
        } else {
            $dirty = @(git status --porcelain)
            if ($dirty.Count -gt 0) {
                throw 'There are local changes, but none are staged. Stage explicit files with git add or pass -StageAll intentionally.'
            }

            Write-Host 'No changes to commit.'
        }
    }

    if ($Push) {
        if ($branch -eq 'main') {
            throw 'Direct push to origin/main is blocked. Use a feature branch, PR, CI and homologation first.'
        }

        git push -u origin $branch
    }

    if ($DeployProduction) {
        if (-not $ConfirmProduction) {
            throw 'Production deploy requires -ConfirmProduction.'
        }

        if ($branch -ne 'main') {
            throw "Production deploy is allowed only from local main. Current branch: $branch."
        }

        if ((git status --porcelain).Count -gt 0) {
            throw 'Working tree must be clean before production deploy.'
        }

        $identityPath = Join-Path $Script:FarmfortRepoRoot $IdentityFile
        if (-not (Test-Path -LiteralPath $identityPath)) {
            throw "SSH key not found: $identityPath."
        }

        ssh -i $identityPath -o BatchMode=yes $Server 'cd /home/higor/Sistema-ERP-rural-Laravel && git fetch --prune origin && git checkout main && git pull --ff-only origin main && ./deploy/production.sh origin/main'
        if ($LASTEXITCODE -ne 0) {
            throw 'Production deploy failed.'
        }
    }
} finally {
    Pop-Location
}

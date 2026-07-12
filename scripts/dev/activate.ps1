param(
    [switch] $Quiet
)

$Script:FarmfortRepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Script:FarmfortRuntime = Join-Path $Script:FarmfortRepoRoot 'work\runtime'
$Script:FarmfortPhp = Join-Path $Script:FarmfortRuntime 'php84\php.exe'
$Script:FarmfortComposer = Join-Path $Script:FarmfortRuntime 'composer.phar'
$Script:FarmfortNodeDir = Join-Path $Script:FarmfortRuntime 'node'
$Script:FarmfortNpm = Join-Path $Script:FarmfortNodeDir 'npm.cmd'
$Script:FarmfortGh = Join-Path $Script:FarmfortRuntime 'gh\bin\gh.exe'
$Script:FarmfortMariaDbDir = Join-Path $Script:FarmfortRuntime 'mariadb-11.8.6'
$Script:FarmfortMariaDbBin = Join-Path $Script:FarmfortMariaDbDir 'bin'

function Add-FarmfortPath {
    param([string] $Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    $parts = $env:Path -split ';' | Where-Object { $_ }
    if ($parts -notcontains $Path) {
        $env:Path = "$Path;$env:Path"
    }
}

Add-FarmfortPath $Script:FarmfortNodeDir
Add-FarmfortPath (Split-Path -Parent $Script:FarmfortGh)
Add-FarmfortPath $Script:FarmfortMariaDbBin
Add-FarmfortPath (Split-Path -Parent $Script:FarmfortPhp)

function global:php {
    & $Script:FarmfortPhp @args
}

function global:composer {
    & $Script:FarmfortPhp $Script:FarmfortComposer @args
}

function global:artisan {
    & $Script:FarmfortPhp (Join-Path $Script:FarmfortRepoRoot 'artisan') @args
}

function global:node {
    & (Join-Path $Script:FarmfortNodeDir 'node.exe') @args
}

function global:npm {
    & $Script:FarmfortNpm @args
}

function global:gh {
    & $Script:FarmfortGh @args
}

if (-not $Quiet) {
    Write-Host "FarmFort dev environment active for this PowerShell session."
    Write-Host "Repo: $Script:FarmfortRepoRoot"
    Write-Host "PHP:  $Script:FarmfortPhp"
    Write-Host "Node: $Script:FarmfortNodeDir"
    if (Test-Path -LiteralPath $Script:FarmfortGh) {
        Write-Host "gh:   $Script:FarmfortGh"
    }
}

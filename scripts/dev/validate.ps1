param(
    [ValidateSet('fast', 'full')]
    [string] $Mode = 'fast',

    [switch] $KeepDatabase
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'activate.ps1') -Quiet

function Invoke-Step {
    param(
        [string] $Name,
        [scriptblock] $Command
    )

    Write-Host ""
    Write-Host "==> $Name"
    $timer = [Diagnostics.Stopwatch]::StartNew()
    & $Command
    $exitCode = $LASTEXITCODE
    $timer.Stop()

    if ($exitCode -ne 0) {
        throw "$Name failed with exit code $exitCode."
    }

    Write-Host ("OK: {0} ({1:n1}s)" -f $Name, $timer.Elapsed.TotalSeconds)
}

function Invoke-Composer {
    param([Parameter(ValueFromRemainingArguments = $true)] [string[]] $Args)
    & $Script:FarmfortPhp $Script:FarmfortComposer @Args
}

function Invoke-Npm {
    param([Parameter(ValueFromRemainingArguments = $true)] [string[]] $Args)
    & $Script:FarmfortNpm @Args
}

function Invoke-Artisan {
    param([Parameter(ValueFromRemainingArguments = $true)] [string[]] $Args)
    & $Script:FarmfortPhp (Join-Path $Script:FarmfortRepoRoot 'artisan') @Args
}

function Assert-TestSchema {
    $client = Join-Path $Script:FarmfortMariaDbBin 'mariadb.exe'
    $requiredTables = @('propriedades', 'usuarios', 'usuario_propriedades', 'talhoes', 'safras')
    $quotedTables = ($requiredTables | ForEach-Object { "'$_'" }) -join ','
    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'farmflow_test' AND table_name IN ($quotedTables) ORDER BY table_name;"
    $existing = & $client '--host=127.0.0.1' '--port=3307' '--user=root' '--password=root' '--ssl=0' '--batch' '--skip-column-names' "--execute=$sql"
    $missing = $requiredTables | Where-Object { $existing -notcontains $_ }

    if ($missing.Count -gt 0) {
        throw "MariaDB is running, but farmflow_test is missing legacy schema tables: $($missing -join ', '). Import the production-like schema dump before running -Mode full."
    }
}

Push-Location $Script:FarmfortRepoRoot
try {
    if ($Mode -eq 'full') {
        $dbStarted = $false
        try {
            Invoke-Step 'start MariaDB test database' { & (Join-Path $PSScriptRoot 'test-db.ps1') start }
            $dbStarted = $true
            Invoke-Step 'check MariaDB legacy schema' { Assert-TestSchema }

            Invoke-Step 'composer validate' { Invoke-Composer validate --no-check-publish }
            Invoke-Step 'composer install' { Invoke-Composer install }
            Invoke-Step 'npm ci' { Invoke-Npm ci }
            Invoke-Step 'npm run build' { Invoke-Npm run build }
            Invoke-Step 'php artisan test' { Invoke-Artisan test }
            Invoke-Step 'php artisan route:list' { Invoke-Artisan route:list }
        } finally {
            if ($dbStarted -and -not $KeepDatabase) {
                & (Join-Path $PSScriptRoot 'test-db.ps1') stop
            }
        }

        return
    }

    if (-not (Test-Path -LiteralPath (Join-Path $Script:FarmfortRepoRoot 'vendor\autoload.php'))) {
        Invoke-Step 'composer install' { Invoke-Composer install }
    } else {
        Invoke-Step 'composer validate' { Invoke-Composer validate --no-check-publish }
    }

    if (-not (Test-Path -LiteralPath (Join-Path $Script:FarmfortRepoRoot 'node_modules'))) {
        Invoke-Step 'npm ci' { Invoke-Npm ci }
    }

    Invoke-Step 'npm run build' { Invoke-Npm run build }
    Invoke-Step 'node syntax check' { & (Join-Path $Script:FarmfortNodeDir 'node.exe') --check public\js\talhao-mapa.js }
    Invoke-Step 'php artisan test --testsuite=Unit' { Invoke-Artisan test --testsuite=Unit }
    Invoke-Step 'php artisan route:list' { Invoke-Artisan route:list }
} finally {
    Pop-Location
}

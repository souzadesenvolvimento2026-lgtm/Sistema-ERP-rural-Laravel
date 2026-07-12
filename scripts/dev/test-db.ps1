param(
    [ValidateSet('start', 'stop', 'status', 'restart')]
    [string] $Action = 'start'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Runtime = Join-Path $RepoRoot 'work\runtime'
$Downloads = Join-Path $Runtime 'downloads'
$MariaVersion = '11.8.6'
$MariaDir = Join-Path $Runtime "mariadb-$MariaVersion"
$DataDir = Join-Path $Runtime 'mariadb-data'
$LogOut = Join-Path $Runtime 'mariadb-test.out.log'
$LogErr = Join-Path $Runtime 'mariadb-test.err.log'
$Port = 3307
$Database = 'farmflow_test'
$SqlMode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'
$MariaZipUrl = 'https://dlm.mariadb.com/4574312/MariaDB/mariadb-11.8.6/winx64-packages/mariadb-11.8.6-winx64.zip'

function Assert-InRuntime {
    param([string] $Path)

    $runtimePath = (Resolve-Path $Runtime).Path
    if (Test-Path -LiteralPath $Path) {
        $resolved = (Resolve-Path $Path).Path
        if (-not $resolved.StartsWith($runtimePath, [StringComparison]::OrdinalIgnoreCase)) {
            throw "Refusing to touch path outside runtime: $resolved"
        }
    }
}

function Remove-RuntimePath {
    param([string] $Path)

    if (Test-Path -LiteralPath $Path) {
        Assert-InRuntime $Path
        Remove-Item -LiteralPath $Path -Recurse -Force
    }
}

function Install-MariaDbRuntime {
    $server = Join-Path $MariaDir 'bin\mariadbd.exe'
    if (Test-Path -LiteralPath $server) {
        return
    }

    New-Item -ItemType Directory -Force -Path $Downloads | Out-Null
    $zipPath = Join-Path $Downloads "mariadb-$MariaVersion-winx64.zip"

    if (-not (Test-Path -LiteralPath $zipPath)) {
        Write-Host "Downloading MariaDB $MariaVersion portable runtime..."
        Invoke-WebRequest -Uri $MariaZipUrl -OutFile $zipPath
    }

    $extractDir = Join-Path $Runtime 'mariadb-extract'
    Remove-RuntimePath $extractDir
    Remove-RuntimePath $MariaDir
    New-Item -ItemType Directory -Force -Path $extractDir | Out-Null

    Expand-Archive -LiteralPath $zipPath -DestinationPath $extractDir -Force
    $extractedRoot = Get-ChildItem -LiteralPath $extractDir -Directory | Select-Object -First 1
    if (-not $extractedRoot) {
        throw 'MariaDB archive did not contain an install directory.'
    }

    Move-Item -LiteralPath $extractedRoot.FullName -Destination $MariaDir
    Remove-RuntimePath $extractDir
}

function Test-TcpPort {
    $client = New-Object Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne(500)) {
            return $false
        }

        $client.EndConnect($async)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Invoke-MariaDb {
    param(
        [string] $Sql,
        [switch] $WithoutPassword
    )

    $client = Join-Path $MariaDir 'bin\mariadb.exe'
    $args = @(
        '--host=127.0.0.1',
        "--port=$Port",
        '--user=root',
        '--ssl=0',
        '--batch',
        '--skip-column-names'
    )

    if (-not $WithoutPassword) {
        $args += '--password=root'
    }

    $args += "--execute=$Sql"
    & $client @args
}

function Initialize-DataDir {
    if (Test-Path -LiteralPath (Join-Path $DataDir 'mysql')) {
        return
    }

    New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
    $installer = Join-Path $MariaDir 'bin\mariadb-install-db.exe'
    & $installer "--datadir=$DataDir" "--port=$Port" '--password=root' '--default-user'
    if ($LASTEXITCODE -ne 0) {
        throw 'MariaDB data directory initialization failed.'
    }
}

function Start-TestDatabase {
    Install-MariaDbRuntime
    Initialize-DataDir

    if (-not (Test-TcpPort)) {
        $server = Join-Path $MariaDir 'bin\mariadbd.exe'
        $args = @(
            '--no-defaults',
            "--datadir=$DataDir",
            "--port=$Port",
            '--bind-address=127.0.0.1',
            "--sql-mode=$SqlMode",
            '--console'
        )

        Write-Host "Starting MariaDB $MariaVersion on 127.0.0.1:$Port..."
        Start-Process -FilePath $server `
            -ArgumentList $args `
            -WorkingDirectory $MariaDir `
            -WindowStyle Hidden `
            -RedirectStandardOutput $LogOut `
            -RedirectStandardError $LogErr `
            -PassThru | Out-Null

        $deadline = (Get-Date).AddSeconds(30)
        while (-not (Test-TcpPort)) {
            if ((Get-Date) -gt $deadline) {
                if (Test-Path -LiteralPath $LogErr) {
                    Get-Content -LiteralPath $LogErr -Tail 80
                }
                throw "MariaDB did not answer on 127.0.0.1:$Port."
            }
            Start-Sleep -Milliseconds 500
        }
    }

    $setupSql = "CREATE DATABASE IF NOT EXISTS $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; SELECT CONCAT(VERSION(), CHAR(124), @@SESSION.sql_mode);"
    $result = Invoke-MariaDb -Sql $setupSql 2>$null
    if ($LASTEXITCODE -ne 0) {
        $bootstrapSql = "ALTER USER 'root'@'localhost' IDENTIFIED BY 'root'; ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY 'root'; ALTER USER 'root'@'::1' IDENTIFIED BY 'root'; FLUSH PRIVILEGES;"
        Invoke-MariaDb -Sql $bootstrapSql -WithoutPassword | Out-Null
        $result = Invoke-MariaDb -Sql $setupSql
    }

    $expected = "$MariaVersion-MariaDB|$SqlMode"
    if ($result -ne $expected) {
        throw "Unexpected MariaDB test server: $result"
    }

    Write-Host "MariaDB test database ready: $expected"
}

function Stop-TestDatabase {
    Install-MariaDbRuntime

    if (Test-TcpPort) {
        $admin = Join-Path $MariaDir 'bin\mariadb-admin.exe'
        & $admin '--host=127.0.0.1' "--port=$Port" '--user=root' '--password=root' '--ssl=0' shutdown 2>$null
        Start-Sleep -Seconds 1
    }

    $processes = Get-CimInstance Win32_Process | Where-Object {
        $_.CommandLine -like '*mariadbd.exe*' -and $_.CommandLine -like "*$DataDir*"
    }

    foreach ($process in $processes) {
        Stop-Process -Id $process.ProcessId -Force
    }

    Write-Host 'MariaDB test database stopped.'
}

function Show-Status {
    Install-MariaDbRuntime

    if (-not (Test-TcpPort)) {
        Write-Host 'MariaDB test database is stopped.'
        return
    }

    $result = Invoke-MariaDb -Sql 'SELECT CONCAT(VERSION(), CHAR(124), @@SESSION.sql_mode);'
    Write-Host "MariaDB test database is running: $result"
}

switch ($Action) {
    'start' { Start-TestDatabase }
    'stop' { Stop-TestDatabase }
    'status' { Show-Status }
    'restart' {
        Stop-TestDatabase
        Start-TestDatabase
    }
}

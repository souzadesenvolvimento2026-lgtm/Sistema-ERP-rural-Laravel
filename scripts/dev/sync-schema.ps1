param(
    [string] $Server = 'higor@192.168.17.65',
    [string] $IdentityFile = 'work\runtime\ssh\farmfort_dev_ed25519',
    [string] $RemoteAppDir = '/var/www/erp-rural/Sistema-ERP-rural-Laravel',
    [string] $Database = 'farmflow_test'
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'activate.ps1') -Quiet

$identityPath = Join-Path $Script:FarmfortRepoRoot $IdentityFile
if (-not (Test-Path -LiteralPath $identityPath)) {
    throw "SSH key not found: $identityPath. Create it once and authorize it on the server before syncing schema."
}

Push-Location $Script:FarmfortRepoRoot
try {
    & (Join-Path $PSScriptRoot 'test-db.ps1') start

    $schemaDir = Join-Path $Script:FarmfortRuntime 'schema'
    New-Item -ItemType Directory -Force -Path $schemaDir | Out-Null
    $schemaFile = Join-Path $schemaDir 'server-schema.sql'

    $phpCode = @'
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config("database.connections.".config("database.default"));

$host = (string) ($connection["host"] ?? "127.0.0.1");
$port = (string) ($connection["port"] ?? "3306");
$database = (string) ($connection["database"] ?? "");
$username = (string) ($connection["username"] ?? "");
$password = (string) ($connection["password"] ?? "");

if ($database === "" || $username === "") {
    fwrite(STDERR, "Database configuration is incomplete.\n");
    exit(1);
}

$dump = trim((string) shell_exec("command -v mariadb-dump || command -v mysqldump"));
if ($dump === "") {
    fwrite(STDERR, "mariadb-dump/mysqldump is not available on the server.\n");
    exit(1);
}

$command = "MYSQL_PWD=".escapeshellarg($password)
    ." ".escapeshellcmd($dump)
    ." --no-data --routines --triggers --events --single-transaction --skip-comments"
    ." --host=".escapeshellarg($host)
    ." --port=".escapeshellarg($port)
    ." --user=".escapeshellarg($username)
    ." ".escapeshellarg($database);

passthru($command, $exitCode);
exit($exitCode);
'@

    $encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($phpCode))
    $remoteCommand = "cd '$RemoteAppDir' && php -r 'eval(base64_decode(`$argv[1]));' '$encoded'"

    Write-Host "Exporting schema from $Server..."
    & ssh -i $identityPath -o BatchMode=yes -o StrictHostKeyChecking=accept-new $Server $remoteCommand > $schemaFile
    if ($LASTEXITCODE -ne 0) {
        throw 'Remote schema export failed.'
    }

    if ((Get-Item -LiteralPath $schemaFile).Length -lt 100) {
        throw "Schema export looks too small: $schemaFile"
    }

    $client = Join-Path $Script:FarmfortMariaDbBin 'mariadb.exe'
    & $client '--host=127.0.0.1' '--port=3307' '--user=root' '--password=root' '--ssl=0' "--execute=DROP DATABASE IF EXISTS $Database; CREATE DATABASE $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to reset local test database $Database."
    }

    Write-Host "Importing schema into local $Database..."
    Get-Content -LiteralPath $schemaFile -Raw | & $client '--host=127.0.0.1' '--port=3307' '--user=root' '--password=root' '--ssl=0' $Database
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to import schema into $Database."
    }

    $required = @('propriedades', 'usuarios', 'usuario_propriedades', 'talhoes', 'safras')
    $quoted = ($required | ForEach-Object { "'$_'" }) -join ','
    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$Database' AND table_name IN ($quoted) ORDER BY table_name;"
    $existing = & $client '--host=127.0.0.1' '--port=3307' '--user=root' '--password=root' '--ssl=0' '--batch' '--skip-column-names' "--execute=$sql"
    $missing = $required | Where-Object { $existing -notcontains $_ }

    if ($missing.Count -gt 0) {
        throw "Schema sync completed, but required tables are missing: $($missing -join ', ')."
    }

    Write-Host "Local test schema is ready in $Database."
} finally {
    Pop-Location
}

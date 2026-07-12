<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private static bool $databaseEnvironmentValidated = false;

    protected function setUp(): void
    {
        $this->assertSafeDatabaseEnvironmentVariables();

        parent::setUp();

        $this->assertSafeProductionLikeDatabase();
    }

    protected function setUpTraits()
    {
        $this->assertSafeProductionLikeDatabase();

        return parent::setUpTraits();
    }

    private function assertSafeDatabaseEnvironmentVariables(): void
    {
        $connection = $this->environmentValue('DB_CONNECTION');
        $database = $this->environmentValue('DB_DATABASE');
        $expectedConnection = strtolower($this->requiredProductionSetting('DB_PRODUCTION_CONNECTION'));
        $expectedVendor = strtolower($this->requiredProductionSetting('DB_PRODUCTION_VENDOR'));
        $expectedVersion = $this->requiredProductionSetting('DB_PRODUCTION_VERSION');
        $expectedSqlMode = $this->requiredProductionSetting('DB_PRODUCTION_SQL_MODE');

        if (! in_array($expectedConnection, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('DB_PRODUCTION_CONNECTION deve ser mysql ou mariadb.');
        }

        if ($connection !== $expectedConnection) {
            throw new RuntimeException(
                "Os feature tests devem usar o mesmo conector da produção: {$expectedConnection}.",
            );
        }

        if ($expectedVendor !== 'mariadb') {
            throw new RuntimeException('DB_PRODUCTION_VENDOR deve identificar o servidor real de produção: mariadb.');
        }

        $this->databaseCoreVersion($expectedVersion, 'esperada');
        $expectedModes = $this->normalizedSqlModes($expectedSqlMode);
        if (! in_array('ONLY_FULL_GROUP_BY', $expectedModes, true)) {
            throw new RuntimeException('DB_PRODUCTION_SQL_MODE deve manter ONLY_FULL_GROUP_BY habilitado.');
        }

        if (! str_ends_with(strtolower($database), '_test')) {
            throw new RuntimeException('Banco de teste inseguro: DB_DATABASE deve terminar com _test.');
        }

        if (file_exists(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Remova o cache de configuração antes dos testes: execute php artisan config:clear.');
        }
    }

    protected function assertSafeProductionLikeDatabase(): void
    {
        $connectionName = (string) config('database.default');
        $connection = config('database.connections.'.$connectionName, []);
        $expectedConnection = strtolower($this->requiredProductionSetting('DB_PRODUCTION_CONNECTION'));

        if (($connection['driver'] ?? null) !== $expectedConnection) {
            throw new RuntimeException(
                "O conector efetivo dos feature tests deve ser {$expectedConnection}.",
            );
        }

        if (self::$databaseEnvironmentValidated) {
            return;
        }

        $server = DB::selectOne(
            'SELECT DATABASE() AS database_name, @@SESSION.sql_mode AS sql_mode, VERSION() AS server_version',
        );
        $actualDatabase = (string) ($server->database_name ?? '');
        if (! str_ends_with(strtolower($actualDatabase), '_test')) {
            throw new RuntimeException("Banco efetivamente conectado é inseguro: {$actualDatabase}.");
        }

        $expectedVendor = strtolower($this->requiredProductionSetting('DB_PRODUCTION_VENDOR'));
        $actualVersion = trim((string) ($server->server_version ?? ''));
        $actualVendor = $this->databaseVendor($actualVersion);
        if (! hash_equals($expectedVendor, $actualVendor)) {
            throw new RuntimeException(
                "Servidor divergente da produção: esperado {$expectedVendor}, encontrado {$actualVendor}.",
            );
        }

        $expectedCoreVersion = $this->databaseCoreVersion(
            $this->requiredProductionSetting('DB_PRODUCTION_VERSION'),
            'esperada',
        );
        $actualCoreVersion = $this->databaseCoreVersion($actualVersion, 'encontrada');
        if (! hash_equals($expectedCoreVersion, $actualCoreVersion)) {
            throw new RuntimeException(
                "Versão do MariaDB divergente: esperada {$expectedCoreVersion}, encontrada {$actualCoreVersion}.",
            );
        }

        $expectedModes = $this->normalizedSqlModes(
            $this->requiredProductionSetting('DB_PRODUCTION_SQL_MODE'),
        );
        $actualModes = $this->normalizedSqlModes((string) ($server->sql_mode ?? ''));
        if ($expectedModes !== $actualModes) {
            $missing = array_values(array_diff($expectedModes, $actualModes));
            $extra = array_values(array_diff($actualModes, $expectedModes));

            throw new RuntimeException(sprintf(
                'sql_mode divergente da produção. Ausentes: %s. Extras: %s.',
                $missing ? implode(',', $missing) : 'nenhum',
                $extra ? implode(',', $extra) : 'nenhum',
            ));
        }

        self::$databaseEnvironmentValidated = true;
    }

    private function requiredProductionSetting(string $name): string
    {
        $value = $this->environmentValue($name);

        if ($value === '') {
            throw new RuntimeException("Defina {$name} com o valor versionado da produção.");
        }

        return $value;
    }

    private function environmentValue(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return trim((string) ($value === false ? '' : $value));
    }

    private function databaseVendor(string $version): string
    {
        if (str_contains(strtolower($version), 'mariadb')) {
            return 'mariadb';
        }

        if ($this->isVersionString($version)) {
            return 'mysql';
        }

        throw new RuntimeException("Não foi possível identificar o servidor do banco: {$version}.");
    }

    private function databaseCoreVersion(string $version, string $label): string
    {
        if (preg_match('/^(\d+\.\d+\.\d+)(?:$|[-+~\s])/', trim($version), $matches) !== 1) {
            throw new RuntimeException(
                "Versão do banco {$label} deve informar major.minor.patch: {$version}.",
            );
        }

        return $matches[1];
    }

    private function isVersionString(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:$|[-+~\s])/', trim($version)) === 1;
    }

    /**
     * @return list<string>
     */
    private function normalizedSqlModes(string $sqlMode): array
    {
        $modes = array_values(array_unique(array_filter(array_map(
            static fn (string $mode): string => strtoupper(trim($mode)),
            explode(',', $sqlMode),
        ))));
        sort($modes);

        return $modes;
    }
}

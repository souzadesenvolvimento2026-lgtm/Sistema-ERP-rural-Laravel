<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private static bool $databaseValidated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$databaseValidated) {
            return;
        }

        $connectionName = (string) config('database.default');
        $connection = (array) config('database.connections.'.$connectionName, []);
        $database = strtolower(trim((string) ($connection['database'] ?? '')));

        if (! preg_match('/(?:^|[_-])(?:test|testing|ci)(?:$|[_-])/', $database)) {
            throw new RuntimeException('Os testes foram bloqueados porque DB_DATABASE nao identifica um banco exclusivo de testes.');
        }

        if (in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $sqlMode = strtoupper((string) (DB::selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode ?? ''));
            if (! str_contains($sqlMode, 'ONLY_FULL_GROUP_BY')) {
                throw new RuntimeException('Os testes exigem ONLY_FULL_GROUP_BY habilitado no MySQL/MariaDB.');
            }
        }

        self::$databaseValidated = true;
    }
}

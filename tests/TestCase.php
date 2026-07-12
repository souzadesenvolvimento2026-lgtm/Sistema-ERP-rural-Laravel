<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private static bool $databaseEnvironmentValidated = false;

    protected function setUp(): void
    {
        $this->assertSafeDatabaseEnvironmentVariables();

        parent::setUp();

        $this->assertSafeProductionLikeDatabase();
        $this->seedAnonymousTestFixtures();
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

    private function seedAnonymousTestFixtures(): void
    {
        if (! app()->runningUnitTests()) {
            return;
        }

        foreach (['propriedades', 'usuarios', 'usuario_propriedades', 'talhoes', 'safras', 'safra_talhoes'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Schema de teste incompleto: tabela {$table} ausente. Execute scripts/dev/sync-schema.ps1.",
                );
            }
        }

        $propertyId = $this->anonymousPropertyId();
        $userIds = $this->anonymousUserIds($propertyId);
        $talhaoId = $this->anonymousTalhaoId($propertyId);
        $safraId = $this->anonymousSafraId($propertyId);

        DB::table('safra_talhoes')
            ->where('safra_id', $safraId)
            ->where('talhao_id', $talhaoId)
            ->where('propriedade_id', $propertyId)
            ->delete();

        if (Schema::hasTable('culturas') && ! DB::table('culturas')->where('nome', 'Soja teste')->exists()) {
            DB::table('culturas')->insert([
                'nome' => 'Soja teste',
                'unidade_producao' => 'sc',
            ]);
        }

        if (Schema::hasTable('categorias') && ! DB::table('categorias')->where('nome', 'Categoria teste')->exists()) {
            DB::table('categorias')->insert([
                'nome' => 'Categoria teste',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
        }

        if (Schema::hasTable('compradores') && ! DB::table('compradores')->where('nome', 'Comprador teste')->where('propriedade_id', $propertyId)->exists()) {
            DB::table('compradores')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Comprador teste',
                'documento' => '00000000000',
                'ativo' => 1,
            ]);
        }

        if (Schema::hasTable('maquinas') && ! DB::table('maquinas')->where('nome', 'Trator teste')->where('propriedade_id', $propertyId)->exists()) {
            DB::table('maquinas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Trator teste',
                'tipo' => 'trator',
                'marca_modelo' => 'Fixture automatizada',
                'identificacao' => 'TESTE-001',
                'valor_aquisicao' => 0,
                'controla_horimetro' => 1,
                'controla_odometro' => 0,
                'horimetro_atual' => 0,
                'odometro_atual' => 0,
                'ativo' => 1,
            ]);
        }

        foreach ($userIds as $userId) {
            DB::table('usuario_propriedades')->updateOrInsert([
                'usuario_id' => $userId,
                'propriedade_id' => $propertyId,
            ]);
        }
    }

    private function anonymousPropertyId(): int
    {
        $propertyId = DB::table('propriedades')
            ->where('nome', 'Fazenda teste')
            ->orderBy('id')
            ->value('id');

        $data = [
            'nome' => 'Fazenda teste',
            'municipio' => 'Rio Verde',
            'estado' => 'GO',
            'area_total' => 100,
            'responsavel' => 'Fixture automatizada',
            'cnpj_cpf' => '00000000000191',
            'plano' => 'premium',
            'pecuaria_ativa' => 0,
            'ativo' => 1,
            'latitude' => -17.79250000,
            'longitude' => -50.91920000,
            'regiao_cotacao' => 'Rio Verde/GO',
            'cotacao_soja' => 0,
            'cotacao_soja_auto' => 0,
        ];

        if ($propertyId) {
            DB::table('propriedades')->where('id', $propertyId)->update($data);

            return (int) $propertyId;
        }

        DB::table('propriedades')->insert($data);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * @return list<int>
     */
    private function anonymousUserIds(int $propertyId): array
    {
        $users = [
            ['nome' => 'Administrador Teste', 'email' => 'teste.admin@farmfort.local', 'perfil' => 'administrador_sistema'],
            ['nome' => 'Gerencia Teste', 'email' => 'teste.gerencia@farmfort.local', 'perfil' => 'gerencia_sistema'],
            ['nome' => 'Colaborador Teste', 'email' => 'teste.colaborador@farmfort.local', 'perfil' => 'colaborador_sistema'],
            ['nome' => 'Gestor Propriedade Teste', 'email' => 'teste.gestor@farmfort.local', 'perfil' => 'gestor_propriedade'],
            ['nome' => 'Visualizador Teste', 'email' => 'teste.visualizador@farmfort.local', 'perfil' => 'visualizador'],
        ];

        $ids = [];
        foreach ($users as $user) {
            $userId = DB::table('usuarios')->where('email', $user['email'])->value('id');
            $data = [
                'nome' => $user['nome'],
                'email' => $user['email'],
                'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
                'perfil' => $user['perfil'],
                'ativo' => 1,
                'sessao_token' => null,
                'sessao_atualizada_em' => null,
            ];

            if ($userId) {
                DB::table('usuarios')->where('id', $userId)->update($data);
            } else {
                DB::table('usuarios')->insert($data);
                $userId = DB::getPdo()->lastInsertId();
            }

            $userId = (int) $userId;
            DB::table('usuario_propriedades')->updateOrInsert([
                'usuario_id' => $userId,
                'propriedade_id' => $propertyId,
            ]);

            $ids[] = $userId;
        }

        return $ids;
    }

    private function anonymousTalhaoId(int $propertyId): int
    {
        $talhaoId = DB::table('talhoes')
            ->where('propriedade_id', $propertyId)
            ->where('nome', 'Talhao teste')
            ->orderBy('id')
            ->value('id');

        $coordinates = json_encode([[
            ['lat' => -17.7930, 'lng' => -50.9200],
            ['lat' => -17.7930, 'lng' => -50.9180],
            ['lat' => -17.7910, 'lng' => -50.9180],
            ['lat' => -17.7910, 'lng' => -50.9200],
        ]], JSON_THROW_ON_ERROR);

        $data = [
            'propriedade_id' => $propertyId,
            'nome' => 'Talhao teste',
            'area' => 10,
            'area_bruta' => 10,
            'area_excluida_ha' => 0,
            'descricao' => 'Fixture automatizada para testes',
            'ativo' => 1,
            'latitude' => -17.79200000,
            'longitude' => -50.91900000,
            'geometria_tipo' => 'polygon',
            'coordenadas_json' => $coordinates,
            'exclusoes_json' => null,
            'pivo_ativo' => 0,
        ];

        if ($talhaoId) {
            DB::table('talhoes')->where('id', $talhaoId)->update($data);

            return (int) $talhaoId;
        }

        DB::table('talhoes')->insert($data);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function anonymousSafraId(int $propertyId): int
    {
        $safraId = DB::table('safras')
            ->where('propriedade_id', $propertyId)
            ->where('descricao', 'Safra teste')
            ->orderBy('id')
            ->value('id');

        $data = [
            'propriedade_id' => $propertyId,
            'cultura_id' => null,
            'safra_referencia' => 'primeira',
            'descricao' => 'Safra teste',
            'data_inicio' => '2026-01-01',
            'data_fim' => null,
            'area_plantada' => 10,
            'producao_estimada' => 0,
            'producao_realizada' => 0,
            'preco_estimado' => 0,
            'status' => 'em_andamento',
            'observacoes' => 'Fixture automatizada para testes',
        ];

        if ($safraId) {
            DB::table('safras')->where('id', $safraId)->update($data);

            return (int) $safraId;
        }

        DB::table('safras')->insert($data);

        return (int) DB::getPdo()->lastInsertId();
    }
}

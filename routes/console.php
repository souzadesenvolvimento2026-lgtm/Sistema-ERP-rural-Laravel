<?php

use App\Services\CotacaoSojaService;
use App\Services\ProductionDatabaseBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command;

Artisan::command('farmfort:atualizar-cotacao-soja {--force}', function (CotacaoSojaService $service) {
    $resultado = $service->atualizarTodas((bool) $this->option('force'));

    $this->info(sprintf(
        '[%s] Cotacao soja: %d atualizada(s), %d falha(s).',
        now()->format('Y-m-d H:i:s'),
        (int) $resultado['atualizadas'],
        (int) $resultado['falhas']
    ));
})->purpose('Atualiza automaticamente a cotacao de soja das propriedades ativas');

Artisan::command('farmfort:verificar-banco {--mariadb-version=11.8}', function () {
    $dados = DB::selectOne('SELECT VERSION() AS versao, @@SESSION.sql_mode AS sql_mode, DATABASE() AS banco');
    $versaoEsperada = trim((string) $this->option('mariadb-version'));
    $versao = (string) ($dados->versao ?? '');
    $sqlMode = strtoupper((string) ($dados->sql_mode ?? ''));
    $banco = trim((string) ($dados->banco ?? ''));

    if ($banco === '') {
        $this->error('Nenhum banco foi selecionado.');

        return Command::FAILURE;
    }

    if ($versaoEsperada !== '' && ! str_starts_with($versao, $versaoEsperada)) {
        $this->error("Versao MariaDB incompativel: {$versao}. Esperada: {$versaoEsperada}.");

        return Command::FAILURE;
    }

    if (! str_contains($sqlMode, 'ONLY_FULL_GROUP_BY')) {
        $this->error('ONLY_FULL_GROUP_BY nao esta habilitado nesta conexao.');

        return Command::FAILURE;
    }

    $this->info("Banco validado: {$banco} | MariaDB {$versao} | ONLY_FULL_GROUP_BY ativo.");

    return Command::SUCCESS;
})->purpose('Valida a versao e o modo estrito do MariaDB antes da publicacao');

Artisan::command('farmfort:backup-banco {path}', function (ProductionDatabaseBackupService $service) {
    $service->backup((string) $this->argument('path'));
    $this->info('Backup do banco criado com sucesso.');

    return Command::SUCCESS;
})->purpose('Cria um backup consistente do banco de dados');

Artisan::command('farmfort:restaurar-banco {path}', function (ProductionDatabaseBackupService $service) {
    $service->restore((string) $this->argument('path'));
    $this->info('Banco restaurado com sucesso.');

    return Command::SUCCESS;
})->purpose('Restaura o banco usado pela aplicacao');

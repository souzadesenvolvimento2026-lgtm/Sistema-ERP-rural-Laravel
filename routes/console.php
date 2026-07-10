<?php

use App\Services\CotacaoSojaService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('farmfort:atualizar-cotacao-soja {--force}', function (CotacaoSojaService $service) {
    $resultado = $service->atualizarTodas((bool)$this->option('force'));

    $this->info(sprintf(
        '[%s] Cotacao soja: %d atualizada(s), %d falha(s).',
        now()->format('Y-m-d H:i:s'),
        (int)$resultado['atualizadas'],
        (int)$resultado['falhas']
    ));
})->purpose('Atualiza automaticamente a cotacao de soja das propriedades ativas');

<?php

namespace App\Http\Controllers;

use App\Services\RelatorioLancamentosService;
use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RelatorioLancamentosController extends Controller
{
    public function index(Request $request, RelatorioLancamentosService $service): View
    {
        return view('financeiro.relatorio-lancamentos.index', $service->dados(app(FarmContext::class)->propertyId(), $request));
    }

    public function exportar(Request $request, RelatorioLancamentosService $service)
    {
        return $service->exportar(app(FarmContext::class)->propertyId(), $request);
    }
}

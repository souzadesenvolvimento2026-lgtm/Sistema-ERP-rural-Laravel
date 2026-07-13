<?php

namespace App\Http\Controllers;

use App\Services\FinanceiroFormDataService;
use App\Services\FinanceiroPainelService;
use App\Services\ReceitaFinanceiraService;
use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceiroPainelController extends Controller
{
    public function index(
        Request $request,
        FinanceiroPainelService $service,
        FinanceiroFormDataService $formData,
        ReceitaFinanceiraService $receitas
    ): View
    {
        $propertyId = app(FarmContext::class)->propertyId();
        $dados = $service->dados($propertyId, $request);
        $dados['lancamentoForm'] = $formData->options($propertyId, $receitas->listarCompradores($propertyId));

        return view('financeiro.index', $dados);
    }
}

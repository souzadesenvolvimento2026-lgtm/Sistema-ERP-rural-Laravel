<?php

namespace App\Http\Controllers;

use App\Services\LivroCaixaService;
use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LivroCaixaController extends Controller
{
    public function index(Request $request, LivroCaixaService $service): View
    {
        return view('financeiro.livro-caixa.index', $service->dados(app(FarmContext::class)->propertyId(), $request));
    }

    public function exportar(Request $request, LivroCaixaService $service): Response
    {
        return $service->exportar(app(FarmContext::class)->propertyId(), $request);
    }
}

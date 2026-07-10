<?php

namespace App\Http\Controllers;

use App\Services\AnaliseDespesasService;
use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnaliseDespesasController extends Controller
{
    public function index(Request $request, AnaliseDespesasService $service): View
    {
        return view('financeiro.analise-despesas.index', $service->dados(app(FarmContext::class)->propertyId(), $request));
    }
}

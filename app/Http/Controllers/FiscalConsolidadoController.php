<?php

namespace App\Http\Controllers;

use App\Services\FiscalConsolidadoService;
use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FiscalConsolidadoController extends Controller
{
    public function index(Request $request, FiscalConsolidadoService $service): View
    {
        return view('fiscal.consolidado.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }
}

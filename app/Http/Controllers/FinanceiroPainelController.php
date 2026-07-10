<?php

namespace App\Http\Controllers;

use App\Services\FinanceiroPainelService;
use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceiroPainelController extends Controller
{
    public function index(Request $request, FinanceiroPainelService $service): View
    {
        return view('financeiro.index', $service->dados(app(FarmContext::class)->propertyId(), $request));
    }
}

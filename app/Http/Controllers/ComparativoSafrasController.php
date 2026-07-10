<?php

namespace App\Http\Controllers;

use App\Services\ComparativoSafrasService;
use Illuminate\Http\Request;

class ComparativoSafrasController extends Controller
{
    public function index(Request $request, ComparativoSafrasService $service)
    {
        if (in_array((string)$request->query('export'), ['csv', 'excel', 'pdf'], true)) {
            return $service->exportar($request, (string)$request->query('export'));
        }

        return view('relatorios.comparativo-safras.index', $service->dados($request));
    }

    public function exportar(Request $request, ComparativoSafrasService $service)
    {
        return $service->exportar($request, (string)$request->query('formato', 'csv'));
    }
}

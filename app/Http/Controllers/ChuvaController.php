<?php

namespace App\Http\Controllers;

use App\Services\ChuvaService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChuvaController extends Controller
{
    public function index(Request $request, ChuvaService $service): View
    {
        return view('talhoes.chuva.index', $service->pagina(
            app(FarmContext::class)->propertyId(),
            $request->integer('ano') ?: null
        ));
    }

    public function store(Request $request, ChuvaService $service): RedirectResponse
    {
        $dados = $request->validate([
            'talhao_id' => ['nullable', 'integer'],
            'data_chuva' => ['required', 'date'],
            'volume_mm' => ['required', 'string'],
            'fonte' => ['required', 'in:manual,pluviometro,estacao,importado'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $service->criar($dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('talhoes.chuva.index', ['ano' => date('Y', strtotime($dados['data_chuva']))])
            ->with('success', 'Registro de chuva salvo pelo Laravel.');
    }
}

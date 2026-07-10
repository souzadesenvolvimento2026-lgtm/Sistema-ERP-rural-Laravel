<?php

namespace App\Http\Controllers;

use App\Services\AgendaFinanceiraService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaFinanceiraController extends Controller
{
    public function index(Request $request, AgendaFinanceiraService $service): View
    {
        return view('financeiro.agenda.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function pagarDespesa(Request $request, AgendaFinanceiraService $service): RedirectResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
            'data_pagamento' => ['nullable', 'date'],
            'conta_id' => ['nullable', 'integer'],
        ]);

        $service->pagarDespesa($dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('financeiro.agenda.index')
            ->with('success', 'Pagamento confirmado.');
    }

    public function receberReceita(Request $request, AgendaFinanceiraService $service): RedirectResponse
    {
        $dados = $request->validate([
            'id' => ['required', 'integer'],
            'data_recebimento' => ['nullable', 'date'],
            'conta_id' => ['nullable', 'integer'],
        ]);

        $service->receberReceita($dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('financeiro.agenda.index')
            ->with('success', 'Recebimento confirmado.');
    }
}

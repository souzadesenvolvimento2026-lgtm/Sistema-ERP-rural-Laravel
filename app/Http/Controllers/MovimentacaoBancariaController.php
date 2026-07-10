<?php

namespace App\Http\Controllers;

use App\Services\ContaBancariaService;
use App\Services\MovimentacaoBancariaService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovimentacaoBancariaController extends Controller
{
    public function index(MovimentacaoBancariaService $service, ContaBancariaService $contas): View
    {
        $propriedadeId = app(FarmContext::class)->propertyId();

        return view('financeiro.movimentacoes.index', array_merge(
            $service->pagina($propriedadeId),
            ['contas' => $contas->contasAtivas($propriedadeId)]
        ));
    }

    public function store(Request $request, MovimentacaoBancariaService $service): RedirectResponse
    {
        $dados = $request->validate([
            'conta_id' => ['required', 'integer'],
            'data_movimento' => ['required', 'date'],
            'tipo' => ['required', 'in:entrada,saida'],
            'descricao' => ['required', 'string', 'max:180'],
            'valor' => ['required', 'string'],
            'origem' => ['required', 'in:manual,extrato,ofx,csv'],
        ]);

        $service->criar($dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('financeiro.movimentacoes.index')
            ->with('success', 'Movimentação bancária criada pelo Laravel.');
    }

    public function conciliar(int $movimentacao, MovimentacaoBancariaService $service): RedirectResponse
    {
        $service->atualizarStatus($movimentacao, app(FarmContext::class)->propertyId(), 'conciliado');

        return redirect()
            ->route('financeiro.movimentacoes.index')
            ->with('success', 'Movimentação conciliada.');
    }

    public function ignorar(int $movimentacao, MovimentacaoBancariaService $service): RedirectResponse
    {
        $service->atualizarStatus($movimentacao, app(FarmContext::class)->propertyId(), 'ignorado');

        return redirect()
            ->route('financeiro.movimentacoes.index')
            ->with('success', 'Movimentação ignorada.');
    }
}

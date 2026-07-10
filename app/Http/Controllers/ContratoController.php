<?php

namespace App\Http\Controllers;

use App\Services\ContratoService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContratoController extends Controller
{
    public function index(ContratoService $service): View
    {
        return view('estoque-producao.contratos.index', $service->pagina(app(FarmContext::class)->propertyId()));
    }

    public function store(Request $request, ContratoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'safra_id' => ['nullable', 'integer'],
            'tipo' => ['required', 'in:venda,deposito,armazenagem,fixacao,compra'],
            'numero' => ['required', 'string', 'max:80'],
            'contraparte' => ['nullable', 'string', 'max:150'],
            'produto' => ['nullable', 'string', 'max:100'],
            'quantidade' => ['nullable', 'string'],
            'unidade' => ['nullable', 'string', 'max:30'],
            'preco_unitario' => ['nullable', 'string'],
            'valor_total' => ['nullable', 'string'],
            'data_contrato' => ['required', 'date'],
            'data_vencimento' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $service->criar($dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('estoque-producao.contratos.index')
            ->with('success', 'Contrato salvo pelo Laravel.');
    }

    public function entrega(Request $request, ContratoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'contrato_id' => ['required', 'integer'],
            'data_entrega' => ['required', 'date'],
            'quantidade' => ['required', 'string'],
            'unidade' => ['nullable', 'string', 'max:30'],
            'valor' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $service->registrarEntrega($dados, app(FarmContext::class)->propertyId());

        return redirect()
            ->route('estoque-producao.contratos.index')
            ->with('success', 'Entrega registrada pelo Laravel.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ContaBancariaService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ContaBancariaController extends Controller
{
    public function index(ContaBancariaService $service): View
    {
        return view('financeiro.contas.index', $service->pagina($this->propriedadeId()));
    }

    public function store(Request $request, ContaBancariaService $service): RedirectResponse
    {
        $service->criar($this->validated($request), $this->propriedadeId());

        return redirect()
            ->route('financeiro.contas.index')
            ->with('success', 'Conta bancária criada.');
    }

    public function edit(int $conta, ContaBancariaService $service): View
    {
        return view('financeiro.contas.edit', [
            ...$service->pagina($this->propriedadeId()),
            'conta' => $service->buscar($conta, $this->propriedadeId()),
        ]);
    }

    public function update(Request $request, int $conta, ContaBancariaService $service): RedirectResponse
    {
        $service->atualizar($conta, $this->validated($request), $this->propriedadeId());

        return redirect()
            ->route('financeiro.contas.index')
            ->with('success', 'Conta bancária atualizada.');
    }

    public function toggleStatus(int $conta, ContaBancariaService $service): RedirectResponse
    {
        $service->alternarStatus($conta, $this->propriedadeId());

        return redirect()
            ->route('financeiro.contas.index')
            ->with('success', 'Status da conta atualizado.');
    }

    public function transfer(Request $request, ContaBancariaService $service): RedirectResponse
    {
        $dados = $request->validate([
            'origem' => ['required', 'integer'],
            'destino' => ['required', 'integer'],
            'valor' => ['required', 'string'],
            'data_transferencia' => ['nullable', 'date'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->registrarTransferencia($dados, $this->propriedadeId(), session('usuario_id'));
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['transferencia' => $exception->getMessage()]);
        }

        return redirect()
            ->route('financeiro.contas.index')
            ->with('success', 'Transferencia registrada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'in:conta_corrente,conta_poupanca,caixa_interno,investimento'],
            'banco' => ['nullable', 'string', 'max:80'],
            'agencia' => ['nullable', 'string', 'max:20'],
            'numero_conta' => ['nullable', 'string', 'max:30'],
            'saldo_inicial' => ['nullable', 'string'],
        ]);
    }

    private function propriedadeId(): int
    {
        return app(FarmContext::class)->propertyId();
    }
}

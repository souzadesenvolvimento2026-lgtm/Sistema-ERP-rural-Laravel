<?php

namespace App\Http\Controllers;

use App\Services\DespesaFinanceiraService;
use App\Services\FinanceiroFormDataService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DespesaFinanceiraController extends Controller
{
    public function __construct(private readonly FinanceiroFormDataService $formDataService) {}

    public function index(Request $request, DespesaFinanceiraService $service): View
    {
        return view('financeiro.despesas.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function edit(Request $request, int $despesa, DespesaFinanceiraService $service): View|RedirectResponse
    {
        $propertyId = app(FarmContext::class)->propertyId();

        try {
            $lancamento = $service->despesaParaEdicao($propertyId, $despesa);
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->redirectToFinancialPanel()->withErrors($exception->getMessage());
        }

        $viewData = [
            ...$this->formData($propertyId),
            'lancamento' => $lancamento,
            'despesa' => $despesa,
        ];

        if ($request->boolean('modal')) {
            return view('financeiro.despesas.partials.modal-edicao', [
                ...$viewData,
                'modalOnly' => true,
            ]);
        }

        return view('financeiro.despesas.edit', $viewData);
    }

    public function duplicate(int $despesa, DespesaFinanceiraService $service): View|RedirectResponse
    {
        $propertyId = app(FarmContext::class)->propertyId();

        try {
            $lancamento = $service->despesaParaEdicao($propertyId, $despesa);
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->redirectToFinancialPanel()->withErrors($exception->getMessage());
        }

        $lancamento->baixado = '0';
        $lancamento->data_pagamento = null;

        return view('financeiro.despesas.duplicate', [
            ...$this->formData($propertyId),
            'lancamento' => $lancamento,
            'despesa' => $despesa,
        ]);
    }

    public function update(Request $request, int $despesa, DespesaFinanceiraService $service): RedirectResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:despesa'],
            'descricao' => ['required', 'string', 'max:255'],
            'pessoa' => ['nullable', 'string', 'max:150'],
            'categoria_id' => ['required', 'integer'],
            'subcategoria_id' => ['nullable', 'integer'],
            'conta_id' => ['nullable', 'integer'],
            'safra_id' => ['nullable', 'integer'],
            'talhao_id' => ['nullable', 'integer'],
            'produtor_id' => ['nullable', 'integer'],
            'quantidade' => ['nullable', 'string'],
            'unidade' => ['nullable', 'string', 'max:30'],
            'preco_unitario' => ['nullable', 'string'],
            'valor_total' => ['nullable', 'string'],
            'data_lancamento' => ['required', 'date'],
            'data_vencimento' => ['nullable', 'date'],
            'forma_pagamento' => ['nullable', 'in:dinheiro,pix,boleto,cheque,transferencia,cartao'],
            'numero_parcelas' => ['nullable', 'integer', 'min:1', 'max:36'],
            'baixado' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string'],
        ]);

        try {
            $service->atualizar(app(FarmContext::class)->propertyId(), $despesa, $dados, session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors($exception->getMessage());
        }

        return $this->redirectToFinancialPanel($despesa)
            ->with('success', 'Despesa editada com sucesso.');
    }

    public function approve(int $despesa, DespesaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->aprovar(app(FarmContext::class)->propertyId(), $despesa, session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return $this->redirectToFinancialPanel($despesa)
            ->with('success', 'Despesa aprovada com sucesso.');
    }

    public function approveBatch(Request $request, DespesaFinanceiraService $service): RedirectResponse
    {
        try {
            $result = $service->aprovarLote(
                app(FarmContext::class)->propertyId(),
                $request->input('despesas', []),
                session('usuario_id')
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        $message = $result['aprovadas'].' despesa(s) aprovada(s).';
        if ($result['ignoradas'] > 0) {
            $message .= ' '.$result['ignoradas'].' item(ns) ignorado(s).';
        }

        return $this->redirectToFinancialPanel(null, ['aprovacao' => 'pendente'])
            ->with('success', $message);
    }

    public function reject(Request $request, int $despesa, DespesaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->reprovar(
                app(FarmContext::class)->propertyId(),
                $despesa,
                session('usuario_id'),
                $request->input('motivo_reprovacao')
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return $this->redirectToFinancialPanel($despesa)
            ->with('success', 'Despesa reprovada com sucesso.');
    }

    public function pay(Request $request, int $despesa, DespesaFinanceiraService $service): RedirectResponse
    {
        $dados = $request->validate([
            'conta_id' => ['required', 'integer'],
            'data_pagamento' => ['nullable', 'date'],
        ]);

        try {
            $service->pagar(
                app(FarmContext::class)->propertyId(),
                $despesa,
                (int) $dados['conta_id'],
                $dados['data_pagamento'] ?? null,
                session('usuario_id')
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return $this->redirectToFinancialPanel($despesa)
            ->with('success', 'Despesa marcada como paga com sucesso.');
    }

    public function cancel(int $despesa, DespesaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->cancelar(app(FarmContext::class)->propertyId(), $despesa, session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return $this->redirectToFinancialPanel($despesa)
            ->with('success', 'Despesa cancelada com sucesso.');
    }

    private function formData(int $propertyId): array
    {
        return [
            'activeModule' => 'financeiro',
            'tipoSelecionado' => 'despesa',
            ...$this->formDataService->options($propertyId),
        ];
    }

    private function redirectToFinancialPanel(?int $expenseId = null, array $params = []): RedirectResponse
    {
        return redirect()->route('financeiro.despesas.index', $params);
    }
}

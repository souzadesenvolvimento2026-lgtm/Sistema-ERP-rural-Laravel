<?php

namespace App\Http\Controllers;

use App\Services\FinanceiroFormDataService;
use App\Services\ReceitaFinanceiraService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ReceitaFinanceiraController extends Controller
{
    public function __construct(private readonly FinanceiroFormDataService $formDataService) {}

    public function index(Request $request, ReceitaFinanceiraService $service): View
    {
        return view('financeiro.receitas.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function storeBuyer(Request $request, ReceitaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->salvarComprador(app(FarmContext::class)->propertyId(), $request->validate([
                'nome' => ['required', 'string', 'max:150'],
                'documento' => ['nullable', 'string', 'max:30'],
                'return_route' => ['nullable', 'in:financeiro.index,financeiro.receitas.index'],
            ]));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route($request->input('return_route', 'financeiro.receitas.index'))
            ->with('success', 'Comprador cadastrado com sucesso.');
    }

    public function edit(int $receita, ReceitaFinanceiraService $service): View|RedirectResponse
    {
        $propertyId = app(FarmContext::class)->propertyId();

        try {
            $lancamento = $service->receitaParaEdicao($propertyId, $receita);
        } catch (RuntimeException $exception) {
            report($exception);

            return redirect()->route('financeiro.receitas.index')->withErrors($exception->getMessage());
        }

        return view('financeiro.receitas.edit', [
            ...$this->formData($propertyId, $service),
            'lancamento' => $lancamento,
            'receita' => $receita,
        ]);
    }

    public function duplicate(int $receita, ReceitaFinanceiraService $service): View|RedirectResponse
    {
        $propertyId = app(FarmContext::class)->propertyId();

        try {
            $lancamento = $service->receitaParaEdicao($propertyId, $receita);
        } catch (RuntimeException $exception) {
            report($exception);

            return redirect()->route('financeiro.receitas.index')->withErrors($exception->getMessage());
        }

        $lancamento->baixado = '0';
        $lancamento->data_vencimento = null;

        return view('financeiro.receitas.duplicate', [
            ...$this->formData($propertyId, $service),
            'lancamento' => $lancamento,
            'receita' => $receita,
        ]);
    }

    public function update(Request $request, int $receita, ReceitaFinanceiraService $service): RedirectResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:receita'],
            'descricao' => ['required', 'string', 'max:255'],
            'comprador_id' => ['nullable', 'integer'],
            'pessoa' => ['nullable', 'string', 'max:150'],
            'categoria_id' => ['nullable', 'integer'],
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
            $service->atualizar(app(FarmContext::class)->propertyId(), $receita, $dados, session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.receitas.index')
            ->with('success', 'Receita editada pelo Laravel.');
    }

    public function approve(int $receita, ReceitaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->aprovar(app(FarmContext::class)->propertyId(), $receita, session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.receitas.index')
            ->with('success', 'Receita aprovada pelo Laravel.');
    }

    public function approveBatch(Request $request, ReceitaFinanceiraService $service): RedirectResponse
    {
        try {
            $result = $service->aprovarLote(
                app(FarmContext::class)->propertyId(),
                $request->input('receitas', []),
                session('usuario_id')
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        $message = $result['aprovadas'].' receita(s) aprovada(s).';
        if ($result['ignoradas'] > 0) {
            $message .= ' '.$result['ignoradas'].' item(ns) ignorado(s).';
        }

        return redirect()
            ->route('financeiro.receitas.index', ['aprovacao' => 'pendente'])
            ->with('success', $message);
    }

    public function reject(Request $request, int $receita, ReceitaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->reprovar(
                app(FarmContext::class)->propertyId(),
                $receita,
                session('usuario_id'),
                $request->input('motivo_reprovacao')
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.receitas.index')
            ->with('success', 'Receita reprovada pelo Laravel.');
    }

    public function receive(Request $request, int $receita, ReceitaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->receber(
                app(FarmContext::class)->propertyId(),
                $receita,
                $request->integer('conta_id') ?: null,
                $request->input('data_recebimento'),
                session('usuario_id')
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.receitas.index')
            ->with('success', 'Receita marcada como recebida pelo Laravel.');
    }

    public function cancel(int $receita, ReceitaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->cancelar(app(FarmContext::class)->propertyId(), $receita, session('usuario_id'));
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.receitas.index')
            ->with('success', 'Receita cancelada pelo Laravel.');
    }

    private function formData(int $propertyId, ReceitaFinanceiraService $service): array
    {
        return [
            'activeModule' => 'financeiro',
            'tipoSelecionado' => 'receita',
            ...$this->formDataService->options($propertyId, $service->listarCompradores($propertyId)),
        ];
    }
}

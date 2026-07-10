<?php

namespace App\Http\Controllers;

use App\Services\DespesaFinanceiraService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class DespesaFinanceiraController extends Controller
{
    public function index(Request $request, DespesaFinanceiraService $service): View
    {
        return view('financeiro.despesas.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function edit(int $despesa, DespesaFinanceiraService $service): View|RedirectResponse
    {
        $propertyId = app(FarmContext::class)->propertyId();

        try {
            $lancamento = $service->despesaParaEdicao($propertyId, $despesa);
        } catch (RuntimeException $exception) {
            return redirect()->route('financeiro.despesas.index')->withErrors($exception->getMessage());
        }

        return view('financeiro.despesas.edit', [
            ...$this->formData($propertyId),
            'lancamento' => $lancamento,
            'despesa' => $despesa,
        ]);
    }

    public function duplicate(int $despesa, DespesaFinanceiraService $service): View|RedirectResponse
    {
        $propertyId = app(FarmContext::class)->propertyId();

        try {
            $lancamento = $service->despesaParaEdicao($propertyId, $despesa);
        } catch (RuntimeException $exception) {
            return redirect()->route('financeiro.despesas.index')->withErrors($exception->getMessage());
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
            return back()->withInput()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.despesas.index')
            ->with('success', 'Despesa editada pelo Laravel.');
    }

    public function approve(int $despesa, DespesaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->aprovar(app(FarmContext::class)->propertyId(), $despesa, session('usuario_id'));
        } catch (RuntimeException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.despesas.index')
            ->with('success', 'Despesa aprovada pelo Laravel.');
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
            return back()->withErrors($exception->getMessage());
        }

        $message = $result['aprovadas'].' despesa(s) aprovada(s).';
        if ($result['ignoradas'] > 0) {
            $message .= ' '.$result['ignoradas'].' item(ns) ignorado(s).';
        }

        return redirect()
            ->route('financeiro.despesas.index', ['aprovacao' => 'pendente'])
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
            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.despesas.index')
            ->with('success', 'Despesa reprovada pelo Laravel.');
    }

    public function pay(Request $request, int $despesa, DespesaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->pagar(
                app(FarmContext::class)->propertyId(),
                $despesa,
                $request->integer('conta_id') ?: null,
                $request->input('data_pagamento'),
                session('usuario_id')
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.despesas.index')
            ->with('success', 'Despesa marcada como paga pelo Laravel.');
    }

    public function cancel(int $despesa, DespesaFinanceiraService $service): RedirectResponse
    {
        try {
            $service->cancelar(app(FarmContext::class)->propertyId(), $despesa, session('usuario_id'));
        } catch (RuntimeException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return redirect()
            ->route('financeiro.despesas.index')
            ->with('success', 'Despesa cancelada pelo Laravel.');
    }

    private function formData(int $propertyId): array
    {
        return [
            'activeModule' => 'financeiro',
            'tipoSelecionado' => 'despesa',
            'categorias' => DB::table('categorias')->where('ativo', 1)->whereNull('categoria_pai_id')->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'subcategorias' => DB::table('categorias')->where('ativo', 1)->whereNotNull('categoria_pai_id')->orderBy('nome')->get(['id', 'categoria_pai_id', 'nome', 'tipo']),
            'contas' => DB::table('contas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'banco']),
            'compradores' => collect(),
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'talhoes' => DB::table('talhoes')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'produtores' => DB::table('produtores')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
        ];
    }
}

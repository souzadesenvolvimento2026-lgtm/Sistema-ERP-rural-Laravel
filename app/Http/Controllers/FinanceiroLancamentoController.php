<?php

namespace App\Http\Controllers;

use App\Services\FinanceiroFormDataService;
use App\Services\FinanceiroLancamentoService;
use App\Services\ReceitaFinanceiraService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class FinanceiroLancamentoController extends Controller
{
    public function create(Request $request, ReceitaFinanceiraService $receitas, FinanceiroFormDataService $formData): View
    {
        $propertyId = app(FarmContext::class)->propertyId();
        $tipo = in_array($request->query('tipo'), ['despesa', 'receita'], true) ? (string) $request->query('tipo') : 'despesa';

        return view('financeiro.lancamentos.create', [
            'activeModule' => 'financeiro',
            'tipoSelecionado' => $tipo,
            ...$formData->options($propertyId, $receitas->listarCompradores($propertyId)),
        ]);
    }

    public function store(Request $request, FinanceiroLancamentoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:despesa,receita'],
            'descricao' => ['required', 'string', 'max:255'],
            'comprador_id' => ['nullable', 'integer'],
            'pessoa' => ['nullable', 'string', 'max:150'],
            'categoria_id' => ['required_if:tipo,despesa', 'nullable', 'integer'],
            'subcategoria_id' => ['nullable', 'integer'],
            'maquina_id' => ['nullable', 'integer'],
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
            'data_recebimento' => ['nullable', 'date'],
            'forma_pagamento' => ['nullable', 'in:dinheiro,pix,boleto,cheque,transferencia,cartao'],
            'numero_parcelas' => ['nullable', 'integer', 'min:1', 'max:36'],
            'baixado' => ['nullable', 'boolean'],
            'tipo_receita' => ['nullable', 'in:graos,outras'],
            'nota_fiscal' => ['nullable', 'string', 'max:50'],
            'comprovante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'observacoes' => ['nullable', 'string'],
            'return_route' => ['nullable', 'in:financeiro.index'],
        ]);

        if ($this->valorTotalSemBaseDeCalculo($dados)) {
            return back()
                ->withInput()
                ->withErrors([
                    'valor_total' => 'Informe o valor total ou a quantidade e o valor unitário.',
                ]);
        }

        $dados['baixado'] = (bool) ($dados['baixado'] ?? false);

        try {
            $service->criar($dados, app(FarmContext::class)->propertyId(), session('usuario_id'), $request->file('comprovante'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors($exception->getMessage());
        }

        if (! empty($dados['return_route'])) {
            return redirect()
                ->route($dados['return_route'])
                ->with('success', 'Lançamento financeiro criado pelo Laravel.');
        }

        return redirect()
            ->route('modules.show', ['module' => 'financeiro'])
            ->with('success', 'Lançamento financeiro criado pelo Laravel.');
    }

    private function valorTotalSemBaseDeCalculo(array $dados): bool
    {
        $valorTotal = trim((string) ($dados['valor_total'] ?? ''));

        if ($valorTotal !== '') {
            return false;
        }

        if (($dados['tipo_receita'] ?? 'graos') === 'outras') {
            return true;
        }

        return trim((string) ($dados['quantidade'] ?? '')) === ''
            || trim((string) ($dados['preco_unitario'] ?? '')) === '';
    }
}

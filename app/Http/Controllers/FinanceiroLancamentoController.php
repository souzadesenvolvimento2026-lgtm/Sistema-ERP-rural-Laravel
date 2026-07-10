<?php

namespace App\Http\Controllers;

use App\Services\FinanceiroLancamentoService;
use App\Services\ReceitaFinanceiraService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceiroLancamentoController extends Controller
{
    public function create(Request $request, ReceitaFinanceiraService $receitas): View
    {
        $propertyId = app(FarmContext::class)->propertyId();
        $tipo = in_array($request->query('tipo'), ['despesa', 'receita'], true) ? (string)$request->query('tipo') : 'despesa';

        return view('financeiro.lancamentos.create', [
            'activeModule' => 'financeiro',
            'tipoSelecionado' => $tipo,
            'categorias' => DB::table('categorias')->where('ativo', 1)->whereNull('categoria_pai_id')->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'subcategorias' => DB::table('categorias')->where('ativo', 1)->whereNotNull('categoria_pai_id')->orderBy('nome')->get(['id', 'categoria_pai_id', 'nome', 'tipo']),
            'contas' => DB::table('contas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'banco']),
            'compradores' => $receitas->listarCompradores($propertyId),
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'talhoes' => DB::table('talhoes')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'produtores' => DB::table('produtores')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
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
            'conta_id' => ['nullable', 'integer'],
            'safra_id' => ['nullable', 'integer'],
            'talhao_id' => ['nullable', 'integer'],
            'produtor_id' => ['nullable', 'integer'],
            'quantidade' => ['nullable', 'string'],
            'unidade' => ['nullable', 'string', 'max:30'],
            'preco_unitario' => ['nullable', 'string'],
            'valor_total' => ['required_if:tipo,despesa', 'nullable', 'string'],
            'data_lancamento' => ['required', 'date'],
            'data_vencimento' => ['nullable', 'date'],
            'forma_pagamento' => ['nullable', 'in:dinheiro,pix,boleto,cheque,transferencia,cartao'],
            'numero_parcelas' => ['nullable', 'integer', 'min:1', 'max:36'],
            'baixado' => ['nullable', 'boolean'],
            'observacoes' => ['nullable', 'string'],
        ]);

        $dados['baixado'] = (bool)($dados['baixado'] ?? false);
        $service->criar($dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('modules.show', ['module' => 'financeiro'])
            ->with('success', 'Lançamento financeiro criado pelo Laravel.');
    }
}

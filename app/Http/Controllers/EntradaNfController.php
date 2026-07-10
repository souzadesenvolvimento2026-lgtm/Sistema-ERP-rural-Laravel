<?php

namespace App\Http\Controllers;

use App\Services\EntradaNfService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EntradaNfController extends Controller
{
    public function index(Request $request, EntradaNfService $service): View
    {
        return view('fiscal.entrada-nf.index', $service->pagina(
            app(FarmContext::class)->propertyId(),
            $request
        ));
    }

    public function create(): View
    {
        $propertyId = app(FarmContext::class)->propertyId();

        return view('fiscal.entrada-nf.create', [
            'activeModule' => 'fiscal',
            'categorias' => DB::table('categorias')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'contas' => DB::table('contas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('id')->get(['id', 'descricao']),
        ]);
    }

    public function store(Request $request, EntradaNfService $service): RedirectResponse
    {
        $dados = $request->validate([
            'numero' => ['required', 'string', 'max:40'],
            'serie' => ['nullable', 'string', 'max:20'],
            'chave_acesso' => ['nullable', 'string', 'max:44'],
            'data_emissao' => ['required', 'date'],
            'data_entrada' => ['required', 'date'],
            'data_vencimento' => ['nullable', 'date'],
            'fornecedor' => ['required', 'string', 'max:180'],
            'fornecedor_doc' => ['nullable', 'string', 'max:20'],
            'valor_total' => ['required', 'string'],
            'valor_produtos' => ['nullable', 'string'],
            'valor_frete' => ['nullable', 'string'],
            'valor_desconto' => ['nullable', 'string'],
            'valor_impostos' => ['nullable', 'string'],
            'valor_financeiro_final' => ['nullable', 'string'],
            'condicao_pagamento' => ['nullable', 'string', 'max:80'],
            'forma_pagamento' => ['required', 'in:dinheiro,pix,boleto,cheque,transferencia,cartao'],
            'conta_id' => ['nullable', 'integer'],
            'categoria_id' => ['nullable', 'integer'],
            'safra_id' => ['nullable', 'integer'],
            'centro_custo' => ['nullable', 'string', 'max:120'],
            'fazenda_unidade' => ['nullable', 'string', 'max:160'],
            'observacoes_nota' => ['nullable', 'string'],
            'observacoes_financeiras' => ['nullable', 'string'],
            'classificar_patrimonio' => ['nullable', 'boolean'],
            'patrimonio_nome' => ['nullable', 'string', 'max:180'],
            'patrimonio_tipo' => ['nullable', 'in:trator,colheitadeira,plantadeira,pulverizador,caminhao,implemento,outro'],
            'patrimonio_tipo_outro' => ['nullable', 'string', 'max:120'],
            'patrimonio_controla_horimetro' => ['nullable', 'boolean'],
            'patrimonio_controla_odometro' => ['nullable', 'boolean'],
            'item_descricao' => ['nullable', 'string', 'max:255'],
            'item_descricao_generica' => ['nullable', 'string', 'max:180'],
            'item_quantidade' => ['nullable', 'string'],
            'item_unidade' => ['nullable', 'string', 'max:30'],
            'item_valor_unitario' => ['nullable', 'string'],
            'item_valor_total' => ['nullable', 'string'],
        ]);

        $service->criarManual($dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('modules.show', ['module' => 'fiscal'])
            ->with('success', 'Entrada de NF criada pelo Laravel.');
    }

    public function show(int $entrada, EntradaNfService $service): View
    {
        return view('fiscal.entrada-nf.show', $service->detalhe(
            app(FarmContext::class)->propertyId(),
            $entrada
        ));
    }

    public function storeItem(Request $request, int $entrada, EntradaNfService $service): RedirectResponse
    {
        $dados = $request->validate([
            'produto_id' => ['nullable', 'integer'],
            'codigo_interno' => ['nullable', 'string', 'max:60'],
            'codigo_fornecedor' => ['nullable', 'string', 'max:80'],
            'descricao_nf' => ['nullable', 'string', 'max:255'],
            'descricao_generica' => ['nullable', 'string', 'max:180'],
            'descricao_detalhada' => ['nullable', 'string'],
            'descricao_interna' => ['nullable', 'string', 'max:255'],
            'descricao_uso' => ['nullable', 'in:generica,detalhada,interna'],
            'quantidade' => ['nullable', 'string'],
            'unidade' => ['nullable', 'string', 'max:30'],
            'valor_unitario' => ['nullable', 'string'],
            'valor_total' => ['nullable', 'string'],
            'desconto' => ['nullable', 'string'],
            'frete_rateado' => ['nullable', 'string'],
            'base_icms' => ['nullable', 'string'],
            'valor_icms' => ['nullable', 'string'],
            'base_pis' => ['nullable', 'string'],
            'valor_pis' => ['nullable', 'string'],
            'base_cofins' => ['nullable', 'string'],
            'valor_cofins' => ['nullable', 'string'],
            'valor_ipi' => ['nullable', 'string'],
            'total_liquido' => ['nullable', 'string'],
            'centro_custo' => ['nullable', 'string', 'max:120'],
            'fazenda_unidade' => ['nullable', 'string', 'max:160'],
            'safra_id' => ['nullable', 'integer'],
            'categoria_id' => ['nullable', 'integer'],
            'grupo' => ['nullable', 'string', 'max:100'],
            'subgrupo' => ['nullable', 'string', 'max:100'],
            'marca' => ['nullable', 'string', 'max:100'],
            'ncm' => ['nullable', 'string', 'max:10'],
            'cest' => ['nullable', 'string', 'max:10'],
            'cfop_entrada' => ['nullable', 'string', 'max:10'],
            'cst_icms' => ['nullable', 'string', 'max:10'],
            'cst_pis' => ['nullable', 'string', 'max:10'],
            'cst_cofins' => ['nullable', 'string', 'max:10'],
            'aliquota_icms' => ['nullable', 'string'],
            'aliquota_pis' => ['nullable', 'string'],
            'aliquota_cofins' => ['nullable', 'string'],
            'aliquota_ipi' => ['nullable', 'string'],
        ]);

        try {
            $service->adicionarItem(app(FarmContext::class)->propertyId(), $entrada, $dados);

            return redirect()
                ->route('fiscal.entrada-nf.show', ['entrada' => $entrada])
                ->with('success', 'Item adicionado a entrada de NF.');
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('fiscal.entrada-nf.show', ['entrada' => $entrada])
                ->withErrors($exception->getMessage());
        }
    }

    public function gerarParcelas(Request $request, int $entrada, EntradaNfService $service): RedirectResponse
    {
        $dados = $request->validate([
            'parcelas_qtd' => ['required', 'integer', 'min:1', 'max:120'],
            'primeiro_vencimento' => ['nullable', 'date'],
        ]);

        try {
            $service->gerarParcelas(
                app(FarmContext::class)->propertyId(),
                $entrada,
                (int)$dados['parcelas_qtd'],
                $dados['primeiro_vencimento'] ?? null
            );

            return redirect()
                ->route('fiscal.entrada-nf.show', ['entrada' => $entrada])
                ->with('success', 'Parcelas geradas para o financeiro da NF.');
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('fiscal.entrada-nf.show', ['entrada' => $entrada])
                ->withErrors($exception->getMessage());
        }
    }

    public function concluir(int $entrada, EntradaNfService $service): RedirectResponse
    {
        try {
            $service->concluir(
                app(FarmContext::class)->propertyId(),
                $entrada,
                session('usuario_id')
            );

            return redirect()
                ->route('fiscal.entrada-nf.show', ['entrada' => $entrada])
                ->with('success', 'Entrada de NF concluida e enviada para contas a pagar.');
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('fiscal.entrada-nf.show', ['entrada' => $entrada])
                ->withErrors($exception->getMessage());
        }
    }
}

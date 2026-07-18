<?php

namespace App\Http\Controllers;

use App\Services\ProdutoService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProdutoController extends Controller
{
    public function index(Request $request, ProdutoService $service): View
    {
        return view('produtos.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function create(ProdutoService $service): View
    {
        return view('produtos.create', [
            'activeModule' => 'estoque-produtos',
            ...$service->formOptions(),
        ]);
    }

    public function edit(int $produto, ProdutoService $service): View
    {
        return view('produtos.edit', [
            'activeModule' => 'estoque-produtos',
            'produto' => $service->buscar($produto, app(FarmContext::class)->propertyId()),
            ...$service->formOptions(),
        ]);
    }

    public function store(Request $request, ProdutoService $service): RedirectResponse
    {
        $dados = $request->validate($this->validationRules());

        $service->criar($dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('produtos.index')
            ->with('success', 'Produto criado pelo Laravel.');
    }

    public function update(Request $request, int $produto, ProdutoService $service): RedirectResponse
    {
        $dados = $request->validate($this->validationRules());

        $service->atualizar($produto, $dados, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('produtos.index')
            ->with('success', 'Produto atualizado.');
    }

    public function toggleStatus(int $produto, ProdutoService $service): RedirectResponse
    {
        $ativo = $service->alternarStatus($produto, app(FarmContext::class)->propertyId(), session('usuario_id'));

        return redirect()
            ->route('produtos.index')
            ->with('success', $ativo ? 'Produto ativado.' : 'Produto inativado.');
    }

    public function storeMovement(Request $request, int $produto, ProdutoService $service): RedirectResponse
    {
        $dados = $request->validate([
            'destino_tipo' => ['required', 'string', 'in:safra,patrimonio,ajuste'],
            'quantidade' => ['required', 'string', 'max:30'],
            'data_movimento' => ['required', 'date'],
            'safra_id' => ['nullable', 'integer'],
            'talhao_id' => ['nullable', 'integer'],
            'maquina_id' => ['nullable', 'integer'],
            'motivo' => ['nullable', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string', 'max:500'],
            'justificativa_sem_safra' => ['nullable', 'string', 'max:500'],
        ]);

        $service->registrarSaida(
            $produto,
            app(FarmContext::class)->propertyId(),
            $dados,
            session('usuario_id')
        );

        return redirect()
            ->route('produtos.index')
            ->with('success', 'Baixa de estoque registrada.');
    }

    private function validationRules(): array
    {
        return [
            'descricao_generica' => ['required', 'string', 'max:180'],
            'codigo_interno' => ['nullable', 'string', 'max:60'],
            'codigo_fornecedor' => ['nullable', 'string', 'max:80'],
            'descricao_original_nf' => ['nullable', 'string', 'max:255'],
            'descricao_detalhada' => ['nullable', 'string'],
            'unidade_medida' => ['nullable', 'string', 'max:30'],
            'categoria_id' => ['nullable', 'integer'],
            'grupo' => ['nullable', 'string', 'max:100'],
            'subgrupo' => ['nullable', 'string', 'max:100'],
            'marca' => ['nullable', 'string', 'max:100'],
            'ncm' => ['nullable', 'string', 'max:10'],
            'cest' => ['nullable', 'string', 'max:10'],
            'cfop_entrada' => ['nullable', 'string', 'max:10'],
            'cst_icms' => ['nullable', 'string', 'max:10'],
            'csosn' => ['nullable', 'string', 'max:10'],
            'cst_pis' => ['nullable', 'string', 'max:10'],
            'cst_cofins' => ['nullable', 'string', 'max:10'],
            'aliquota_icms' => ['nullable', 'string', 'max:20'],
            'aliquota_pis' => ['nullable', 'string', 'max:20'],
            'aliquota_cofins' => ['nullable', 'string', 'max:20'],
            'aliquota_ipi' => ['nullable', 'string', 'max:20'],
            'origem_mercadoria' => ['nullable', 'string', 'max:10'],
            'tipo_item' => ['nullable', 'string', 'max:20'],
            'codigo_anp' => ['nullable', 'string', 'max:20'],
            'informacoes_fiscais' => ['nullable', 'string'],
            'observacoes_fiscais' => ['nullable', 'string'],
            'descricao_interna' => ['nullable', 'string'],
        ];
    }
}

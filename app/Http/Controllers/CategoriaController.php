<?php

namespace App\Http\Controllers;

use App\Services\CategoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoriaController extends Controller
{
    public function index(CategoriaService $service): View
    {
        return view('financeiro.categorias.index', $service->pagina());
    }

    public function store(Request $request, CategoriaService $service): RedirectResponse
    {
        $service->criar($this->validated($request));

        return redirect()
            ->route('financeiro.categorias.index')
            ->with('success', 'Categoria criada pelo Laravel.');
    }

    public function update(int $categoria, Request $request, CategoriaService $service): RedirectResponse
    {
        $service->atualizar($categoria, $this->validated($request));

        return redirect()
            ->route('financeiro.categorias.index')
            ->with('success', 'Categoria atualizada.');
    }

    public function destroy(int $categoria, CategoriaService $service): RedirectResponse
    {
        $resultado = $service->excluirOuDesativar($categoria);

        return redirect()
            ->route('financeiro.categorias.index')
            ->with($resultado['type'], $resultado['message']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'categoria_pai_id' => ['nullable', 'integer'],
            'nome' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'in:insumo,manutencao,folha,servico,combustivel,administrativo,bancario,outros'],
            'cor' => ['nullable', 'string', 'max:7'],
            'icone' => ['nullable', 'string', 'max:40'],
            'ativo' => ['nullable', 'boolean'],
        ]);
    }
}

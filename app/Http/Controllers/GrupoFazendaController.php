<?php

namespace App\Http\Controllers;

use App\Services\GrupoFazendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrupoFazendaController extends Controller
{
    public function index(GrupoFazendaService $service): View
    {
        return view('propriedades.grupos.index', $service->pagina());
    }

    public function store(Request $request, GrupoFazendaService $service): RedirectResponse
    {
        $service->criar($this->validated($request));

        return redirect()
            ->route('propriedades.grupos.index')
            ->with('success', 'Grupo de fazendas criado pelo Laravel.');
    }

    public function update(int $grupo, Request $request, GrupoFazendaService $service): RedirectResponse
    {
        $service->atualizar($grupo, $this->validated($request));

        return redirect()
            ->route('propriedades.grupos.index')
            ->with('success', 'Grupo de fazendas atualizado.');
    }

    public function destroy(int $grupo, GrupoFazendaService $service): RedirectResponse
    {
        $service->desativar($grupo);

        return redirect()
            ->route('propriedades.grupos.index')
            ->with('success', 'Grupo de fazendas desativado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string'],
            'aprovador_usuario_id' => ['nullable', 'integer'],
            'ativo' => ['nullable', 'boolean'],
            'propriedades' => ['required', 'array', 'min:1'],
            'propriedades.*' => ['integer'],
        ]);
    }
}

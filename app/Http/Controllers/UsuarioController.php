<?php

namespace App\Http\Controllers;

use App\Services\UsuarioService;
use App\Support\FarmContext;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class UsuarioController extends Controller
{
    public function index(Request $request, UsuarioService $service): View
    {
        return view('usuarios.index', $service->pagina(app(FarmContext::class)->propertyId(), $request));
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'activeModule' => 'usuarios',
            'perfis' => UsuarioService::perfisPermitidos(),
        ]);
    }

    public function edit(int $usuario, UsuarioService $service): View
    {
        return view('usuarios.edit', [
            'activeModule' => 'usuarios',
            'usuario' => $service->buscar($usuario, app(FarmContext::class)->propertyId()),
            'perfis' => UsuarioService::perfisPermitidos(),
        ]);
    }

    public function store(Request $request, UsuarioService $service): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:usuarios,email'],
            'senha' => ['required', 'string', 'min:6', 'confirmed'],
            'perfil' => ['required', 'in:gestor_propriedade,gestor_financeiro,gestao,produtor,colaborador,financeiro,visualizador'],
        ]);

        try {
            $service->criar($dados, app(FarmContext::class)->propertyId(), (int)$request->session()->get('usuario_id'));
        } catch (RuntimeException $exception) {
            return back()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuário criado pelo Laravel.');
    }

    public function update(Request $request, int $usuario, UsuarioService $service): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('usuarios', 'email')->ignore($usuario)],
            'senha' => ['nullable', 'string', 'min:6', 'confirmed'],
            'perfil' => ['required', Rule::in(array_keys(UsuarioService::perfisPermitidos()))],
        ]);

        $service->atualizar($usuario, $dados, app(FarmContext::class)->propertyId(), (int)$request->session()->get('usuario_id'));

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuário atualizado.');
    }

    public function toggleStatus(Request $request, int $usuario, UsuarioService $service): RedirectResponse
    {
        $ativo = $service->alternarStatus($usuario, app(FarmContext::class)->propertyId(), (int)$request->session()->get('usuario_id'));

        return redirect()
            ->route('usuarios.index')
            ->with('success', $ativo ? 'Usuário ativado.' : 'Usuário inativado.');
    }
}

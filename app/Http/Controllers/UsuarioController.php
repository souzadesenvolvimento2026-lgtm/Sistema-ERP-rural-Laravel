<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use App\Services\UsuarioService;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class UsuarioController extends Controller
{
    public function __construct(private readonly ProfileAccess $access) {}

    public function index(Request $request, UsuarioService $service): View
    {
        return view('usuarios.index', $service->pagina(app(FarmContext::class)->propertyId(), $request, $this->isSystemAdmin()));
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'activeModule' => 'usuarios',
            'perfis' => $this->perfisDisponiveis(),
            'modoSistema' => $this->isSystemAdmin(),
        ]);
    }

    public function edit(int $usuario, UsuarioService $service): View
    {
        return view('usuarios.edit', [
            'activeModule' => 'usuarios',
            'usuario' => $this->isSystemAdmin()
                ? $service->buscarSistema($usuario)
                : $service->buscar($usuario, app(FarmContext::class)->propertyId()),
            'perfis' => $this->perfisDisponiveis(),
            'modoSistema' => $this->isSystemAdmin(),
        ]);
    }

    public function store(Request $request, UsuarioService $service): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:usuarios,email'],
            'senha' => ['required', 'string', 'min:6', 'confirmed'],
            'perfil' => ['required', Rule::in(array_keys($this->perfisDisponiveis()))],
        ]);

        try {
            if ($this->isSystemAdmin()) {
                $service->criarSistema($dados, (int) $request->session()->get('usuario_id'));
            } else {
                $service->criar($dados, app(FarmContext::class)->propertyId(), (int) $request->session()->get('usuario_id'));
            }
        } catch (RuntimeException $exception) {
            report($exception);

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
            'perfil' => ['required', Rule::in(array_keys($this->perfisDisponiveis()))],
        ]);

        if ($this->isSystemAdmin()) {
            $service->atualizarSistema($usuario, $dados, (int) $request->session()->get('usuario_id'));
        } else {
            $service->atualizar($usuario, $dados, app(FarmContext::class)->propertyId(), (int) $request->session()->get('usuario_id'));
        }

        return redirect()
            ->route('usuarios.index')
            ->with('success', 'Usuário atualizado.');
    }

    public function toggleStatus(Request $request, int $usuario, UsuarioService $service): RedirectResponse
    {
        $ativo = $this->isSystemAdmin()
            ? $service->alternarStatusSistema($usuario, (int) $request->session()->get('usuario_id'))
            : $service->alternarStatus($usuario, app(FarmContext::class)->propertyId(), (int) $request->session()->get('usuario_id'));

        return redirect()
            ->route('usuarios.index')
            ->with('success', $ativo ? 'Usuário ativado.' : 'Usuário inativado.');
    }

    private function isSystemAdmin(): bool
    {
        return $this->access->isSystemAdministrator((string) session('perfil'));
    }

    private function perfisDisponiveis(): array
    {
        return $this->isSystemAdmin() ? UsuarioService::perfisSistema() : UsuarioService::perfisPermitidos();
    }
}

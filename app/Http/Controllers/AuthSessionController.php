<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthSessionController extends Controller
{
    public function __construct(
        private readonly ProfileAccess $access,
        private readonly AuthenticationService $authentication,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (session('usuario_id')) {
            return redirect()->route($this->access->isSystemAdministrator((string) session('perfil')) ? 'admin.index' : 'dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $user = $this->authentication->authenticate($credentials['email'], $credentials['senha']);

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'E-mail ou senha inválidos.']);
        }

        $propertyId = $this->authentication->defaultPropertyId((int) $user->id, (string) $user->perfil);
        if ($propertyId === null && ! $this->access->isSystemAdministrator((string) $user->perfil)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Usuário sem propriedade ativa vinculada. Contate o administrador.']);
        }

        $request->session()->regenerate();
        session([
            'usuario_id' => (int) $user->id,
            'usuario_nome' => $user->nome,
            'perfil' => $user->perfil,
            'propriedade_id' => $propertyId,
        ]);

        $this->authentication->registerLogin(
            (int) $user->id,
            $propertyId,
            (string) $request->ip(),
        );

        $homeRoute = $this->access->isSystemAdministrator((string) $user->perfil)
            ? route('admin.index')
            : route('dashboard');

        return redirect()->intended($homeRoute);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $userId = (int) session('usuario_id');
        if ($userId > 0) {
            $this->authentication->registerLogout(
                $userId,
                session('propriedade_id') ? (int) session('propriedade_id') : null,
                (string) $request->ip(),
            );
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

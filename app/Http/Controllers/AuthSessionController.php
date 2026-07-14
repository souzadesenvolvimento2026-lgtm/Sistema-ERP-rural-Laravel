<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use App\Services\AuthenticationService;
use App\Services\RequestContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthSessionController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_DECAY_SECONDS = 60;

    public function __construct(
        private readonly ProfileAccess $access,
        private readonly AuthenticationService $authentication,
        private readonly RequestContextService $requestContext,
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

        $throttleKey = $this->throttleKey($request);
        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Muitas tentativas de login. Aguarde '.$seconds.' segundo(s) e tente novamente.',
                ]);
        }

        $user = $this->authentication->authenticate($credentials['email'], $credentials['senha']);

        if (! $user) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

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

        RateLimiter::clear($throttleKey);

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
            (string) ($this->requestContext->clientIp($request) ?? $request->ip()),
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
                (string) ($this->requestContext->clientIp($request) ?? $request->ip()),
            );
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email'))).'|'.($this->requestContext->clientIp($request) ?? $request->ip());
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\AuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFarmfortUserIsLoggedIn
{
    public function __construct(private readonly AuthenticationService $authentication) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->session()->get('usuario_id', 0);
        if ($userId <= 0) {
            return redirect()->route('login');
        }

        $user = $this->authentication->activeUser($userId);
        $sessionProfile = (string) $request->session()->get('perfil', '');
        $propertyId = $request->session()->get('propriedade_id');
        $propertyId = $propertyId ? (int) $propertyId : null;

        if (! $user
            || (string) $user->perfil !== $sessionProfile
            || ! $this->authentication->canAccessProperty($userId, $sessionProfile, $propertyId)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sessão expirada ou acesso revogado.'], 401);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Sua sessão expirou ou o acesso à propriedade foi revogado.',
            ]);
        }

        $request->session()->put('usuario_nome', $user->nome);

        return $next($request);
    }
}

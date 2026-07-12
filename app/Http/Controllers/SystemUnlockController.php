<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SystemUnlockController extends Controller
{
    public function __construct(
        private readonly ProfileAccess $access,
        private readonly AuthenticationService $authentication,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $returnTo = $this->returnTo($request);
        $perfil = (string) session('perfil', '');

        if (! $this->access->isSystemAdministrator($perfil)) {
            return redirect($returnTo)->with('error', 'Acesso restrito a administradores e gerentes do sistema.');
        }

        $senha = (string) $request->input('senha_confirmacao', '');
        if ($senha === '') {
            return redirect($returnTo)->with('error', 'Informe sua senha para liberar a edicao.');
        }

        $usuarioId = (int) session('usuario_id');
        if (! $this->authentication->verifyActiveUserPassword($usuarioId, $senha)) {
            return redirect($returnTo)->with('error', 'Senha incorreta. A edicao das propriedades continua bloqueada.');
        }

        session(['system_write_unlocked_until' => time() + (30 * 60)]);
        $this->authentication->registerSystemUnlock(
            $usuarioId,
            session('propriedade_id') ? (int) session('propriedade_id') : null,
            (string) $request->ip(),
        );

        return redirect($returnTo)->with('success', 'Edicao liberada por 30 minutos para as propriedades.');
    }

    private function returnTo(Request $request): string
    {
        $returnTo = (string) $request->input('return_to', route('dashboard'));

        if (str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        if (str_starts_with($returnTo, url('/'))) {
            return $returnTo;
        }

        return route('dashboard');
    }
}

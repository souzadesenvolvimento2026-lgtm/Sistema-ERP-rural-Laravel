<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use App\Services\AuditService;
use App\Services\AuthenticationService;
use App\Services\RequestContextService;
use App\Services\SystemWriteUnlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SystemUnlockController extends Controller
{
    public function __construct(
        private readonly ProfileAccess $access,
        private readonly AuthenticationService $authentication,
        private readonly RequestContextService $requestContext,
        private readonly SystemWriteUnlockService $writeUnlock,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $returnTo = $this->returnTo($request);
        $profile = (string) session('perfil', '');

        if (! $this->access->isSystemAdministrator($profile)) {
            return redirect($returnTo)->with('error', 'Acesso restrito a administradores e gerentes do sistema.');
        }

        $propertyId = (int) session('propriedade_id', 0);
        if ($propertyId <= 0) {
            return redirect($returnTo)->with('error', 'Selecione uma propriedade ativa para liberar a edição.');
        }

        $password = (string) $request->input('senha_confirmacao', '');
        if ($password === '') {
            return redirect($returnTo)->with('error', 'Informe sua senha para liberar a edição.');
        }

        $userId = (int) session('usuario_id');
        if (! $this->authentication->verifyActiveUserPassword($userId, $password)) {
            return redirect($returnTo)->with('error', 'Senha incorreta. A edição da propriedade continua bloqueada.');
        }

        $this->writeUnlock->unlock($request, $propertyId);
        $this->authentication->registerSystemUnlock(
            $userId,
            $propertyId,
            (string) ($this->requestContext->clientIp($request) ?? $request->ip()),
        );

        return redirect($returnTo)->with('success', 'Edição liberada por 5 minutos para a propriedade selecionada.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $profile = (string) session('perfil', '');
        if (! $this->access->isSystemAdministrator($profile)) {
            return response()->json(['message' => 'Acesso restrito.'], 403);
        }

        $propertyId = (int) session('propriedade_id', 0);
        $expiresAt = $this->writeUnlock->refresh($request, $propertyId);
        if ($expiresAt === null) {
            return response()->json([
                'message' => 'A liberação expirou. Digite a senha novamente.',
            ], 419);
        }

        AuditService::log(
            action: 'renovar_edicao_sistema',
            table: 'propriedades',
            recordId: $propertyId,
            propertyId: $propertyId,
            details: ['evento' => 'Renovou edição operacional por presença ativa.'],
            request: $request,
        );

        return response()->json([
            'message' => 'Edição renovada por mais 5 minutos.',
            'expires_at' => $expiresAt,
        ]);
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

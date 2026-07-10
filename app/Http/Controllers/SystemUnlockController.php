<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemUnlockController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $returnTo = $this->returnTo($request);
        $perfil = (string)session('perfil', '');

        if (!in_array($perfil, ['administrador_sistema', 'gerencia_sistema'], true)) {
            return redirect($returnTo)->with('error', 'Acesso restrito a administradores e gerentes do sistema.');
        }

        $senha = (string)$request->input('senha_confirmacao', '');
        if ($senha === '') {
            return redirect($returnTo)->with('error', 'Informe sua senha para liberar a edicao.');
        }

        $usuarioId = (int)session('usuario_id');
        $usuario = DB::table('usuarios')->where('id', $usuarioId)->where('ativo', 1)->first(['id', 'senha']);

        if (!$usuario || !password_verify($senha, (string)$usuario->senha)) {
            return redirect($returnTo)->with('error', 'Senha incorreta. A edicao das propriedades continua bloqueada.');
        }

        session(['system_write_unlocked_until' => time() + (30 * 60)]);
        $this->audit($usuarioId);

        return redirect($returnTo)->with('success', 'Edicao liberada por 30 minutos para as propriedades.');
    }

    private function returnTo(Request $request): string
    {
        $returnTo = (string)$request->input('return_to', route('dashboard'));

        if (str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        if (str_starts_with($returnTo, url('/'))) {
            return $returnTo;
        }

        return route('dashboard');
    }

    private function audit(int $usuarioId): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $usuarioId,
                'acao' => 'liberar_edicao_sistema',
                'tabela' => 'usuarios',
                'registro_id' => $usuarioId,
                'propriedade_id' => session('propriedade_id'),
                'detalhes' => 'Usuario liberou edicao operacional nas propriedades por senha',
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable) {
            // Auditoria nao deve impedir a liberacao operacional.
        }
    }
}

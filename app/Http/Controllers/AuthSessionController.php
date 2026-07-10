<?php

namespace App\Http\Controllers;

use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (session('usuario_id')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $user = DB::table('usuarios')
            ->where('email', trim($credentials['email']))
            ->where('ativo', 1)
            ->first();

        if (!$user || !password_verify($credentials['senha'], $user->senha)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'E-mail ou senha inválidos.']);
        }

        $request->session()->regenerate();
        session([
            'usuario_id' => (int)$user->id,
            'usuario_nome' => $user->nome,
            'perfil' => $user->perfil,
            'propriedade_id' => app(FarmContext::class)->propertyId(),
        ]);

        DB::table('usuarios')
            ->where('id', $user->id)
            ->update([
                'ultimo_acesso' => now(),
                'sessao_atualizada_em' => now(),
            ]);

        $this->audit('login', 'Login Laravel realizado', (int)$user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $userId = (int)session('usuario_id');
        if ($userId > 0) {
            $this->audit('logout', 'Logout Laravel realizado', $userId);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function audit(string $action, string $details, int $userId): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $userId,
                'acao' => $action,
                'tabela' => 'usuarios',
                'registro_id' => $userId,
                'propriedade_id' => session('propriedade_id'),
                'detalhes' => $details,
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable) {
            // Auditoria nao deve impedir login ou logout.
        }
    }
}

<?php

namespace App\Services;

use App\Domain\Access\ProfileAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AuthenticationService
{
    public function __construct(private readonly ProfileAccess $profiles) {}

    public function authenticate(string $email, string $password): ?object
    {
        $user = DB::table('usuarios')
            ->where('email', strtolower(trim($email)))
            ->where('ativo', 1)
            ->first();

        if (! $user || ! password_verify($password, (string) $user->senha)) {
            return null;
        }

        return $user;
    }

    public function defaultPropertyId(int $userId, string $profile): ?int
    {
        $propertyId = $this->accessibleProperties($userId, $profile)
            ->orderBy('p.id')
            ->value('p.id');

        return $propertyId === null ? null : (int) $propertyId;
    }

    public function activeUser(int $userId): ?object
    {
        return DB::table('usuarios')
            ->where('id', $userId)
            ->where('ativo', 1)
            ->first(['id', 'nome', 'perfil']);
    }

    public function canAccessProperty(int $userId, string $profile, ?int $propertyId): bool
    {
        if ($propertyId === null) {
            return $this->profiles->isSystemAdministrator($profile);
        }

        return $this->accessibleProperties($userId, $profile)
            ->where('p.id', $propertyId)
            ->exists();
    }

    public function verifyActiveUserPassword(int $userId, string $password): bool
    {
        $hash = DB::table('usuarios')
            ->where('id', $userId)
            ->where('ativo', 1)
            ->value('senha');

        return is_string($hash) && password_verify($password, $hash);
    }

    public function registerLogin(int $userId, ?int $propertyId, string $ip): void
    {
        DB::table('usuarios')
            ->where('id', $userId)
            ->update([
                'ultimo_acesso' => now(),
                'sessao_atualizada_em' => now(),
            ]);

        $this->audit('login', 'Login Laravel realizado', $userId, $propertyId, $ip);
    }

    public function registerLogout(int $userId, ?int $propertyId, string $ip): void
    {
        $this->audit('logout', 'Logout Laravel realizado', $userId, $propertyId, $ip);
    }

    public function registerSystemUnlock(int $userId, ?int $propertyId, string $ip): void
    {
        $this->audit(
            'liberar_edicao_sistema',
            'Usuario liberou edicao operacional nas propriedades por senha',
            $userId,
            $propertyId,
            $ip,
        );
    }

    private function accessibleProperties(int $userId, string $profile): Builder
    {
        $query = DB::table('propriedades as p')->where('p.ativo', 1);

        if (! $this->profiles->hasGlobalPropertyAccess($profile)) {
            $query->where(function ($access) use ($userId) {
                $access->whereExists(function ($direct) use ($userId) {
                    $direct->selectRaw('1')
                        ->from('usuario_propriedades as up')
                        ->whereColumn('up.propriedade_id', 'p.id')
                        ->where('up.usuario_id', $userId);
                })->orWhereExists(function ($group) use ($userId) {
                    $group->selectRaw('1')
                        ->from('usuario_grupos_fazendas as ugf')
                        ->join('grupos_fazendas as gf', 'gf.id', '=', 'ugf.grupo_id')
                        ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
                        ->whereColumn('gfp.propriedade_id', 'p.id')
                        ->where('ugf.usuario_id', $userId)
                        ->where('gf.ativo', 1);
                });
            });
        }

        return $query;
    }

    private function audit(string $action, string $details, int $userId, ?int $propertyId, string $ip): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $userId,
                'acao' => $action,
                'tabela' => 'usuarios',
                'registro_id' => $userId,
                'propriedade_id' => $propertyId,
                'detalhes' => $details,
                'ip' => $ip,
                'criado_em' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

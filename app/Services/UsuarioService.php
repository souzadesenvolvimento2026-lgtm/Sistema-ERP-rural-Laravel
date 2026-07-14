<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UsuarioService
{
    public function __construct(private readonly AuditService $audit) {}

    public static function perfisPermitidos(): array
    {
        return [
            'administrador' => 'Administrador',
            'gestor_propriedade' => 'Gestor da Propriedade',
            'gestor_financeiro' => 'Gestor Financeiro',
            'gestao' => 'Gestão',
            'produtor' => 'Produtor',
            'colaborador' => 'Colaborador',
            'financeiro' => 'Financeiro',
            'visualizador' => 'Visualizador',
        ];
    }

    public static function perfisSistema(): array
    {
        return [
            'administrador_sistema' => 'Administrador do Sistema',
            'gerencia_sistema' => 'Gerência do Sistema',
            'colaborador_sistema' => 'Colaborador FarmFort',
        ];
    }

    public function pagina(int $propriedadeId, Request $request, bool $modoSistema = false): array
    {
        if ($modoSistema) {
            return $this->paginaSistema($request);
        }

        $filtros = $this->filtros($request);
        $rows = $this->rows($propriedadeId, $filtros);

        return [
            'activeModule' => 'usuarios',
            'title' => 'Usuários',
            'subtitle' => 'Usuários e permissões da propriedade selecionada.',
            'tableTitle' => 'Usuários e permissões por fazenda',
            'panelTitle' => 'Usuários da propriedade',
            'emptyMessage' => 'Nenhum usuário encontrado para a propriedade atual.',
            'filtros' => $filtros,
            'rows' => $rows,
            'perfis' => self::perfisPermitidos(),
            'modoSistema' => false,
            'statusOptions' => [
                '' => 'Todos',
                'ativos' => 'Ativos',
                'inativos' => 'Inativos',
                'gestores' => 'Gestores',
            ],
            'cards' => [
                ['label' => 'Usuários', 'value' => (string) $rows->count(), 'tone' => 'success'],
                ['label' => 'Ativos', 'value' => (string) $rows->where('ativo', true)->count(), 'tone' => 'success'],
                ['label' => 'Gestores', 'value' => (string) $rows->whereIn('perfil_key', ['administrador', 'gestor_propriedade', 'gestor_financeiro', 'gestao'])->count(), 'tone' => 'warning'],
                ['label' => 'Visualizadores', 'value' => (string) $rows->where('perfil_key', 'visualizador')->count(), 'tone' => ''],
            ],
        ];
    }

    public function paginaSistema(Request $request): array
    {
        $filtros = $this->filtros($request);
        $rows = $this->rowsSistema($filtros);

        return [
            'activeModule' => 'usuarios',
            'title' => 'Usuários',
            'subtitle' => 'Logins internos FarmFort com acesso administrativo ao sistema.',
            'tableTitle' => 'Logins internos FarmFort',
            'panelTitle' => 'Usuários internos FarmFort',
            'emptyMessage' => 'Nenhum login interno encontrado.',
            'filtros' => $filtros,
            'rows' => $rows,
            'perfis' => self::perfisSistema(),
            'modoSistema' => true,
            'statusOptions' => [
                '' => 'Todos',
                'ativos' => 'Ativos',
                'inativos' => 'Inativos',
                'gestores' => 'Gestores',
            ],
            'cards' => [
                ['label' => 'Usuários internos', 'value' => (string) $rows->count(), 'tone' => 'success'],
                ['label' => 'Ativos', 'value' => (string) $rows->where('ativo', true)->count(), 'tone' => 'success'],
                ['label' => 'Administradores', 'value' => (string) $rows->where('perfil_key', 'administrador_sistema')->count(), 'tone' => 'warning'],
                ['label' => 'Gerência', 'value' => (string) $rows->where('perfil_key', 'gerencia_sistema')->count(), 'tone' => ''],
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioLogadoId = null): int
    {
        $this->validarLimitePropriedade($propriedadeId);

        $nome = trim($dados['nome']);
        $email = strtolower(trim($dados['email']));
        $perfil = $dados['perfil'] ?: 'visualizador';

        DB::table('usuarios')->insert([
            'nome' => $nome,
            'email' => $email,
            'senha' => Hash::make($dados['senha']),
            'perfil' => $perfil,
            'ativo' => 1,
        ]);

        $usuarioId = (int) DB::getPdo()->lastInsertId();
        DB::table('usuario_propriedades')->updateOrInsert([
            'usuario_id' => $usuarioId,
            'propriedade_id' => $propriedadeId,
        ]);

        $this->audit->registrar(
            $usuarioLogadoId,
            'salvar_usuario',
            'usuarios',
            $usuarioId,
            $propriedadeId,
            [
                'evento' => 'Usuário criado e vinculado automaticamente à propriedade atual.',
                'nome' => $nome,
                'email' => $email,
                'perfil' => $this->perfilLabel($perfil),
            ],
        );

        return $usuarioId;
    }

    public function criarSistema(array $dados, ?int $usuarioLogadoId = null): int
    {
        $nome = trim($dados['nome']);
        $email = strtolower(trim($dados['email']));
        $perfil = $dados['perfil'] ?: 'colaborador_sistema';

        DB::table('usuarios')->insert([
            'nome' => $nome,
            'email' => $email,
            'senha' => Hash::make($dados['senha']),
            'perfil' => $perfil,
            'ativo' => 1,
        ]);

        $usuarioId = (int) DB::getPdo()->lastInsertId();
        $this->audit->registrar(
            $usuarioLogadoId,
            'salvar_usuario',
            'usuarios',
            $usuarioId,
            (int) session('propriedade_id') ?: null,
            [
                'evento' => 'Login interno FarmFort criado.',
                'nome' => $nome,
                'email' => $email,
                'perfil' => $this->perfilLabel($perfil),
            ],
        );

        return $usuarioId;
    }

    public function buscar(int $usuarioId, int $propriedadeId): object
    {
        $usuario = DB::table('usuarios as u')
            ->where('u.id', $usuarioId)
            ->whereNotIn('u.perfil', array_keys(self::perfisSistema()))
            ->where(function ($query) use ($propriedadeId) {
                $this->scopeAcessoPropriedade($query, $propriedadeId);
            })
            ->select('u.id', 'u.nome', 'u.email', 'u.perfil', 'u.ativo')
            ->first();

        abort_if(! $usuario, 404);

        return $usuario;
    }

    public function buscarSistema(int $usuarioId): object
    {
        $usuario = DB::table('usuarios as u')
            ->whereIn('u.perfil', array_keys(self::perfisSistema()))
            ->where('u.id', $usuarioId)
            ->select('u.id', 'u.nome', 'u.email', 'u.perfil', 'u.ativo')
            ->first();

        abort_if(! $usuario, 404);

        return $usuario;
    }

    public function atualizar(int $usuarioId, array $dados, int $propriedadeId, ?int $usuarioLogadoId = null): void
    {
        $usuarioAnterior = $this->buscar($usuarioId, $propriedadeId);

        $nome = trim($dados['nome']);
        $email = strtolower(trim($dados['email']));
        $perfil = $dados['perfil'] ?: 'visualizador';
        $senhaAlterada = trim((string) ($dados['senha'] ?? '')) !== '';
        $payload = [
            'nome' => $nome,
            'email' => $email,
            'perfil' => $perfil,
        ];

        if ($senhaAlterada) {
            $payload['senha'] = Hash::make($dados['senha']);
        }

        DB::table('usuarios')->where('id', $usuarioId)->update($payload);

        $this->audit->registrar(
            $usuarioLogadoId,
            'salvar_usuario',
            'usuarios',
            $usuarioId,
            $propriedadeId,
            [
                'evento' => 'Usuário atualizado.',
                'antes' => [
                    'nome' => $usuarioAnterior->nome,
                    'email' => $usuarioAnterior->email,
                    'perfil' => $this->perfilLabel((string) $usuarioAnterior->perfil),
                ],
                'depois' => [
                    'nome' => $nome,
                    'email' => $email,
                    'perfil' => $this->perfilLabel($perfil),
                ],
            ],
        );

        if ($senhaAlterada) {
            $this->audit->registrar(
                $usuarioLogadoId,
                'alterar_senha_usuario',
                'usuarios',
                $usuarioId,
                $propriedadeId,
                [
                    'evento' => 'Senha de usuário alterada.',
                    'usuario' => $nome,
                    'email' => $email,
                ],
            );
        }
    }

    public function atualizarSistema(int $usuarioId, array $dados, ?int $usuarioLogadoId = null): void
    {
        $usuarioAnterior = $this->buscarSistema($usuarioId);
        $this->validarProtecaoAdministradorPrincipal($usuarioAnterior, $usuarioLogadoId);

        $nome = trim($dados['nome']);
        $email = strtolower(trim($dados['email']));
        $perfil = $dados['perfil'] ?: 'colaborador_sistema';
        $senhaAlterada = trim((string) ($dados['senha'] ?? '')) !== '';
        $payload = [
            'nome' => $nome,
            'email' => $email,
            'perfil' => $perfil,
        ];

        if ($senhaAlterada) {
            $payload['senha'] = Hash::make($dados['senha']);
        }

        DB::table('usuarios')->where('id', $usuarioId)->update($payload);

        $this->audit->registrar(
            $usuarioLogadoId,
            'salvar_usuario',
            'usuarios',
            $usuarioId,
            (int) session('propriedade_id') ?: null,
            [
                'evento' => 'Login interno FarmFort atualizado.',
                'antes' => [
                    'nome' => $usuarioAnterior->nome,
                    'email' => $usuarioAnterior->email,
                    'perfil' => $this->perfilLabel((string) $usuarioAnterior->perfil),
                ],
                'depois' => [
                    'nome' => $nome,
                    'email' => $email,
                    'perfil' => $this->perfilLabel($perfil),
                ],
            ],
        );

        if ($senhaAlterada) {
            $this->audit->registrar(
                $usuarioLogadoId,
                'alterar_senha_usuario',
                'usuarios',
                $usuarioId,
                (int) session('propriedade_id') ?: null,
                [
                    'evento' => 'Senha de login interno alterada.',
                    'usuario' => $nome,
                    'email' => $email,
                ],
            );
        }
    }

    public function alternarStatus(int $usuarioId, int $propriedadeId, ?int $usuarioLogadoId = null): bool
    {
        $usuario = $this->buscar($usuarioId, $propriedadeId);
        $ativo = (int) $usuario->ativo === 1 ? 0 : 1;

        DB::table('usuarios')->where('id', $usuarioId)->update(['ativo' => $ativo]);

        $this->audit->registrar(
            $usuarioLogadoId,
            $ativo === 1 ? 'ativar_usuario' : 'desativar_usuario',
            'usuarios',
            $usuarioId,
            $propriedadeId,
            [
                'evento' => $ativo === 1 ? 'Usuário ativado.' : 'Usuário desativado.',
                'nome' => $usuario->nome,
                'email' => $usuario->email,
            ],
        );

        return $ativo === 1;
    }

    public function alternarStatusSistema(int $usuarioId, ?int $usuarioLogadoId = null): bool
    {
        $usuario = $this->buscarSistema($usuarioId);
        $this->validarProtecaoAdministradorPrincipal($usuario, $usuarioLogadoId);

        $ativo = (int) $usuario->ativo === 1 ? 0 : 1;

        DB::table('usuarios')->where('id', $usuarioId)->update(['ativo' => $ativo]);

        $this->audit->registrar(
            $usuarioLogadoId,
            $ativo === 1 ? 'ativar_usuario' : 'desativar_usuario',
            'usuarios',
            $usuarioId,
            (int) session('propriedade_id') ?: null,
            [
                'evento' => $ativo === 1 ? 'Login interno ativado.' : 'Login interno inativado.',
                'nome' => $usuario->nome,
                'email' => $usuario->email,
            ],
        );

        return $ativo === 1;
    }

    private function filtros(Request $request): array
    {
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['', 'ativos', 'inativos', 'gestores'], true)) {
            $status = '';
        }

        return [
            'status' => $status,
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    private function rows(int $propriedadeId, array $filtros): Collection
    {
        $query = DB::table('usuarios as u')
            ->whereNotIn('u.perfil', array_keys(self::perfisSistema()))
            ->where(function ($query) use ($propriedadeId) {
                $this->scopeAcessoPropriedade($query, $propriedadeId);
            });

        if ($filtros['status'] === 'ativos') {
            $query->where('u.ativo', 1);
        } elseif ($filtros['status'] === 'inativos') {
            $query->where('u.ativo', 0);
        } elseif ($filtros['status'] === 'gestores') {
            $query->whereIn('u.perfil', ['administrador', 'gestor_propriedade', 'gestor_financeiro', 'gestao']);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('u.nome', 'like', $term)
                    ->orWhere('u.email', 'like', $term)
                    ->orWhere('u.perfil', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('u.ativo')
            ->orderBy('u.nome')
            ->get([
                'u.id',
                'u.nome',
                'u.email',
                'u.perfil',
                'u.ativo',
                'u.ultimo_acesso',
                'u.criado_em',
            ])
            ->map(fn ($row) => $this->normalizar($row, $propriedadeId));
    }

    private function rowsSistema(array $filtros): Collection
    {
        $query = DB::table('usuarios as u')
            ->whereIn('u.perfil', array_keys(self::perfisSistema()));

        if ($filtros['status'] === 'ativos') {
            $query->where('u.ativo', 1);
        } elseif ($filtros['status'] === 'inativos') {
            $query->where('u.ativo', 0);
        } elseif ($filtros['status'] === 'gestores') {
            $query->whereIn('u.perfil', ['administrador_sistema', 'gerencia_sistema']);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('u.nome', 'like', $term)
                    ->orWhere('u.email', 'like', $term)
                    ->orWhere('u.perfil', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('u.ativo')
            ->orderBy('u.nome')
            ->get([
                'u.id',
                'u.nome',
                'u.email',
                'u.perfil',
                'u.ativo',
                'u.ultimo_acesso',
                'u.criado_em',
            ])
            ->map(fn ($row) => $this->normalizar($row, null, true));
    }

    private function normalizar($row, ?int $propriedadeId = null, bool $modoSistema = false): object
    {
        $vinculos = $modoSistema ? $this->vinculosSistema() : $this->vinculosPropriedade((int) $row->id, (int) $propriedadeId);

        return (object) [
            'id' => (int) $row->id,
            'nome' => FarmFormat::value($row->nome),
            'email' => FarmFormat::value($row->email),
            'perfil_key' => (string) $row->perfil,
            'perfil' => $this->perfilLabel((string) $row->perfil),
            'ativo' => (int) $row->ativo === 1,
            'status' => (int) $row->ativo === 1 ? 'Ativo' : 'Inativo',
            'ultimo_acesso' => FarmFormat::date($row->ultimo_acesso),
            'criado_em' => FarmFormat::date($row->criado_em),
            'acesso_efetivo' => $vinculos['acesso_efetivo'],
            'vinculo_direto' => $vinculos['vinculo_direto'],
            'grupos' => $vinculos['grupos'],
        ];
    }

    /**
     * @return array{acesso_efetivo: string, vinculo_direto: string, grupos: string}
     */
    private function vinculosSistema(): array
    {
        return [
            'acesso_efetivo' => 'Sistema FarmFort',
            'vinculo_direto' => 'Interno',
            'grupos' => '-',
        ];
    }

    /**
     * @return array{acesso_efetivo: string, vinculo_direto: string, grupos: string}
     */
    private function vinculosPropriedade(int $usuarioId, int $propriedadeId): array
    {
        $propriedade = DB::table('usuario_propriedades as up')
            ->join('propriedades as p', 'p.id', '=', 'up.propriedade_id')
            ->where('up.usuario_id', $usuarioId)
            ->where('up.propriedade_id', $propriedadeId)
            ->value('p.nome');

        $grupos = DB::table('usuario_grupos_fazendas as ugf')
            ->join('grupos_fazendas as gf', 'gf.id', '=', 'ugf.grupo_id')
            ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
            ->where('ugf.usuario_id', $usuarioId)
            ->where('gfp.propriedade_id', $propriedadeId)
            ->where('gf.ativo', 1)
            ->distinct()
            ->orderBy('gf.nome')
            ->pluck('gf.nome')
            ->filter()
            ->values()
            ->implode(', ');

        $vinculoDireto = $propriedade ? (string) $propriedade : '-';
        $grupos = $grupos !== '' ? $grupos : '-';

        return [
            'acesso_efetivo' => $propriedade ? (string) $propriedade : ($grupos !== '-' ? 'Grupo: '.$grupos : '-'),
            'vinculo_direto' => $vinculoDireto,
            'grupos' => $grupos,
        ];
    }

    private function perfilLabel(string $perfil): string
    {
        return FarmFormat::statusLabel($perfil);
    }

    private function validarLimitePropriedade(int $propriedadeId): void
    {
        $propriedade = DB::table('propriedades')->where('id', $propriedadeId)->first(['nome', 'plano']);
        if (! $propriedade) {
            throw new RuntimeException('Propriedade não encontrada para vincular o usuário.');
        }

        $plano = (string) ($propriedade->plano ?: 'basico');
        $limite = ['basico' => 3, 'avancado' => 5, 'premium' => 10][$plano] ?? 3;
        $total = $this->contarUsuariosEfetivos($propriedadeId);

        if ($total >= $limite) {
            throw new RuntimeException('Limite de usuários do plano '.$this->planoLabel($plano).' atingido para '.$propriedade->nome.'.');
        }
    }

    private function contarUsuariosEfetivos(int $propriedadeId, ?int $ignorarUsuarioId = null): int
    {
        return DB::table('usuarios as u')
            ->where('u.ativo', 1)
            ->whereNotIn('u.perfil', array_keys(self::perfisSistema()))
            ->when($ignorarUsuarioId, fn ($query) => $query->where('u.id', '!=', $ignorarUsuarioId))
            ->where(function ($query) use ($propriedadeId) {
                $this->scopeAcessoPropriedade($query, $propriedadeId);
            })
            ->count();
    }

    private function planoLabel(string $plano): string
    {
        return ['basico' => 'Básico', 'avancado' => 'Avançado', 'premium' => 'Premium'][$plano] ?? 'Básico';
    }

    private function scopeAcessoPropriedade($query, int $propriedadeId): void
    {
        $query->whereExists(function ($sub) use ($propriedadeId) {
            $sub->selectRaw('1')
                ->from('usuario_propriedades as up')
                ->whereColumn('up.usuario_id', 'u.id')
                ->where('up.propriedade_id', $propriedadeId);
        })->orWhereExists(function ($sub) use ($propriedadeId) {
            $sub->selectRaw('1')
                ->from('usuario_grupos_fazendas as ugf')
                ->join('grupos_fazendas as gf', 'gf.id', '=', 'ugf.grupo_id')
                ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
                ->whereColumn('ugf.usuario_id', 'u.id')
                ->where('gf.ativo', 1)
                ->where('gfp.propriedade_id', $propriedadeId);
        });
    }

    private function validarProtecaoAdministradorPrincipal(object $usuarioAlvo, ?int $usuarioLogadoId): void
    {
        if ((string) $usuarioAlvo->perfil !== 'administrador_sistema') {
            return;
        }

        $perfilAtor = $usuarioLogadoId
            ? (string) DB::table('usuarios')->where('id', $usuarioLogadoId)->value('perfil')
            : '';

        if ($perfilAtor === 'gerencia_sistema') {
            throw new RuntimeException('Gerência do sistema não pode alterar ou desativar administrador principal.');
        }
    }
}

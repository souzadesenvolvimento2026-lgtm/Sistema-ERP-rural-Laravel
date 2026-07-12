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
    public static function perfisPermitidos(): array
    {
        return [
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
            'subtitle' => 'Gerenciamento dos logins vinculados à fazenda atual.',
            'filtros' => $filtros,
            'rows' => $rows,
            'statusOptions' => [
                '' => 'Todos',
                'ativos' => 'Ativos',
                'inativos' => 'Inativos',
                'gestores' => 'Gestores',
            ],
            'cards' => [
                ['label' => 'Usuários', 'value' => (string) $rows->count(), 'tone' => 'success'],
                ['label' => 'Ativos', 'value' => (string) $rows->where('ativo', true)->count(), 'tone' => 'success'],
                ['label' => 'Gestores', 'value' => (string) $rows->whereIn('perfil_key', ['gestor_propriedade', 'gestor_financeiro', 'gestao'])->count(), 'tone' => 'warning'],
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
            'emptyMessage' => 'Nenhum login interno encontrado.',
            'filtros' => $filtros,
            'rows' => $rows,
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

        $this->auditar(
            $usuarioLogadoId,
            'salvar_usuario',
            'usuarios',
            $usuarioId,
            $propriedadeId,
            'Usuario criado: '.$nome.' ('.$email.') - Perfil: '.$this->perfilLabel($perfil)
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
        $this->auditar(
            $usuarioLogadoId,
            'salvar_usuario',
            'usuarios',
            $usuarioId,
            (int) session('propriedade_id'),
            'Login interno FarmFort criado: '.$nome.' ('.$email.') - Perfil: '.$this->perfilLabel($perfil)
        );

        return $usuarioId;
    }

    public function buscar(int $usuarioId, int $propriedadeId): object
    {
        $usuario = DB::table('usuarios as u')
            ->where('u.id', $usuarioId)
            ->whereNotIn('u.perfil', ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'])
            ->where(function ($query) use ($propriedadeId) {
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

        $detalhes = 'Usuario atualizado: '.$nome.' ('.$email.') - Perfil: '.$this->perfilLabel($perfil);
        if ($usuarioAnterior->nome !== $nome || $usuarioAnterior->email !== $email || $usuarioAnterior->perfil !== $perfil) {
            $detalhes .= ' - Anterior: '.$usuarioAnterior->nome.' ('.$usuarioAnterior->email.') - Perfil: '.$this->perfilLabel((string) $usuarioAnterior->perfil);
        }

        $this->auditar($usuarioLogadoId, 'salvar_usuario', 'usuarios', $usuarioId, $propriedadeId, $detalhes);

        if ($senhaAlterada) {
            $this->auditar(
                $usuarioLogadoId,
                'alterar_senha_usuario',
                'usuarios',
                $usuarioId,
                $propriedadeId,
                'Senha alterada para o usuario: '.$nome.' ('.$email.')'
            );
        }
    }

    public function atualizarSistema(int $usuarioId, array $dados, ?int $usuarioLogadoId = null): void
    {
        $usuarioAnterior = $this->buscarSistema($usuarioId);
        $nome = trim($dados['nome']);
        $email = strtolower(trim($dados['email']));
        $perfil = $dados['perfil'] ?: 'colaborador_sistema';
        $payload = [
            'nome' => $nome,
            'email' => $email,
            'perfil' => $perfil,
        ];

        if (trim((string) ($dados['senha'] ?? '')) !== '') {
            $payload['senha'] = Hash::make($dados['senha']);
        }

        DB::table('usuarios')->where('id', $usuarioId)->update($payload);

        $this->auditar(
            $usuarioLogadoId,
            'salvar_usuario',
            'usuarios',
            $usuarioId,
            (int) session('propriedade_id'),
            'Login interno FarmFort atualizado: '.$nome.' ('.$email.') - Perfil: '.$this->perfilLabel($perfil).' - Anterior: '.$usuarioAnterior->nome.' ('.$usuarioAnterior->email.')'
        );
    }

    public function alternarStatus(int $usuarioId, int $propriedadeId, ?int $usuarioLogadoId = null): bool
    {
        $usuario = $this->buscar($usuarioId, $propriedadeId);
        $ativo = (int) $usuario->ativo === 1 ? 0 : 1;

        DB::table('usuarios')->where('id', $usuarioId)->update(['ativo' => $ativo]);

        $this->auditar(
            $usuarioLogadoId,
            $ativo === 1 ? 'ativar_usuario' : 'desativar_usuario',
            'usuarios',
            $usuarioId,
            $propriedadeId,
            ($ativo === 1 ? 'Usuario ativado: ' : 'Usuario desativado: ').$usuario->nome.' ('.$usuario->email.')'
        );

        return $ativo === 1;
    }

    public function alternarStatusSistema(int $usuarioId, ?int $usuarioLogadoId = null): bool
    {
        $usuario = $this->buscarSistema($usuarioId);
        $ativo = (int) $usuario->ativo === 1 ? 0 : 1;

        DB::table('usuarios')->where('id', $usuarioId)->update(['ativo' => $ativo]);

        $this->auditar(
            $usuarioLogadoId,
            $ativo === 1 ? 'ativar_usuario' : 'desativar_usuario',
            'usuarios',
            $usuarioId,
            (int) session('propriedade_id'),
            ($ativo === 1 ? 'Login interno ativado: ' : 'Login interno inativado: ').$usuario->nome.' ('.$usuario->email.')'
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
            ->whereNotIn('u.perfil', ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'])
            ->where(function ($query) use ($propriedadeId) {
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
            });

        if ($filtros['status'] === 'ativos') {
            $query->where('u.ativo', 1);
        } elseif ($filtros['status'] === 'inativos') {
            $query->where('u.ativo', 0);
        } elseif ($filtros['status'] === 'gestores') {
            $query->whereIn('u.perfil', ['gestor_propriedade', 'gestor_financeiro', 'gestao']);
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
            ->map(fn ($row) => $this->normalizar($row));
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
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function normalizar($row): object
    {
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
            throw new RuntimeException('Propriedade nao encontrada para vincular o usuario.');
        }

        $plano = (string) ($propriedade->plano ?: 'basico');
        $limite = ['basico' => 3, 'avancado' => 5, 'premium' => 10][$plano] ?? 3;
        $total = $this->contarUsuariosEfetivos($propriedadeId);

        if ($total >= $limite) {
            throw new RuntimeException('Limite de usuarios do plano '.$this->planoLabel($plano).' atingido para '.$propriedade->nome.'.');
        }
    }

    private function contarUsuariosEfetivos(int $propriedadeId, ?int $ignorarUsuarioId = null): int
    {
        return DB::table('usuarios as u')
            ->whereNotIn('u.perfil', ['administrador', 'administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'])
            ->when($ignorarUsuarioId, fn ($query) => $query->where('u.id', '!=', $ignorarUsuarioId))
            ->where(function ($query) use ($propriedadeId) {
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
            })
            ->count();
    }

    private function planoLabel(string $plano): string
    {
        return ['basico' => 'Basico', 'avancado' => 'Avancado', 'premium' => 'Premium'][$plano] ?? 'Basico';
    }

    private function auditar(?int $usuarioId, string $acao, string $tabela, int $registroId, int $propriedadeId, string $detalhes): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'propriedade_id' => $propriedadeId,
                'detalhes' => $detalhes,
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

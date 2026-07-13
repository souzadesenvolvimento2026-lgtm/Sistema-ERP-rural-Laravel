<?php

namespace App\Services;

use App\Domain\Access\ProfileAccess;
use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

class PropriedadeService
{
    private const SYSTEM_PROFILES = ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'];

    private const PROPERTY_MANAGER_PROFILES = [
        'administrador',
        'gestor_propriedade',
        'gestao',
    ];

    private const APPROVER_PROFILES = [
        'administrador',
        'gestor_financeiro',
        'gestor_propriedade',
        'gestao',
        'financeiro',
    ];

    private const USER_PROFILES = [
        'gestor_propriedade' => 'Gestor da Propriedade',
        'administrador' => 'Administrador',
        'gestao' => 'Gestão',
        'produtor' => 'Produtor',
        'colaborador' => 'Colaborador',
        'financeiro' => 'Financeiro',
        'visualizador' => 'Visualizador',
        'gestor_financeiro' => 'Gestor Financeiro',
    ];

    private const PLAN_OPTIONS = [
        'basico' => 'Básico - até 3 usuários',
        'avancado' => 'Avançado - até 5 usuários',
        'premium' => 'Premium - até 10 usuários',
    ];

    public function __construct(private readonly ProfileAccess $access)
    {
    }

    public function pagina(Request $request): array
    {
        $filtros = $this->filtros($request);
        $usuarioId = (int) session('usuario_id');
        $perfil = (string) session('perfil', '');
        $isSystemAdmin = $this->access->isSystemAdministrator($perfil);
        $canEditProperties = $isSystemAdmin || in_array($perfil, self::PROPERTY_MANAGER_PROFILES, true);
        $canToggleProperties = $isSystemAdmin;
        $rows = $this->rows($filtros, $usuarioId, $perfil, $canEditProperties, $canToggleProperties);
        $usuariosPorPropriedade = $this->usuariosPorPropriedade($rows->pluck('id')->all());

        $rows = $rows->map(function ($row) use ($usuariosPorPropriedade) {
            $row->usuarios_vinculados = $usuariosPorPropriedade->get($row->id, collect())->values();

            return $row;
        });

        $propriedadeAtual = DB::table('propriedades')
            ->where('id', (int) session('propriedade_id'))
            ->first(['id', 'nome', 'plano']);
        $canCreateProperty = $isSystemAdmin || (
            $canEditProperties
            && (string) ($propriedadeAtual->plano ?? '') === 'premium'
        );

        return [
            'activeModule' => 'propriedades',
            'title' => 'Propriedades',
            'subtitle' => 'Painel administrativo de fazendas, usuários, planos, cotação e georreferência.',
            'filtros' => $filtros,
            'rows' => $rows,
            'statusOptions' => [
                'ativas' => 'Ativas',
                'inativas' => 'Inativas',
                'todas' => 'Todas',
            ],
            'cards' => [
                ['label' => 'Propriedades', 'value' => (string) $rows->count(), 'tone' => 'success'],
                ['label' => 'Ativas', 'value' => (string) $rows->where('ativo', true)->count(), 'tone' => 'success'],
                ['label' => 'Área total', 'value' => FarmFormat::decimal($rows->sum('area_total_raw'), 2).' ha', 'tone' => 'success'],
                ['label' => 'Usuários vinculados', 'value' => (string) $rows->sum('usuarios_total'), 'tone' => 'warning'],
            ],
            'aprovadores' => self::aprovadores(),
            'perfisUsuario' => self::USER_PROFILES,
            'planOptions' => self::PLAN_OPTIONS,
            'canCreateProperty' => $canCreateProperty,
            'canEditProperties' => $canEditProperties,
            'canToggleProperties' => $canToggleProperties,
            'isSystemAdmin' => $isSystemAdmin,
        ];
    }

    public function criar(array $dados, ?UploadedFile $arquivoGeo = null, ?int $usuarioId = null): int
    {
        $this->autorizarCriacao();
        $aprovadorId = $this->aprovadorValido($dados['aprovador_usuario_id'] ?? null);

        return DB::transaction(function () use ($dados, $arquivoGeo, $usuarioId, $aprovadorId) {
            DB::table('propriedades')->insert($this->payload($dados) + [
                'ativo' => 1,
                'aprovador_usuario_id' => $aprovadorId,
            ]);

            $propriedadeId = (int) DB::getPdo()->lastInsertId();
            $this->vincularCriadorQuandoNecessario($propriedadeId, $usuarioId);
            $this->vincularAprovador($propriedadeId, $aprovadorId);
            $this->processarUsuarios($propriedadeId, $dados, $usuarioId);
            $this->validarLimiteUsuarios($propriedadeId, (string) ($dados['plano'] ?? 'basico'));
            $this->importarGeo($propriedadeId, $arquivoGeo, $usuarioId, $dados['area_total'] ?? null);
            $this->auditar($usuarioId, 'criar_propriedade', 'propriedades', $propriedadeId, $propriedadeId, 'Propriedade criada: '.trim($dados['nome']));

            return $propriedadeId;
        });
    }

    public function buscar(int $propriedadeId): object
    {
        $propriedade = DB::table('propriedades')->where('id', $propriedadeId)->first();
        abort_if(! $propriedade, 404);

        return $propriedade;
    }

    public function atualizar(int $propriedadeId, array $dados, ?UploadedFile $arquivoGeo = null, ?int $usuarioId = null): void
    {
        $propriedadeAnterior = $this->buscar($propriedadeId);
        $this->autorizarEdicao($propriedadeId);
        $aprovadorId = $this->aprovadorValido($dados['aprovador_usuario_id'] ?? null);

        DB::transaction(function () use ($propriedadeId, $dados, $arquivoGeo, $usuarioId, $aprovadorId, $propriedadeAnterior) {
            DB::table('propriedades')
                ->where('id', $propriedadeId)
                ->update($this->payload($dados) + ['aprovador_usuario_id' => $aprovadorId]);

            $this->vincularAprovador($propriedadeId, $aprovadorId);
            $this->processarUsuarios($propriedadeId, $dados, $usuarioId);
            $this->validarLimiteUsuarios($propriedadeId, (string) ($dados['plano'] ?? 'basico'));
            $this->importarGeo($propriedadeId, $arquivoGeo, $usuarioId, $dados['area_total'] ?? null);

            $detalhes = 'Propriedade atualizada: '.$propriedadeAnterior->nome.' → '.trim($dados['nome']);
            if ((string) $propriedadeAnterior->plano !== (string) ($dados['plano'] ?? '')) {
                $detalhes .= ' | Plano: '.$this->planoLabel((string) $propriedadeAnterior->plano).' → '.$this->planoLabel((string) ($dados['plano'] ?? 'basico'));
            }

            $this->auditar($usuarioId, 'editar_propriedade', 'propriedades', $propriedadeId, $propriedadeId, $detalhes);
        });
    }

    public static function aprovadores(): Collection
    {
        return DB::table('usuarios')
            ->where('ativo', 1)
            ->whereIn('perfil', self::APPROVER_PROFILES)
            ->orderBy('nome')
            ->get(['id', 'nome', 'email', 'perfil']);
    }

    public function alternarStatus(int $propriedadeId, string $senhaAdministrador, ?int $usuarioId = null): bool
    {
        $propriedade = $this->buscar($propriedadeId);
        $this->autorizarAlternarStatus($senhaAdministrador, $usuarioId);

        $ativo = (int) $propriedade->ativo === 1 ? 0 : 1;

        DB::table('propriedades')
            ->where('id', $propriedadeId)
            ->update(['ativo' => $ativo]);

        $acao = $ativo === 1 ? 'reativar_propriedade' : 'desativar_propriedade';
        $detalhes = ($ativo === 1 ? 'Propriedade reativada: ' : 'Propriedade desativada: ').$propriedade->nome;
        $this->auditar($usuarioId, $acao, 'propriedades', $propriedadeId, $propriedadeId, $detalhes);

        return $ativo === 1;
    }

    private function filtros(Request $request): array
    {
        $status = (string) $request->query('status', 'ativas');
        if (! in_array($status, ['ativas', 'inativas', 'todas'], true)) {
            $status = 'ativas';
        }

        return [
            'status' => $status,
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    private function rows(array $filtros, int $usuarioId, string $perfil, bool $canEditProperties, bool $canToggleProperties): Collection
    {
        $query = DB::table('propriedades as p')
            ->leftJoin('usuarios as aprovador', 'aprovador.id', '=', 'p.aprovador_usuario_id')
            ->leftJoin(DB::raw("(
                SELECT up.propriedade_id, COUNT(DISTINCT up.usuario_id) AS usuarios_total
                FROM usuario_propriedades up
                JOIN usuarios u ON u.id = up.usuario_id
                WHERE u.ativo = 1
                  AND u.perfil NOT IN ('administrador_sistema', 'gerencia_sistema', 'colaborador_sistema')
                GROUP BY up.propriedade_id
            ) as up"), 'up.propriedade_id', '=', 'p.id')
            ->leftJoin(DB::raw("(
                SELECT propriedade_id, COUNT(*) AS talhoes_total
                FROM talhoes
                WHERE ativo = 1
                GROUP BY propriedade_id
            ) as talhoes"), 'talhoes.propriedade_id', '=', 'p.id')
            ->leftJoin(DB::raw("(
                SELECT gfp.propriedade_id, GROUP_CONCAT(gf.nome ORDER BY gf.nome SEPARATOR ', ') AS grupos_nomes
                FROM grupo_fazenda_propriedades gfp
                JOIN grupos_fazendas gf ON gf.id = gfp.grupo_id
                GROUP BY gfp.propriedade_id
            ) as grupos"), 'grupos.propriedade_id', '=', 'p.id');

        if (! $this->access->hasGlobalPropertyAccess($perfil)) {
            $query->where('p.ativo', 1)
                ->where(function ($access) use ($usuarioId) {
                    $access->whereExists(function ($direct) use ($usuarioId) {
                        $direct->selectRaw('1')
                            ->from('usuario_propriedades as up_access')
                            ->whereColumn('up_access.propriedade_id', 'p.id')
                            ->where('up_access.usuario_id', $usuarioId);
                    })->orWhereExists(function ($group) use ($usuarioId) {
                        $group->selectRaw('1')
                            ->from('usuario_grupos_fazendas as ugf_access')
                            ->join('grupos_fazendas as gf_access', 'gf_access.id', '=', 'ugf_access.grupo_id')
                            ->join('grupo_fazenda_propriedades as gfp_access', 'gfp_access.grupo_id', '=', 'gf_access.id')
                            ->whereColumn('gfp_access.propriedade_id', 'p.id')
                            ->where('ugf_access.usuario_id', $usuarioId)
                            ->where('gf_access.ativo', 1);
                    });
                });
        }

        if ($filtros['status'] === 'ativas') {
            $query->where('p.ativo', 1);
        } elseif ($filtros['status'] === 'inativas') {
            $query->where('p.ativo', 0);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('p.nome', 'like', $term)
                    ->orWhere('p.municipio', 'like', $term)
                    ->orWhere('p.estado', 'like', $term)
                    ->orWhere('p.responsavel', 'like', $term)
                    ->orWhere('p.cnpj_cpf', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('p.ativo')
            ->orderBy('p.nome')
            ->get([
                'p.id',
                'p.nome',
                'p.municipio',
                'p.estado',
                'p.area_total',
                'p.responsavel',
                'p.inscricao_estadual',
                'p.cnpj_cpf',
                'p.plano',
                'p.pecuaria_ativa',
                'p.ativo',
                'p.latitude',
                'p.longitude',
                'p.kml_arquivo',
                'p.regiao_cotacao',
                'p.cotacao_soja',
                'p.cotacao_soja_atualizada_em',
                'p.cotacao_soja_fonte',
                'p.cotacao_soja_ultima_busca',
                'p.aprovador_usuario_id',
                'aprovador.nome as aprovador_nome',
                DB::raw('COALESCE(up.usuarios_total, 0) as usuarios_total'),
                DB::raw('COALESCE(talhoes.talhoes_total, 0) as talhoes_total'),
                DB::raw('COALESCE(grupos.grupos_nomes, "") as grupos_nomes'),
            ])
            ->map(fn ($row) => $this->normalizar($row, $canEditProperties, $canToggleProperties));
    }

    private function normalizar($row, bool $canEditProperties, bool $canToggleProperties): object
    {
        $plano = (string) ($row->plano ?: 'basico');
        $latitude = $row->latitude !== null ? (string) $row->latitude : '';
        $longitude = $row->longitude !== null ? (string) $row->longitude : '';
        $hasGeo = $latitude !== '' && $longitude !== '';
        $mapUrl = $hasGeo ? 'https://www.google.com/maps?q='.rawurlencode($latitude.','.$longitude) : null;

        return (object) [
            'id' => (int) $row->id,
            'nome' => FarmFormat::value($row->nome),
            'nome_raw' => (string) ($row->nome ?? ''),
            'municipio' => (string) ($row->municipio ?? ''),
            'estado' => (string) ($row->estado ?? ''),
            'municipio_uf' => trim(FarmFormat::value($row->municipio).' / '.FarmFormat::value($row->estado), ' /'),
            'area_total_raw' => (float) $row->area_total,
            'area_total_input' => (float) $row->area_total > 0 ? number_format((float) $row->area_total, 2, ',', '.') : '',
            'area_total' => (float) $row->area_total > 0 ? FarmFormat::decimal($row->area_total, 2) : '-',
            'responsavel_raw' => (string) ($row->responsavel ?? ''),
            'responsavel' => FarmFormat::value($row->responsavel),
            'inscricao_estadual' => (string) ($row->inscricao_estadual ?? ''),
            'cnpj_cpf_raw' => (string) ($row->cnpj_cpf ?? ''),
            'cnpj_cpf' => FarmFormat::value($row->cnpj_cpf),
            'plano_key' => $plano,
            'plano' => $this->planoLabel($plano),
            'limite_usuarios' => $this->limiteUsuarios($plano),
            'usuarios_total' => (int) $row->usuarios_total,
            'pecuaria' => (int) $row->pecuaria_ativa === 1 ? 'Ativa' : 'Desativada',
            'pecuaria_ativa' => (int) $row->pecuaria_ativa === 1,
            'ativo' => (int) $row->ativo === 1,
            'status' => (int) $row->ativo === 1 ? 'Ativa' : 'Inativa',
            'aprovador_usuario_id' => $row->aprovador_usuario_id ? (int) $row->aprovador_usuario_id : null,
            'aprovador' => FarmFormat::value($row->aprovador_nome),
            'grupos' => FarmFormat::value($row->grupos_nomes),
            'talhoes_total' => (int) $row->talhoes_total,
            'cotacao_soja' => FarmFormat::money($row->cotacao_soja),
            'cotacao_data' => FarmFormat::date($row->cotacao_soja_atualizada_em),
            'cotacao_fonte' => FarmFormat::value($row->cotacao_soja_fonte),
            'cotacao_ultima_busca' => $this->dataHora($row->cotacao_soja_ultima_busca),
            'regiao_cotacao_raw' => (string) ($row->regiao_cotacao ?? ''),
            'regiao_cotacao' => FarmFormat::value($row->regiao_cotacao),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'kml_arquivo' => (string) ($row->kml_arquivo ?? ''),
            'has_geo' => $hasGeo,
            'geo' => $hasGeo ? $latitude.', '.$longitude : 'Sem KML',
            'geo_url' => $mapUrl,
            'can_edit' => $canEditProperties,
            'can_toggle' => $canToggleProperties,
        ];
    }

    private function usuariosPorPropriedade(array $propriedadesIds): Collection
    {
        $ids = collect($propriedadesIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('usuario_propriedades as up')
            ->join('usuarios as u', 'u.id', '=', 'up.usuario_id')
            ->whereIn('up.propriedade_id', $ids->all())
            ->whereNotIn('u.perfil', self::SYSTEM_PROFILES)
            ->orderBy('u.nome')
            ->get([
                'up.propriedade_id',
                'u.id',
                'u.nome',
                'u.email',
                'u.perfil',
                'u.ativo',
            ])
            ->map(fn ($row) => (object) [
                'propriedade_id' => (int) $row->propriedade_id,
                'id' => (int) $row->id,
                'nome' => (string) $row->nome,
                'email' => (string) $row->email,
                'perfil' => (string) $row->perfil,
                'perfil_label' => FarmFormat::statusLabel((string) $row->perfil),
                'ativo' => (int) $row->ativo === 1,
            ])
            ->groupBy('propriedade_id');
    }

    private function processarUsuarios(int $propriedadeId, array $dados, ?int $usuarioLogadoId): void
    {
        $this->atualizarUsuariosVinculados($propriedadeId, $dados['usuarios_vinculados'] ?? [], $usuarioLogadoId);
        $this->criarOuVincularNovosUsuarios($propriedadeId, $dados['novos_usuarios'] ?? [], $usuarioLogadoId);
    }

    private function atualizarUsuariosVinculados(int $propriedadeId, mixed $usuarios, ?int $usuarioLogadoId): void
    {
        if (! is_array($usuarios)) {
            return;
        }

        foreach ($usuarios as $usuarioDados) {
            $usuarioId = (int) ($usuarioDados['id'] ?? 0);
            if ($usuarioId <= 0 || ! $this->usuarioVinculado($propriedadeId, $usuarioId)) {
                continue;
            }

            $usuarioAnterior = DB::table('usuarios')
                ->where('id', $usuarioId)
                ->whereNotIn('perfil', self::SYSTEM_PROFILES)
                ->first(['id', 'nome', 'email', 'perfil']);

            if (! $usuarioAnterior) {
                continue;
            }

            $nome = trim((string) ($usuarioDados['nome'] ?? $usuarioAnterior->nome));
            $email = strtolower(trim((string) ($usuarioDados['email'] ?? $usuarioAnterior->email)));
            $perfil = $this->perfilUsuario((string) ($usuarioDados['perfil'] ?? $usuarioAnterior->perfil));

            if ($nome === '' || $email === '') {
                throw new RuntimeException('Nome e e-mail dos usuários vinculados são obrigatórios.');
            }

            $emailEmUso = DB::table('usuarios')
                ->where('email', $email)
                ->where('id', '!=', $usuarioId)
                ->exists();
            if ($emailEmUso) {
                throw new RuntimeException('O e-mail '.$email.' já está sendo usado por outro usuário.');
            }

            $payload = [
                'nome' => $nome,
                'email' => $email,
                'perfil' => $perfil,
            ];

            if (trim((string) ($usuarioDados['senha'] ?? '')) !== '') {
                $payload['senha'] = Hash::make((string) $usuarioDados['senha']);
            }

            DB::table('usuarios')->where('id', $usuarioId)->update($payload);

            $this->auditar(
                $usuarioLogadoId,
                'editar_usuario_propriedade',
                'usuarios',
                $usuarioId,
                $propriedadeId,
                'Usuário vinculado atualizado: '.$usuarioAnterior->nome.' ('.$usuarioAnterior->email.') → '.$nome.' ('.$email.')'
            );
        }
    }

    private function criarOuVincularNovosUsuarios(int $propriedadeId, mixed $usuarios, ?int $usuarioLogadoId): void
    {
        if (! is_array($usuarios)) {
            return;
        }

        $usuarios = array_slice($usuarios, 0, 3);
        foreach ($usuarios as $usuarioDados) {
            $nome = trim((string) ($usuarioDados['nome'] ?? ''));
            $email = strtolower(trim((string) ($usuarioDados['email'] ?? '')));
            $senha = (string) ($usuarioDados['senha'] ?? '');
            $perfil = $this->perfilUsuario((string) ($usuarioDados['perfil'] ?? 'visualizador'));

            if ($nome === '' && $email === '' && trim($senha) === '') {
                continue;
            }

            if ($email === '') {
                throw new RuntimeException('Informe o e-mail do novo usuário.');
            }

            $usuarioExistente = DB::table('usuarios')
                ->where('email', $email)
                ->first(['id', 'nome', 'email', 'perfil', 'ativo']);

            if ($usuarioExistente) {
                if ((int) $usuarioExistente->ativo !== 1 || in_array((string) $usuarioExistente->perfil, self::SYSTEM_PROFILES, true)) {
                    throw new RuntimeException('O e-mail '.$email.' pertence a um usuário indisponível para vínculo nesta propriedade.');
                }

                DB::table('usuario_propriedades')->updateOrInsert([
                    'usuario_id' => (int) $usuarioExistente->id,
                    'propriedade_id' => $propriedadeId,
                ]);

                $this->auditar(
                    $usuarioLogadoId,
                    'vincular_usuario_existente',
                    'usuario_propriedades',
                    (int) $usuarioExistente->id,
                    $propriedadeId,
                    'Usuário existente vinculado à propriedade: '.$usuarioExistente->nome.' ('.$usuarioExistente->email.')'
                );
                continue;
            }

            if ($nome === '' || trim($senha) === '') {
                throw new RuntimeException('Usuário novo precisa de nome, e-mail e senha.');
            }

            DB::table('usuarios')->insert([
                'nome' => $nome,
                'email' => $email,
                'senha' => Hash::make($senha),
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
                'criar_usuario_propriedade',
                'usuarios',
                $usuarioId,
                $propriedadeId,
                'Usuário criado e vinculado à propriedade: '.$nome.' ('.$email.') - Perfil: '.FarmFormat::statusLabel($perfil)
            );
        }
    }

    private function validarLimiteUsuarios(int $propriedadeId, string $plano): void
    {
        $limitePlano = $this->limiteUsuarios($plano);
        $usuariosTotal = $this->contarUsuariosOperacionais($propriedadeId);

        if ($usuariosTotal > $limitePlano) {
            throw new RuntimeException('Esta propriedade tem '.$usuariosTotal.' usuários operacionais e o plano '.$this->planoLabel($plano).' permite '.$limitePlano.'. Ajuste os vínculos ou o plano antes de salvar.');
        }
    }

    private function contarUsuariosOperacionais(int $propriedadeId): int
    {
        return (int) DB::table('usuario_propriedades as up')
            ->join('usuarios as u', 'u.id', '=', 'up.usuario_id')
            ->where('up.propriedade_id', $propriedadeId)
            ->where('u.ativo', 1)
            ->whereNotIn('u.perfil', self::SYSTEM_PROFILES)
            ->distinct('u.id')
            ->count('u.id');
    }

    private function limiteUsuarios(string $plano): int
    {
        return ['basico' => 3, 'avancado' => 5, 'premium' => 10][$plano] ?? 3;
    }

    private function planoLabel(string $plano): string
    {
        return ['basico' => 'Básico', 'avancado' => 'Avançado', 'premium' => 'Premium'][$plano] ?? 'Básico';
    }

    private function payload(array $dados): array
    {
        return [
            'nome' => trim($dados['nome']),
            'municipio' => trim($dados['municipio'] ?? '') ?: null,
            'estado' => strtoupper(trim($dados['estado'] ?? '')) ?: null,
            'area_total' => $this->decimal($dados['area_total'] ?? 0),
            'responsavel' => trim($dados['responsavel'] ?? '') ?: null,
            'inscricao_estadual' => trim($dados['inscricao_estadual'] ?? '') ?: null,
            'cnpj_cpf' => preg_replace('/\D+/', '', (string) ($dados['cnpj_cpf'] ?? '')) ?: null,
            'plano' => $dados['plano'] ?: 'basico',
            'pecuaria_ativa' => (bool) ($dados['pecuaria_ativa'] ?? false),
            'latitude' => $this->nullableDecimal($dados['latitude'] ?? null),
            'longitude' => $this->nullableDecimal($dados['longitude'] ?? null),
            'regiao_cotacao' => trim($dados['regiao_cotacao'] ?? '') ?: null,
        ];
    }

    private function aprovadorValido($aprovadorId): ?int
    {
        if (! $aprovadorId) {
            return null;
        }

        $id = (int) $aprovadorId;
        $exists = DB::table('usuarios')
            ->where('id', $id)
            ->where('ativo', 1)
            ->whereIn('perfil', self::APPROVER_PROFILES)
            ->exists();

        return $exists ? $id : null;
    }

    private function perfilUsuario(string $perfil): string
    {
        return array_key_exists($perfil, self::USER_PROFILES) ? $perfil : 'visualizador';
    }

    private function usuarioVinculado(int $propriedadeId, int $usuarioId): bool
    {
        return DB::table('usuario_propriedades')
            ->where('propriedade_id', $propriedadeId)
            ->where('usuario_id', $usuarioId)
            ->exists();
    }

    private function vincularAprovador(int $propriedadeId, ?int $aprovadorId): void
    {
        if (! $aprovadorId || $this->usuarioVinculado($propriedadeId, $aprovadorId)) {
            return;
        }

        DB::table('usuario_propriedades')->insert([
            'usuario_id' => $aprovadorId,
            'propriedade_id' => $propriedadeId,
        ]);
    }

    private function vincularCriadorQuandoNecessario(int $propriedadeId, ?int $usuarioId): void
    {
        if (! $usuarioId || $this->access->isSystemAdministrator((string) session('perfil', ''))) {
            return;
        }

        $usuarioPermitido = DB::table('usuarios')
            ->where('id', $usuarioId)
            ->where('ativo', 1)
            ->whereNotIn('perfil', self::SYSTEM_PROFILES)
            ->exists();

        if (! $usuarioPermitido) {
            return;
        }

        DB::table('usuario_propriedades')->updateOrInsert([
            'usuario_id' => $usuarioId,
            'propriedade_id' => $propriedadeId,
        ]);
    }

    private function autorizarCriacao(): void
    {
        $perfil = (string) session('perfil', '');
        if ($this->access->isSystemAdministrator($perfil)) {
            return;
        }

        if (! in_array($perfil, self::PROPERTY_MANAGER_PROFILES, true)) {
            throw new RuntimeException('Você não tem permissão para adicionar fazendas.');
        }

        $planoAtual = DB::table('propriedades')
            ->where('id', (int) session('propriedade_id'))
            ->value('plano');

        if ((string) $planoAtual !== 'premium') {
            throw new RuntimeException('Adicionar nova fazenda está disponível apenas para plano Premium.');
        }
    }

    private function autorizarEdicao(int $propriedadeId): void
    {
        $perfil = (string) session('perfil', '');
        $usuarioId = (int) session('usuario_id');
        if ($this->access->isSystemAdministrator($perfil)) {
            return;
        }

        if (! in_array($perfil, self::PROPERTY_MANAGER_PROFILES, true)) {
            throw new RuntimeException('Você não tem permissão para editar propriedades.');
        }

        $acessa = DB::table('usuario_propriedades')
            ->where('usuario_id', $usuarioId)
            ->where('propriedade_id', $propriedadeId)
            ->exists();

        if (! $acessa) {
            $acessa = DB::table('usuario_grupos_fazendas as ugf')
                ->join('grupos_fazendas as gf', 'gf.id', '=', 'ugf.grupo_id')
                ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
                ->where('ugf.usuario_id', $usuarioId)
                ->where('gfp.propriedade_id', $propriedadeId)
                ->where('gf.ativo', 1)
                ->exists();
        }

        if (! $acessa) {
            throw new RuntimeException('Você não tem acesso a esta propriedade.');
        }
    }

    private function autorizarAlternarStatus(string $senhaAdministrador, ?int $usuarioId): void
    {
        $usuario = DB::table('usuarios')
            ->where('id', (int) $usuarioId)
            ->where('ativo', 1)
            ->first(['id', 'senha', 'perfil']);

        if (! $usuario || ! $this->access->isSystemAdministrator((string) $usuario->perfil)) {
            throw new RuntimeException('Somente admin do sistema pode desativar ou reativar propriedades.');
        }

        if (! Hash::check($senhaAdministrador, (string) $usuario->senha)) {
            throw new RuntimeException('Senha do administrador inválida.');
        }
    }

    private function importarGeo(int $propriedadeId, ?UploadedFile $arquivoGeo, ?int $usuarioId, mixed $areaInformada): void
    {
        if (! $arquivoGeo || ! $arquivoGeo->isValid()) {
            return;
        }

        $resultado = app(TalhaoService::class)->importarArquivoGeo($propriedadeId, $arquivoGeo, null, $usuarioId);
        if (($resultado['imported'] ?? 0) <= 0) {
            return;
        }

        $kmlArquivo = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->whereNotNull('kml_arquivo')
            ->orderByDesc('id')
            ->value('kml_arquivo');

        $talhoesImportados = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->when($kmlArquivo, fn ($query) => $query->where('kml_arquivo', $kmlArquivo))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude', 'area']);

        if ($talhoesImportados->isEmpty()) {
            return;
        }

        $latitude = (float) $talhoesImportados->avg('latitude');
        $longitude = (float) $talhoesImportados->avg('longitude');
        $areaTotal = (float) $talhoesImportados->sum('area');
        $areaFallback = $areaTotal > 0 ? $areaTotal : $this->decimal($areaInformada ?? 0);
        $regiao = number_format($latitude, 6, '.', '').', '.number_format($longitude, 6, '.', '');

        DB::table('propriedades')
            ->where('id', $propriedadeId)
            ->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'kml_arquivo' => $kmlArquivo,
                'area_total' => DB::raw('IF(COALESCE(area_total,0)>0, area_total, '.($areaFallback > 0 ? (string) $areaFallback : 'NULL').')'),
                'regiao_cotacao' => DB::raw("COALESCE(NULLIF(regiao_cotacao,''), '".$regiao."')"),
                'cotacao_soja_fonte' => DB::raw("COALESCE(NULLIF(cotacao_soja_fonte,''), 'Cadastro manual / referência regional')"),
            ]);
    }

    private function decimal($value): float
    {
        $value = str_replace(',', '.', trim((string) $value));

        return max(0.0, (float) $value);
    }

    private function nullableDecimal($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (float) str_replace(',', '.', trim((string) $value));
    }

    private function dataHora($value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
        } catch (Throwable) {
            return '-';
        }
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
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

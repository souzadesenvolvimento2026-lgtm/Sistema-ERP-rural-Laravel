<?php

namespace App\Services;

use App\Domain\Geo\InvalidPolygon;
use App\Domain\Geo\PolygonGeometry;
use App\Domain\Geo\TalhaoMapCapabilities;
use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class TalhaoService
{
    private const GEO_UPLOAD_EXTENSIONS = ['kml', 'kmz', 'shp', 'zip'];
    private const GEO_UPLOAD_MAX_BYTES = 20 * 1024 * 1024;
    private const GEO_ZIP_MAX_ENTRIES = 200;

    public function __construct(
        private readonly PolygonGeometry $geometry,
        private readonly TalhaoMapCapabilities $mapCapabilities,
    ) {}

    public function pagina(int $propriedadeId, Request $request): array
    {
        $filtros = $this->filtros($request);
        $rows = $this->rows($propriedadeId, $filtros);
        $talhoesAtivos = $this->talhoesAtivos($propriedadeId);
        $propertyName = (string) (DB::table('propriedades')->where('id', $propriedadeId)->value('nome') ?: 'Propriedade');
        $counts = [
            'ativos' => DB::table('talhoes')->where('propriedade_id', $propriedadeId)->where('ativo', 1)->count(),
            'desativados' => DB::table('talhoes')->where('propriedade_id', $propriedadeId)->where('ativo', 0)->count(),
        ];

        return [
            'activeModule' => 'talhoes',
            'title' => 'Talhões',
            'subtitle' => 'Consulta dos talhões, áreas, geometrias, despesas e custos por hectare.',
            'propertyName' => $propertyName,
            'filtros' => $filtros,
            'rows' => $rows,
            'counts' => $counts,
            'talhoesAtivos' => $talhoesAtivos,
            'unification' => [
                'can_unify' => $talhoesAtivos->count() >= 2,
                'block_reason' => 'Cadastre pelo menos dois talhoes ativos para usar a unificacao.',
            ],
            'statusOptions' => [
                'ativos' => 'Ativos',
                'desativados' => 'Desativados',
                'todos' => 'Todos',
            ],
            'cards' => [
                ['label' => 'Total de talhões', 'value' => (string) $counts['ativos'], 'tone' => 'success'],
                ['label' => 'Área total', 'value' => FarmFormat::decimal($rows->where('ativo', true)->sum('area_raw'), 2).' ha', 'tone' => 'success'],
                ['label' => 'Georreferenciados', 'value' => (string) $rows->where('georreferenciado', true)->count(), 'tone' => 'warning'],
                ['label' => 'Arquivo geoespacial', 'value' => 'Opcional', 'hint' => 'KML, KMZ ou SHP', 'tone' => ''],
            ],
        ];
    }

    public function mapa(int $propriedadeId): array
    {
        $propriedade = DB::table('propriedades')
            ->where('id', $propriedadeId)
            ->first(['id', 'nome', 'municipio', 'estado', 'regiao_cotacao']);
        $safraAtual = DB::table('safras')
            ->where('propriedade_id', $propriedadeId)
            ->orderByRaw("CASE WHEN status = 'em_andamento' THEN 0 ELSE 1 END")
            ->orderByDesc('data_inicio')
            ->orderByDesc('id')
            ->first(['id', 'descricao', 'status']);

        $gastosPorTalhao = DB::table('despesas')
            ->where('propriedade_id', $propriedadeId)
            ->where('status_pagamento', '!=', 'cancelado')
            ->where('status_aprovacao', '=', 'aprovada')
            ->whereNotNull('talhao_id')
            ->groupBy('talhao_id')
            ->selectRaw('talhao_id, COUNT(*) as qtd, COALESCE(SUM(valor_total), 0) as total')
            ->get()
            ->keyBy('talhao_id');
        $safrasEmUsoPorTalhao = $this->safrasEmUso($propriedadeId)
            ->groupBy('talhao_id');

        $talhoes = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get([
                'id',
                'nome',
                'descricao',
                'area',
                'area_bruta',
                'area_excluida_ha',
                'latitude',
                'longitude',
                'geometria_tipo',
                'coordenadas_json',
                'exclusoes_json',
                'pivo_ativo',
                'pivo_lat',
                'pivo_lng',
                'pivo_raio_m',
                'pivo_area_ha',
            ])
            ->map(function ($talhao) use ($gastosPorTalhao, $safrasEmUsoPorTalhao) {
                $mapa = $this->talhaoMapa($talhao);
                $gasto = $gastosPorTalhao->get($mapa['id']);
                $safrasEmUso = $this->normalizarSafrasEmUso($safrasEmUsoPorTalhao->get($mapa['id'], collect()));
                $mapa['custo'] = (float) ($gasto->total ?? 0);
                $mapa['custo_formatado'] = FarmFormat::money($mapa['custo']);
                $mapa['total_despesas_formatado'] = $mapa['custo_formatado'];
                $mapa['custo_ha_formatado'] = $mapa['area'] > 0
                    ? FarmFormat::money($mapa['custo'] / $mapa['area']).'/ha'
                    : FarmFormat::money(0).'/ha';
                $mapa['qtd_despesas'] = (int) ($gasto->qtd ?? 0);
                foreach ($this->mapCapabilities->for(
                    $safrasEmUso,
                    count($mapa['exclusoes']),
                ) as $capability => $value) {
                    $mapa[$capability] = $value;
                }

                return $mapa;
            });

        $centro = $this->centroGeograficoTalhoes($talhoes)
            ?? $talhoes->first(fn ($talhao) => $talhao['lat'] && $talhao['lng'])
            ?? ['lat' => -15.7801, 'lng' => -47.9292];
        $areaTotal = (float) $talhoes->sum('area');
        $totalGasto = (float) $talhoes->sum('custo');
        $talhoesGeo = $talhoes->filter(fn ($talhao) => ($talhao['lat'] && $talhao['lng']) || count($talhao['points'] ?? []) >= 3)->count();
        $regiao = $this->regiaoGeograficaMapa($propriedade);

        return [
            'activeModule' => 'talhoes',
            'property' => $propriedade,
            'propertyName' => $propriedade->nome ?? session('propriedade_nome', 'Fazenda'),
            'safraName' => $safraAtual->descricao ?? null,
            'talhoes' => $talhoes,
            'centro' => $centro,
            'mapCards' => [
                'talhoes' => $talhoes->count(),
                'talhoes_geo' => $talhoesGeo,
                'area_total' => $areaTotal,
                'total_gasto' => $totalGasto,
                'regiao' => $regiao,
                'coordenadas' => number_format((float) ($centro['lat'] ?? -15.7801), 6, '.', '').', '.number_format((float) ($centro['lng'] ?? -47.9292), 6, '.', ''),
            ],
        ];
    }

    private function regiaoGeograficaMapa(object $propriedade): string
    {
        $municipio = trim((string) ($propriedade->municipio ?? ''));
        $estado = strtoupper(trim((string) ($propriedade->estado ?? '')));

        if ($municipio !== '') {
            return $estado !== '' && stripos($municipio, $estado) === false
                ? trim($municipio.'/'.$estado, '/')
                : $municipio;
        }

        return trim((string) ($propriedade->regiao_cotacao ?? '')) ?: 'Região da fazenda';
    }

    private function centroGeograficoTalhoes(Collection $talhoes): ?array
    {
        $georreferenciados = $talhoes->filter(fn ($talhao) => $talhao['lat'] !== null && $talhao['lng'] !== null);
        if ($georreferenciados->isEmpty()) {
            return null;
        }

        $pesoTotal = (float) $georreferenciados->sum(fn ($talhao) => max(0.0, (float) ($talhao['area'] ?? 0)));
        if ($pesoTotal <= 0.0) {
            return [
                'lat' => (float) $georreferenciados->avg('lat'),
                'lng' => (float) $georreferenciados->avg('lng'),
            ];
        }

        return [
            'lat' => (float) ($georreferenciados->sum(fn ($talhao) => (float) $talhao['lat'] * max(0.0, (float) ($talhao['area'] ?? 0))) / $pesoTotal),
            'lng' => (float) ($georreferenciados->sum(fn ($talhao) => (float) $talhao['lng'] * max(0.0, (float) ($talhao['area'] ?? 0))) / $pesoTotal),
        ];
    }

    public function atualizarDadosMapa(int $talhaoId, int $propriedadeId, array $dados, ?int $usuarioId): void
    {
        $nome = trim((string) ($dados['nome'] ?? ''));
        abort_if($nome === '', 422, 'Informe o nome do talhao.');

        DB::transaction(function () use ($talhaoId, $propriedadeId, $dados, $usuarioId, $nome): void {
            $talhao = DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->lockForUpdate()
                ->first();
            abort_if(! $talhao, 404);

            $duplicado = DB::table('talhoes')
                ->where('propriedade_id', $propriedadeId)
                ->where('ativo', 1)
                ->where('nome', $nome)
                ->where('id', '<>', $talhaoId)
                ->exists();
            abort_if($duplicado, 422, 'Ja existe um talhao ativo com esse nome nesta propriedade.');

            $payload = [
                'nome' => $nome,
                'descricao' => trim((string) ($dados['descricao'] ?? '')) ?: null,
            ];
            $hasPolygon = count($this->normalizarPontos($talhao->coordenadas_json ?? '')) >= 3;

            if (! $hasPolygon) {
                $requestedArea = $this->decimal($dados['area'] ?? ($talhao->area ?? 0));
                if (abs($requestedArea - (float) ($talhao->area ?? 0)) > 0.000001) {
                    $this->assertMapMutationAllowed($talhao, $propriedadeId, 'area');
                    $excludedArea = max(0, (float) ($talhao->area_excluida_ha ?? 0));
                    $payload['area'] = $requestedArea;
                    $payload['area_bruta'] = round($requestedArea + $excludedArea, 2);
                }
            }

            DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->update($payload);

            $this->auditarObrigatorio(
                $usuarioId,
                'editar_talhao_mapa',
                'talhoes',
                $talhaoId,
                $propriedadeId,
                'Dados do talhao editados no mapa',
            );
        }, 3);
    }

    public function salvarExclusao(int $talhaoId, int $propriedadeId, string $exclusaoJson, ?int $usuarioId): void
    {
        try {
            $exclusion = $this->geometry->decodeJsonRing($exclusaoJson);
        } catch (InvalidPolygon $exception) {
            throw ValidationException::withMessages(['exclusao_json' => $exception->getMessage()]);
        }

        DB::transaction(function () use ($talhaoId, $propriedadeId, $exclusion, $usuarioId): void {
            $talhao = DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->lockForUpdate()
                ->first();
            abort_if(! $talhao, 404);
            $this->assertMapMutationAllowed($talhao, $propriedadeId, 'exclusao_json');

            $geometrias = $this->geometriasTalhao($talhao);
            if (count($geometrias) > 1) {
                $salvou = $this->salvarExclusaoEmGeometriaComposta($talhao, $propriedadeId, $geometrias, $exclusion);
                if (! $salvou) {
                    return;
                }

                $this->auditarObrigatorio(
                    $usuarioId,
                    'criar_exclusao_talhao',
                    'talhoes',
                    $talhaoId,
                    $propriedadeId,
                    'Area excluida adicionada ao talhao '.$talhao->nome,
                );

                return;
            }

            try {
                $outer = $this->geometry->normalizeRing($this->pontosTalhao($talhao));
            } catch (InvalidPolygon $exception) {
                throw ValidationException::withMessages([
                    'exclusao_json' => 'O talhão precisa de um polígono válido para receber área excluída.',
                ]);
            }

            $existingRings = $this->exclusoesTalhaoEstritas($talhao);
            try {
                $this->geometry->areaBreakdown($outer, $existingRings);
            } catch (InvalidPolygon $exception) {
                throw new RuntimeException('As áreas excluídas existentes estão inconsistentes.', previous: $exception);
            }

            try {
                $rings = $this->geometry->appendExclusion(
                    $outer,
                    $existingRings,
                    $exclusion,
                );
                if ($rings === $existingRings) {
                    return;
                }
                $areas = $this->geometry->areaBreakdown($outer, $rings);
            } catch (InvalidPolygon $exception) {
                throw ValidationException::withMessages(['exclusao_json' => $exception->getMessage()]);
            }

            DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->update([
                    'exclusoes_json' => json_encode(
                        $rings,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
                    'area' => $areas['liquida'],
                    'area_bruta' => $areas['bruta'],
                    'area_excluida_ha' => $areas['excluida'],
                ]);

            $this->auditarObrigatorio(
                $usuarioId,
                'criar_exclusao_talhao',
                'talhoes',
                $talhaoId,
                $propriedadeId,
                'Area excluida adicionada ao talhao '.$talhao->nome,
            );
        }, 3);
    }

    public function limparExclusoes(int $talhaoId, int $propriedadeId, ?int $usuarioId): void
    {
        DB::transaction(function () use ($talhaoId, $propriedadeId, $usuarioId): void {
            $talhao = DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->lockForUpdate()
                ->first();
            abort_if(! $talhao, 404);
            $this->assertMapMutationAllowed($talhao, $propriedadeId, 'exclusao_json');

            $geometrias = $this->geometriasTalhao($talhao);
            if (count($geometrias) > 1) {
                foreach ($geometrias as $indice => $geometria) {
                    $geometrias[$indice]['exclusions'] = [];
                }

                [$coordenadasJson, $exclusoesJson] = $this->serializarGeometrias($geometrias);
                $areas = $this->areasGeometrias($geometrias);

                DB::table('talhoes')
                    ->where('id', $talhaoId)
                    ->where('propriedade_id', $propriedadeId)
                    ->update([
                        'coordenadas_json' => $coordenadasJson,
                        'exclusoes_json' => $exclusoesJson,
                        'area' => $areas['liquida'],
                        'area_bruta' => $areas['bruta'],
                        'area_excluida_ha' => 0,
                    ]);

                $this->auditarObrigatorio(
                    $usuarioId,
                    'limpar_exclusoes_talhao',
                    'talhoes',
                    $talhaoId,
                    $propriedadeId,
                    'Areas excluidas removidas do talhao '.$talhao->nome,
                );

                return;
            }

            $outer = $this->pontosTalhao($talhao);
            $area = count($outer) >= 3 ? $this->areaHa($outer) : (float) ($talhao->area ?? 0);

            DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->update([
                    'exclusoes_json' => null,
                    'area' => $area,
                    'area_bruta' => $area,
                    'area_excluida_ha' => 0,
                ]);

            $this->auditarObrigatorio(
                $usuarioId,
                'limpar_exclusoes_talhao',
                'talhoes',
                $talhaoId,
                $propriedadeId,
                'Areas excluidas removidas do talhao '.$talhao->nome,
            );
        }, 3);
    }

    public function salvarPivo(int $talhaoId, int $propriedadeId, array $dados, ?int $usuarioId): void
    {
        $lat = $this->nullableDecimal($dados['pivo_lat'] ?? null);
        $lng = $this->nullableDecimal($dados['pivo_lng'] ?? null);
        $raio = $this->nullableDecimal($dados['pivo_raio_m'] ?? null);
        abort_if($lat === null || $lat < -90 || $lat > 90 || $lng === null || $lng < -180 || $lng > 180 || $raio === null || $raio <= 0, 422, 'Informe um pivo valido.');

        $areaPivo = round((pi() * ($raio ** 2)) / 10000, 2);
        DB::transaction(function () use ($talhaoId, $propriedadeId, $usuarioId, $lat, $lng, $raio, $areaPivo): void {
            $talhao = DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->lockForUpdate()
                ->first();
            abort_if(! $talhao, 404);
            $this->assertMapMutationAllowed($talhao, $propriedadeId, 'pivo');

            DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->update([
                    'pivo_ativo' => 1,
                    'pivo_lat' => round($lat, 8),
                    'pivo_lng' => round($lng, 8),
                    'pivo_raio_m' => round($raio, 2),
                    'pivo_area_ha' => $areaPivo,
                ]);

            $this->auditarObrigatorio(
                $usuarioId,
                'salvar_pivo_talhao',
                'talhoes',
                $talhaoId,
                $propriedadeId,
                'Pivo criado/atualizado no talhao '.$talhao->nome,
            );
        }, 3);
    }

    public function criarPivoComoTalhao(int $propriedadeId, array $dados, ?int $usuarioId): int
    {
        $nome = trim((string) ($dados['nome'] ?? ''));
        abort_if($nome === '', 422, 'Informe o nome do talhao/pivo.');

        $lat = $this->nullableDecimal($dados['pivo_lat'] ?? null);
        $lng = $this->nullableDecimal($dados['pivo_lng'] ?? null);
        $raio = $this->nullableDecimal($dados['pivo_raio_m'] ?? null);
        abort_if($lat === null || $lat < -90 || $lat > 90 || $lng === null || $lng < -180 || $lng > 180 || $raio === null || $raio <= 0, 422, 'Informe um pivo valido.');

        $duplicado = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->where('nome', $nome)
            ->exists();
        abort_if($duplicado, 422, 'Ja existe um talhao ativo com esse nome nesta propriedade.');

        $raio = round($raio, 2);
        $areaPivo = round((pi() * ($raio ** 2)) / 10000, 2);
        $points = $this->circlePoints($lat, $lng, $raio);
        abort_if(count($points) < 3, 422, 'Nao foi possivel gerar o poligono redondo do pivo.');

        DB::table('talhoes')->insert([
            'propriedade_id' => $propriedadeId,
            'nome' => $nome,
            'area' => $areaPivo,
            'area_bruta' => $areaPivo,
            'area_excluida_ha' => 0,
            'descricao' => 'Pivo desenhado no mapa',
            'latitude' => round($lat, 8),
            'longitude' => round($lng, 8),
            'geometria_tipo' => 'polygon',
            'coordenadas_json' => json_encode($points, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'exclusoes_json' => null,
            'pivo_ativo' => 1,
            'pivo_lat' => round($lat, 8),
            'pivo_lng' => round($lng, 8),
            'pivo_raio_m' => $raio,
            'pivo_area_ha' => $areaPivo,
            'ativo' => 1,
        ]);

        $talhaoId = (int) DB::getPdo()->lastInsertId();
        $this->auditar($usuarioId, 'criar_pivo_talhao_mapa', 'talhoes', $talhaoId, $propriedadeId, 'Pivo/talhao '.$nome.' desenhado no mapa');

        return $talhaoId;
    }

    public function removerPivo(int $talhaoId, int $propriedadeId, ?int $usuarioId): void
    {
        DB::transaction(function () use ($talhaoId, $propriedadeId, $usuarioId): void {
            $talhao = DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->lockForUpdate()
                ->first();
            abort_if(! $talhao, 404);
            $this->assertMapMutationAllowed($talhao, $propriedadeId, 'pivo');

            DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->update([
                    'pivo_ativo' => 0,
                    'pivo_lat' => null,
                    'pivo_lng' => null,
                    'pivo_raio_m' => null,
                    'pivo_area_ha' => null,
                ]);

            $this->auditarObrigatorio(
                $usuarioId,
                'remover_pivo_talhao',
                'talhoes',
                $talhaoId,
                $propriedadeId,
                'Pivo removido do talhao',
            );
        }, 3);
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        $area = $this->decimal($dados['area'] ?? 0);

        DB::table('talhoes')->insert([
            'propriedade_id' => $propriedadeId,
            ...$this->payload($dados, $area),
            'ativo' => 1,
        ]);

        $talhaoId = (int) DB::getPdo()->lastInsertId();
        $this->auditar($usuarioId, 'salvar_talhao', 'talhoes', $talhaoId, $propriedadeId, trim($dados['nome']));

        return $talhaoId;
    }

    public function buscar(int $talhaoId, int $propriedadeId): object
    {
        $talhao = DB::table('talhoes')
            ->where('id', $talhaoId)
            ->where('propriedade_id', $propriedadeId)
            ->first();

        abort_if($talhao === null, 404);

        return $talhao;
    }

    public function atualizar(int $talhaoId, array $dados, int $propriedadeId, ?int $usuarioId): void
    {
        $area = $this->decimal($dados['area'] ?? 0);

        DB::table('talhoes')
            ->where('id', $talhaoId)
            ->where('propriedade_id', $propriedadeId)
            ->update($this->payload($dados, $area));

        $this->auditar($usuarioId, 'salvar_talhao', 'talhoes', $talhaoId, $propriedadeId, trim($dados['nome']));
    }

    public function alternarAtivo(int $talhaoId, int $propriedadeId, ?int $usuarioId): void
    {
        $talhao = $this->buscar($talhaoId, $propriedadeId);
        $ativar = (int) $talhao->ativo !== 1;

        if (! $ativar) {
            $bloqueios = $this->bloqueiosSafraAtiva($propriedadeId, $talhaoId);
            if ($bloqueios) {
                throw new RuntimeException('Este talhao possui '.implode(', ', $bloqueios).'. Finalize ou arquive a safra ativa antes de desativar.');
            }
        }

        $nota = $ativar
            ? 'Reativado em '.now()->format('d/m/Y H:i').'.'
            : 'Desativado em '.now()->format('d/m/Y H:i').' sem apagar historico.';

        DB::table('talhoes')
            ->where('id', $talhaoId)
            ->where('propriedade_id', $propriedadeId)
            ->update([
                'ativo' => $ativar ? 1 : 0,
                'descricao' => $this->descricaoComNota($talhao->descricao, $nota),
            ]);

        $this->auditar(
            $usuarioId,
            $ativar ? 'reativar_talhao' : 'desativar_talhao',
            'talhoes',
            $talhaoId,
            $propriedadeId,
            (string) $talhao->nome
        );
    }

    public function unificar(int $propriedadeId, int $destinoId, array $origemIds, bool $somarArea, ?int $usuarioId): int
    {
        $origemIds = collect($origemIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $destinoId)
            ->unique()
            ->values()
            ->all();

        abort_if(empty($origemIds), 422, 'Selecione pelo menos um talhao de origem diferente do destino.');

        $talhoes = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->whereIn('id', array_merge([$destinoId], $origemIds))
            ->get()
            ->keyBy('id');

        $destino = $talhoes->get($destinoId);
        abort_if(! $destino || $talhoes->count() !== count($origemIds) + 1, 422, 'Talhao destino ou origem invalido para esta propriedade.');

        $origens = collect($origemIds)->map(fn ($id) => $talhoes->get($id))->filter()->values();

        $notaAuditoria = '';

        DB::transaction(function () use ($propriedadeId, $destinoId, $origemIds, $destino, $origens, $somarArea, &$notaAuditoria) {
            foreach (['atividades_campo', 'chuvas', 'colheita_talhoes', 'despesas', 'maquina_lancamentos'] as $table) {
                DB::table($table)
                    ->where('propriedade_id', $propriedadeId)
                    ->whereIn('talhao_id', $origemIds)
                    ->update(['talhao_id' => $destinoId]);
            }

            $this->unificarSafraTalhoes($propriedadeId, $destinoId, $origemIds);

            $origemNomes = $origens->pluck('nome')->implode(', ');
            $notaDestino = 'Unificado em '.now()->format('d/m/Y H:i').' com: '.$origemNomes.'.';
            $notaAuditoria = $notaDestino;
            $payloadDestino = [
                'descricao' => $this->descricaoComNota($destino->descricao, $notaDestino),
            ];

            $geometrias = $this->geometriasParaUnificacao(collect([$destino])->merge($origens));
            if ($geometrias !== []) {
                [$coordenadasJson, $exclusoesJson] = $this->serializarGeometrias($geometrias);
                $centro = $this->centroideGeometrias($geometrias);
                $payloadDestino['geometria_tipo'] = 'polygon';
                $payloadDestino['coordenadas_json'] = $coordenadasJson;
                $payloadDestino['exclusoes_json'] = $exclusoesJson;
                $payloadDestino['latitude'] = $centro['lat'];
                $payloadDestino['longitude'] = $centro['lng'];
            }

            if ($somarArea) {
                $payloadDestino['area'] = (float) ($destino->area ?? 0) + $origens->sum(fn ($talhao) => (float) ($talhao->area ?? 0));
                $payloadDestino['area_bruta'] = (float) ($destino->area_bruta ?? $destino->area ?? 0)
                    + $origens->sum(fn ($talhao) => (float) ($talhao->area_bruta ?? $talhao->area ?? 0));
                $payloadDestino['area_excluida_ha'] = (float) ($destino->area_excluida_ha ?? 0)
                    + $origens->sum(fn ($talhao) => (float) ($talhao->area_excluida_ha ?? 0));
            }

            DB::table('talhoes')
                ->where('propriedade_id', $propriedadeId)
                ->where('id', $destinoId)
                ->update($payloadDestino);

            foreach ($origens as $origem) {
                DB::table('talhoes')
                    ->where('propriedade_id', $propriedadeId)
                    ->where('id', $origem->id)
                    ->update([
                        'ativo' => 0,
                        'descricao' => $this->descricaoComNota(
                            $origem->descricao,
                            'Inativado por unificacao com talhao #'.$destinoId.' em '.now()->format('d/m/Y H:i').'.'
                        ),
                    ]);
            }
        });

        $this->auditar($usuarioId, 'unificar_talhoes', 'talhoes', $destinoId, $propriedadeId, $notaAuditoria ?: 'Talhoes unificados');

        return count($origemIds);
    }

    public function criarPorPoligono(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        try {
            $points = $this->geometry->decodeJsonRing((string) ($dados['coordenadas_json'] ?? ''));
        } catch (InvalidPolygon $exception) {
            throw ValidationException::withMessages(['coordenadas_json' => $exception->getMessage()]);
        }

        $centro = $this->centroide($points);
        $area = $this->areaHa($points);

        DB::table('talhoes')->insert([
            'propriedade_id' => $propriedadeId,
            'nome' => trim($dados['nome']),
            'area' => $area,
            'area_bruta' => $area,
            'area_excluida_ha' => 0,
            'descricao' => trim($dados['descricao'] ?? '') ?: null,
            'ativo' => 1,
            'latitude' => $centro['lat'],
            'longitude' => $centro['lng'],
            'geometria_tipo' => 'polygon',
            'coordenadas_json' => json_encode($points, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $talhaoId = (int) DB::getPdo()->lastInsertId();
        $nome = trim($dados['nome']);
        $this->auditar($usuarioId, 'criar_talhao_mapa', 'talhoes', $talhaoId, $propriedadeId, 'Talhao '.$nome.' desenhado no mapa');

        return $talhaoId;
    }

    public function atualizarPoligono(int $talhaoId, array $dados, int $propriedadeId, ?int $usuarioId): void
    {
        $geometrias = $this->decodificarGeometriasDoFormulario((string) ($dados['coordenadas_json'] ?? ''));
        $centro = $this->centroideGeometrias($geometrias);

        DB::transaction(function () use ($talhaoId, $dados, $propriedadeId, $usuarioId, $geometrias, $centro): void {
            $talhao = DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->lockForUpdate()
                ->first();
            abort_if(! $talhao, 404);
            $this->assertMapMutationAllowed($talhao, $propriedadeId, 'coordenadas_json');

            $geometriasAtualizadas = $this->preservarExclusoesAoRedesenhar($talhao, $geometrias);
            $areas = $this->calcularAreasGeometriasEstritas($geometriasAtualizadas);
            [$coordenadasJson, $exclusoesJson] = $this->serializarGeometrias($geometriasAtualizadas);

            DB::table('talhoes')
                ->where('id', $talhaoId)
                ->where('propriedade_id', $propriedadeId)
                ->update([
                    'nome' => trim($dados['nome']),
                    'area' => $areas['liquida'],
                    'area_bruta' => $areas['bruta'],
                    'area_excluida_ha' => $areas['excluida'],
                    'descricao' => trim($dados['descricao'] ?? '') ?: null,
                    'latitude' => $centro['lat'],
                    'longitude' => $centro['lng'],
                    'geometria_tipo' => 'polygon',
                    'coordenadas_json' => $coordenadasJson,
                    'exclusoes_json' => $exclusoesJson,
                ]);

            $this->auditarObrigatorio(
                $usuarioId,
                'salvar_poligono_talhao',
                'talhoes',
                $talhaoId,
                $propriedadeId,
                'Poligono do talhao atualizado. Area recalculada: '.number_format($areas['liquida'], 2, ',', '.').' ha.',
            );
        }, 3);
    }

    public function exportarKmlPropriedade(int $propriedadeId): Response
    {
        $propriedade = DB::table('propriedades')->where('id', $propriedadeId)->first(['nome']);
        $talhoes = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get();

        $nome = $propriedade->nome ?? 'FarmFort';
        $placemarks = '';
        foreach ($talhoes as $talhao) {
            $placemarks .= $this->placemarkTalhao($talhao);
        }

        $kml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<kml xmlns="http://www.opengis.net/kml/2.2"><Document>'
            .'<name>'.$this->xml('FarmFort - '.$nome).'</name>'
            .$placemarks
            .'</Document></kml>';

        return response($kml, 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->slug('farmfort_'.$nome.'_talhoes_'.date('Ymd_His')).'.kml"',
        ]);
    }

    public function exportarTalhao(int $talhaoId, int $propriedadeId, string $formato): Response|BinaryFileResponse
    {
        $talhao = DB::table('talhoes')
            ->where('id', $talhaoId)
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->first();
        abort_unless($talhao, 404);

        $baseName = 'farmfort_'.$this->slug((string) $talhao->nome);
        $formato = strtolower($formato);

        if ($formato === 'kml') {
            $kml = $this->kmlTalhao($talhao);

            return response($kml, 200, [
                'Content-Type' => 'application/vnd.google-earth.kml+xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$baseName.'.kml"',
            ]);
        }

        if ($formato === 'kmz') {
            return $this->zipResponse($baseName.'.kmz', [
                'doc.kml' => $this->kmlTalhao($talhao),
            ], 'application/vnd.google-earth.kmz');
        }

        if ($formato === 'shp') {
            return $this->shpZip($talhao, $baseName);
        }

        abort(404);
    }

    public function importarArquivoGeo(int $propriedadeId, UploadedFile $arquivo, ?string $nomeImportacao = null, ?int $usuarioId = null): array
    {
        $ext = $this->validarArquivoGeo($arquivo);

        $nomeBase = $this->slug(pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME) ?: 'talhoes');
        $arquivoNome = 'talhoes_prop_'.$propriedadeId.'_'.date('YmdHis').'_'.bin2hex(random_bytes(8)).'_'.$nomeBase.'.'.$ext;
        $dir = public_path('uploads/geo');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Nao foi possivel preparar o diretorio de uploads geoespaciais.');
        }
        $arquivo->move($dir, $arquivoNome);
        $relativePath = 'uploads/geo/'.$arquivoNome;
        $absolutePath = $dir.DIRECTORY_SEPARATOR.$arquivoNome;

        try {
            $items = $this->parseGeoFile($absolutePath, $ext);
        } catch (InvalidPolygon $exception) {
            throw new RuntimeException('O arquivo contém um polígono inválido: '.$exception->getMessage(), previous: $exception);
        }
        $items = $this->nomearItensImportados($items, trim((string) $nomeImportacao));
        if (! $items) {
            throw new RuntimeException('Nenhum talhao foi encontrado no arquivo.');
        }

        $imported = 0;
        DB::transaction(function () use ($propriedadeId, $items, $relativePath, $ext, &$imported) {
            foreach ($items as $item) {
                $existingId = DB::table('talhoes')
                    ->where('propriedade_id', $propriedadeId)
                    ->where(function ($query) use ($item) {
                        $query->where('nome', $item['name'])
                            ->orWhere('kml_nome', $item['name']);
                    })
                    ->value('id');

                $payload = [
                    'area' => $item['area'] > 0 ? round($item['area'], 2) : null,
                    'area_bruta' => $item['area'] > 0 ? round($item['area'], 2) : null,
                    'latitude' => $item['centroid']['lat'],
                    'longitude' => $item['centroid']['lng'],
                    'geometria_tipo' => $item['type'],
                    'coordenadas_json' => json_encode($item['points'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'kml_nome' => $item['name'],
                    'kml_arquivo' => $relativePath,
                    'ativo' => 1,
                ];

                if ($existingId) {
                    DB::table('talhoes')
                        ->where('id', $existingId)
                        ->where('propriedade_id', $propriedadeId)
                        ->update($payload);
                } else {
                    DB::table('talhoes')->insert([
                        'propriedade_id' => $propriedadeId,
                        'nome' => $item['name'],
                        'descricao' => 'Importado de '.strtoupper($ext),
                        ...$payload,
                    ]);
                }
                $imported++;
            }
        });

        $this->auditar($usuarioId, 'importar_geometria', 'talhoes', 0, $propriedadeId, "Importados {$imported} talhoes de ".strtoupper($ext));

        return ['imported' => $imported, 'source' => strtoupper($ext)];
    }

    private function validarArquivoGeo(UploadedFile $arquivo): string
    {
        if (! $arquivo->isValid()) {
            throw new RuntimeException('Nao foi possivel receber o arquivo geoespacial.');
        }

        $size = (int) $arquivo->getSize();
        if ($size <= 0 || $size > self::GEO_UPLOAD_MAX_BYTES) {
            throw new RuntimeException('O arquivo geoespacial precisa ter conteudo e no maximo 20 MB.');
        }

        $clientExt = strtolower(pathinfo($arquivo->getClientOriginalName(), PATHINFO_EXTENSION));
        $detectedExt = strtolower((string) $arquivo->extension());
        $ext = in_array($clientExt, self::GEO_UPLOAD_EXTENSIONS, true) ? $clientExt : $detectedExt;

        if (! in_array($ext, self::GEO_UPLOAD_EXTENSIONS, true)) {
            throw new RuntimeException('Envie um arquivo KML, KMZ, SHP ou ZIP.');
        }

        return $ext;
    }

    private function filtros(Request $request): array
    {
        $status = (string) $request->query('status', 'ativos');
        if (! in_array($status, ['ativos', 'desativados', 'todos'], true)) {
            $status = 'ativos';
        }

        return [
            'status' => $status,
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    private function safrasEmUso(int $propriedadeId, ?int $talhaoId = null): Collection
    {
        return DB::table('safra_talhoes as st')
            ->join('safras as s', function ($join) {
                $join->on('s.id', '=', 'st.safra_id')
                    ->on('s.propriedade_id', '=', 'st.propriedade_id');
            })
            ->where('st.propriedade_id', $propriedadeId)
            ->whereIn('s.status', $this->mapCapabilities->blockingStatuses())
            ->when($talhaoId !== null, fn ($query) => $query->where('st.talhao_id', $talhaoId))
            ->orderByDesc('s.data_inicio')
            ->orderByDesc('s.id')
            ->get([
                'st.talhao_id',
                's.id as safra_id',
                's.descricao as safra_nome',
                's.status',
            ]);
    }

    /**
     * @return list<array{nome: string, status: string}>
     */
    private function normalizarSafrasEmUso(Collection $safras): array
    {
        return $safras
            ->map(fn ($safra): array => [
                'nome' => trim((string) ($safra->safra_nome ?? '')),
                'status' => (string) ($safra->status ?? ''),
            ])
            ->values()
            ->all();
    }

    private function assertMapMutationAllowed(object $talhao, int $propriedadeId, string $errorKey): void
    {
        if ((int) ($talhao->ativo ?? 0) !== 1) {
            throw ValidationException::withMessages([$errorKey => 'Talhão inativo.']);
        }

        $capabilities = $this->mapCapabilities->for(
            $this->normalizarSafrasEmUso($this->safrasEmUso($propriedadeId, (int) $talhao->id)),
        );

        if (! $capabilities['can_edit_geometry']) {
            throw ValidationException::withMessages([
                $errorKey => (string) $capabilities['block_reason'],
            ]);
        }
    }

    private function talhoesAtivos(int $propriedadeId): Collection
    {
        return DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome', 'area'])
            ->map(fn ($talhao) => (object) [
                'id' => (int) $talhao->id,
                'nome' => (string) $talhao->nome,
                'area' => FarmFormat::decimal($talhao->area, 2).' ha',
            ]);
    }

    private function bloqueiosSafraAtiva(int $propriedadeId, int $talhaoId): array
    {
        $checks = [
            'vinculo com safra ativa' => DB::table('safra_talhoes as st')
                ->join('safras as s', function ($join) {
                    $join->on('s.id', '=', 'st.safra_id')
                        ->on('s.propriedade_id', '=', 'st.propriedade_id');
                })
                ->where('st.propriedade_id', $propriedadeId)
                ->where('st.talhao_id', $talhaoId)
                ->where('s.status', 'em_andamento')
                ->whereNull('st.colheita_finalizada_em'),
            'lancamentos financeiros na safra ativa' => DB::table('despesas as d')
                ->join('safras as s', function ($join) {
                    $join->on('s.id', '=', 'd.safra_id')
                        ->on('s.propriedade_id', '=', 'd.propriedade_id');
                })
                ->where('d.propriedade_id', $propriedadeId)
                ->where('d.talhao_id', $talhaoId)
                ->where('s.status', 'em_andamento'),
            'atividades de campo na safra ativa' => DB::table('atividades_campo as a')
                ->join('safras as s', function ($join) {
                    $join->on('s.id', '=', 'a.safra_id')
                        ->on('s.propriedade_id', '=', 'a.propriedade_id');
                })
                ->where('a.propriedade_id', $propriedadeId)
                ->where('a.talhao_id', $talhaoId)
                ->where('s.status', 'em_andamento'),
            'colheitas na safra ativa' => DB::table('colheita_talhoes as c')
                ->join('safras as s', function ($join) {
                    $join->on('s.id', '=', 'c.safra_id')
                        ->on('s.propriedade_id', '=', 'c.propriedade_id');
                })
                ->where('c.propriedade_id', $propriedadeId)
                ->where('c.talhao_id', $talhaoId)
                ->where('s.status', 'em_andamento'),
            'lancamentos de maquinas na safra ativa' => DB::table('maquina_lancamentos as ml')
                ->join('safras as s', function ($join) {
                    $join->on('s.id', '=', 'ml.safra_id')
                        ->on('s.propriedade_id', '=', 'ml.propriedade_id');
                })
                ->where('ml.propriedade_id', $propriedadeId)
                ->where('ml.talhao_id', $talhaoId)
                ->where('s.status', 'em_andamento'),
        ];

        $bloqueios = [];
        foreach ($checks as $label => $query) {
            if ((clone $query)->count() > 0) {
                $bloqueios[] = $label;
            }
        }

        return $bloqueios;
    }

    private function rows(int $propriedadeId, array $filtros): Collection
    {
        $query = DB::table('talhoes as t')
            ->leftJoin('despesas as d', function ($join) {
                $join->on('d.talhao_id', '=', 't.id')
                    ->where('d.status_pagamento', '!=', 'cancelado')
                    ->where('d.status_aprovacao', '=', 'aprovada');
            })
            ->where('t.propriedade_id', $propriedadeId);

        if ($filtros['status'] === 'ativos') {
            $query->where('t.ativo', 1);
        } elseif ($filtros['status'] === 'desativados') {
            $query->where('t.ativo', 0);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('t.nome', 'like', $term)
                    ->orWhere('t.descricao', 'like', $term)
                    ->orWhere('t.kml_nome', 'like', $term)
                    ->orWhere('t.geometria_tipo', 'like', $term);
            });
        }

        return $query
            ->groupBy(
                't.id',
                't.nome',
                't.area',
                't.area_bruta',
                't.area_excluida_ha',
                't.descricao',
                't.ativo',
                't.latitude',
                't.longitude',
                't.geometria_tipo',
                't.kml_nome',
                't.pivo_ativo',
                't.pivo_area_ha'
            )
            ->orderByDesc('t.ativo')
            ->orderBy('t.nome')
            ->get([
                't.id',
                't.nome',
                't.area',
                't.area_bruta',
                't.area_excluida_ha',
                't.descricao',
                't.ativo',
                't.latitude',
                't.longitude',
                't.geometria_tipo',
                't.kml_nome',
                't.pivo_ativo',
                't.pivo_area_ha',
                DB::raw('COUNT(DISTINCT d.id) as qtd_despesas'),
                DB::raw('COALESCE(SUM(d.valor_total), 0) as total_despesas'),
            ])
            ->map(fn ($row) => $this->normalizarLinha($row));
    }

    private function normalizarLinha($row): object
    {
        $area = (float) $row->area;
        $total = (float) $row->total_despesas;
        $lat = $row->latitude !== null ? (float) $row->latitude : null;
        $lng = $row->longitude !== null ? (float) $row->longitude : null;

        return (object) [
            'id' => (int) $row->id,
            'nome' => FarmFormat::value($row->nome),
            'kml_nome' => FarmFormat::value($row->kml_nome),
            'area_raw' => $area,
            'area' => FarmFormat::decimal($area, 2).' ha',
            'area_bruta' => FarmFormat::decimal($row->area_bruta, 2).' ha',
            'area_excluida' => FarmFormat::decimal($row->area_excluida_ha, 2).' ha',
            'geometria' => FarmFormat::value($row->geometria_tipo ?: 'Manual'),
            'centroide' => $lat !== null && $lng !== null
                ? number_format($lat, 6, '.', '').', '.number_format($lng, 6, '.', '')
                : '-',
            'georreferenciado' => $lat !== null && $lng !== null,
            'qtd_despesas' => (int) $row->qtd_despesas,
            'total_despesas_raw' => $total,
            'total_despesas' => FarmFormat::money($total),
            'custo_ha' => $area > 0 ? FarmFormat::money($total / $area) : '-',
            'descricao' => FarmFormat::value($row->descricao),
            'pivo' => (int) $row->pivo_ativo === 1 ? 'Ativo' : 'Não',
            'pivo_ativo' => (int) $row->pivo_ativo === 1,
            'pivo_area' => FarmFormat::decimal($row->pivo_area_ha, 2).' ha',
            'ativo' => (int) $row->ativo === 1,
            'status' => (int) $row->ativo === 1 ? 'Ativo' : 'Desativado',
        ];
    }

    private function unificarSafraTalhoes(int $propriedadeId, int $destinoId, array $origemIds): void
    {
        $vinculos = DB::table('safra_talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->whereIn('talhao_id', $origemIds)
            ->get();

        foreach ($vinculos as $vinculo) {
            $destino = DB::table('safra_talhoes')
                ->where('safra_id', $vinculo->safra_id)
                ->where('talhao_id', $destinoId)
                ->first();

            if ($destino) {
                if ($destino->colheita_finalizada_em === null && $vinculo->colheita_finalizada_em !== null) {
                    DB::table('safra_talhoes')
                        ->where('safra_id', $vinculo->safra_id)
                        ->where('talhao_id', $destinoId)
                        ->update([
                            'colheita_finalizada_em' => $vinculo->colheita_finalizada_em,
                            'colheita_finalizada_por' => $vinculo->colheita_finalizada_por,
                        ]);
                }

                continue;
            }

            DB::table('safra_talhoes')->insert([
                'safra_id' => $vinculo->safra_id,
                'talhao_id' => $destinoId,
                'propriedade_id' => $propriedadeId,
                'colheita_finalizada_em' => $vinculo->colheita_finalizada_em,
                'colheita_finalizada_por' => $vinculo->colheita_finalizada_por,
                'criado_em' => $vinculo->criado_em,
            ]);
        }

        DB::table('safra_talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->whereIn('talhao_id', $origemIds)
            ->delete();
    }

    private function descricaoComNota(?string $descricao, string $nota): string
    {
        $descricao = trim((string) $descricao);

        return $descricao === '' ? $nota : $descricao."\n".$nota;
    }

    private function parseGeoFile(string $path, string $ext): array
    {
        if ($ext === 'kml') {
            $raw = file_get_contents($path);

            return $raw === false ? [] : $this->parseKmlContent($raw, 'Talhao KML');
        }

        if ($ext === 'shp') {
            $raw = file_get_contents($path);

            return $raw === false ? [] : $this->parseShpContent($raw, pathinfo($path, PATHINFO_FILENAME) ?: 'Talhao SHP');
        }

        abort_unless(class_exists(ZipArchive::class), 500, 'Extensao ZIP nao disponivel.');
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }

        if ($zip->numFiles > self::GEO_ZIP_MAX_ENTRIES) {
            $zip->close();

            throw new RuntimeException('O arquivo geoespacial possui arquivos internos demais.');
        }

        $shpIndexes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! $name) {
                continue;
            }
            if (! $this->zipGeoEntryPermitida($name)) {
                continue;
            }
            $entryExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($entryExt === 'kml') {
                $raw = $zip->getFromIndex($i);
                $zip->close();

                return $raw === false ? [] : $this->parseKmlContent($raw, 'Talhao KMZ');
            }
            if ($entryExt === 'shp') {
                $shpIndexes[] = $i;
            }
        }

        $items = [];
        foreach ($shpIndexes as $index) {
            $raw = $zip->getFromIndex($index);
            $name = $zip->getNameIndex($index);
            if ($raw !== false) {
                $items = array_merge($items, $this->parseShpContent($raw, pathinfo((string) $name, PATHINFO_FILENAME) ?: 'Talhao SHP'));
            }
        }

        $zip->close();

        return $items;
    }

    private function zipGeoEntryPermitida(string $name): bool
    {
        $normalized = str_replace('\\', '/', $name);
        if ($normalized === '' || str_contains($normalized, "\0")) {
            return false;
        }

        if (str_starts_with($normalized, '/') || preg_match('/^[a-zA-Z]:\//', $normalized) === 1) {
            return false;
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return in_array(strtolower(pathinfo($normalized, PATHINFO_EXTENSION)), ['kml', 'shp'], true);
    }

    private function parseKmlContent(string $raw, string $defaultName): array
    {
        $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET);
        if (! $xml) {
            return [];
        }

        $placemarks = $xml->xpath('//*[local-name()="Placemark"]') ?: [];
        $items = [];
        foreach ($placemarks as $index => $placemark) {
            $nameNode = $placemark->xpath('./*[local-name()="name"]');
            $name = $nameNode ? trim((string) $nameNode[0]) : $defaultName.' '.($index + 1);
            $coordNode = $placemark->xpath('.//*[local-name()="coordinates"]');
            if (! $coordNode) {
                continue;
            }

            $points = $this->pointsFromKmlCoordinates((string) $coordNode[0]);
            if (! $points) {
                continue;
            }

            $isPolygon = (bool) $placemark->xpath('.//*[local-name()="Polygon"]');
            $isLine = (bool) $placemark->xpath('.//*[local-name()="LineString"]');
            $type = $isPolygon ? 'polygon' : ($isLine ? 'line' : 'point');
            $centroid = $this->centroide($points);

            $items[] = [
                'name' => $name !== '' ? $name : $defaultName.' '.($index + 1),
                'points' => $points,
                'centroid' => $centroid,
                'type' => $type,
                'area' => $type === 'polygon' ? $this->areaHa($points) : 0,
            ];
        }

        return $items;
    }

    private function pointsFromKmlCoordinates(string $coordinates): array
    {
        $points = [];
        foreach (preg_split('/\s+/', trim($coordinates)) ?: [] as $coord) {
            if ($coord === '') {
                continue;
            }
            $parts = explode(',', $coord);
            if (count($parts) < 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                continue;
            }
            $points[] = ['lat' => (float) $parts[1], 'lng' => (float) $parts[0]];
        }

        return $points;
    }

    private function nomearItensImportados(array $items, string $nomeImportacao): array
    {
        if ($nomeImportacao === '') {
            return $items;
        }

        $total = count($items);
        foreach ($items as $index => $item) {
            $items[$index]['name'] = $total === 1
                ? $nomeImportacao
                : $nomeImportacao.' '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        }

        return $items;
    }

    private function parseShpContent(string $data, string $baseName): array
    {
        if (strlen($data) < 100 || $this->beInt32($data, 0) !== 9994) {
            return [];
        }

        $items = [];
        $offset = 100;
        $recordFallback = 1;
        $length = strlen($data);

        while ($offset + 8 <= $length) {
            $recordNumber = $this->beInt32($data, $offset) ?: $recordFallback;
            $contentBytes = $this->beInt32($data, $offset + 4) * 2;
            $offset += 8;
            if ($contentBytes <= 0 || $offset + $contentBytes > $length) {
                break;
            }

            $record = substr($data, $offset, $contentBytes);
            $offset += $contentBytes;
            $recordFallback++;
            if (strlen($record) < 4) {
                continue;
            }

            $shapeType = $this->leInt32($record, 0);
            if ($shapeType === 0) {
                continue;
            }

            if (in_array($shapeType, [1, 11, 21], true) && strlen($record) >= 20) {
                $point = ['lng' => $this->leDouble($record, 4), 'lat' => $this->leDouble($record, 12)];
                if ($this->pointsAreLatLng([$point])) {
                    $items[] = [
                        'name' => trim($baseName).' '.$recordNumber,
                        'points' => [$point],
                        'centroid' => $point,
                        'type' => 'point',
                        'area' => 0,
                    ];
                }

                continue;
            }

            if (! in_array($shapeType, [3, 5, 13, 15, 23, 25], true) || strlen($record) < 44) {
                continue;
            }

            $type = in_array($shapeType, [5, 15, 25], true) ? 'polygon' : 'line';
            $numParts = $this->leInt32($record, 36);
            $numPoints = $this->leInt32($record, 40);
            if ($numParts < 1 || $numPoints < 1) {
                continue;
            }

            $partsOffset = 44;
            $pointsOffset = $partsOffset + ($numParts * 4);
            if ($pointsOffset + ($numPoints * 16) > strlen($record)) {
                continue;
            }

            $parts = [];
            for ($i = 0; $i < $numParts; $i++) {
                $parts[] = $this->leInt32($record, $partsOffset + ($i * 4));
            }
            $parts[] = $numPoints;

            $points = [];
            for ($i = 0; $i < $numPoints; $i++) {
                $points[] = [
                    'lng' => $this->leDouble($record, $pointsOffset + ($i * 16)),
                    'lat' => $this->leDouble($record, $pointsOffset + ($i * 16) + 8),
                ];
            }

            for ($part = 0; $part < $numParts; $part++) {
                $partPoints = array_slice($points, $parts[$part], max(0, $parts[$part + 1] - $parts[$part]));
                if (! $partPoints || ! $this->pointsAreLatLng($partPoints)) {
                    continue;
                }

                $items[] = [
                    'name' => trim($baseName).' '.$recordNumber.($numParts > 1 ? '-'.($part + 1) : ''),
                    'points' => $partPoints,
                    'centroid' => $this->centroide($partPoints),
                    'type' => $type,
                    'area' => $type === 'polygon' ? $this->areaHa($partPoints) : 0,
                ];
            }
        }

        return $items;
    }

    private function pointsAreLatLng(array $points): bool
    {
        foreach ($points as $point) {
            if (! isset($point['lat'], $point['lng'])) {
                return false;
            }
            if ((float) $point['lat'] < -90 || (float) $point['lat'] > 90) {
                return false;
            }
            if ((float) $point['lng'] < -180 || (float) $point['lng'] > 180) {
                return false;
            }
        }

        return true;
    }

    private function leInt32(string $data, int $offset): int
    {
        $value = unpack('V', substr($data, $offset, 4));

        return (int) ($value[1] ?? 0);
    }

    private function beInt32(string $data, int $offset): int
    {
        $value = unpack('N', substr($data, $offset, 4));

        return (int) ($value[1] ?? 0);
    }

    private function leDouble(string $data, int $offset): float
    {
        $value = unpack('e', substr($data, $offset, 8));

        return (float) ($value[1] ?? 0);
    }

    private function talhaoMapa($talhao): array
    {
        $geometrias = $this->geometriasTalhao($talhao);
        $points = $geometrias[0]['points'] ?? $this->normalizarPontos($talhao->coordenadas_json ?? '');
        $centro = $geometrias !== [] ? $this->centroideGeometrias($geometrias) : null;
        $lat = $talhao->latitude !== null ? (float) $talhao->latitude : ($centro['lat'] ?? ($points[0]['lat'] ?? null));
        $lng = $talhao->longitude !== null ? (float) $talhao->longitude : ($centro['lng'] ?? ($points[0]['lng'] ?? null));
        $tipo = $talhao->geometria_tipo ?: (count($points) >= 3 ? 'polygon' : 'point');
        $area = (float) $talhao->area;
        $exclusoes = $this->exclusoesTalhao($talhao);
        $geometriasCount = count($geometrias);
        $downloadUrl = route('talhoes.exportar-talhao', ['talhao' => (int) $talhao->id]);

        return [
            'id' => (int) $talhao->id,
            'nome' => $talhao->nome,
            'descricao' => $talhao->descricao ?? '',
            'area' => $area,
            'area_formatada' => FarmFormat::decimal($area, 2).' ha',
            'area_bruta' => (float) ($talhao->area_bruta ?? $talhao->area ?? 0),
            'area_excluida_ha' => (float) ($talhao->area_excluida_ha ?? 0),
            'area_excluida_formatada' => FarmFormat::decimal($talhao->area_excluida_ha ?? 0, 2).' ha',
            'lat' => $lat,
            'lng' => $lng,
            'tipo' => $tipo,
            'tipo_label' => match ($tipo) {
                'polygon' => $geometriasCount > 1 ? 'Poligono composto' : 'Polígono',
                'line' => 'Linha',
                'point' => 'Ponto',
                default => 'Manual',
            },
            'tem_geometria' => ($lat !== null && $lng !== null) || count($points) >= 2 || $geometriasCount > 0,
            'points' => $points,
            'geometries' => $geometrias,
            'geometrias_count' => $geometriasCount,
            'exclusoes' => $exclusoes,
            'exclusoes_count' => count($exclusoes),
            'pivo_ativo' => (int) ($talhao->pivo_ativo ?? 0) === 1,
            'pivo_lat' => $talhao->pivo_lat !== null ? (float) $talhao->pivo_lat : null,
            'pivo_lng' => $talhao->pivo_lng !== null ? (float) $talhao->pivo_lng : null,
            'pivo_raio_m' => $talhao->pivo_raio_m !== null ? (float) $talhao->pivo_raio_m : null,
            'pivo_area_ha' => $talhao->pivo_area_ha !== null ? (float) $talhao->pivo_area_ha : null,
            'pivo_area_formatada' => $talhao->pivo_area_ha !== null ? FarmFormat::decimal($talhao->pivo_area_ha, 2).' ha' : '',
            'google_url' => ($lat !== null && $lng !== null) ? 'https://www.google.com/maps?q='.$lat.','.$lng : null,
            'download_url' => $downloadUrl,
            'download_urls' => [
                'kml' => $downloadUrl.'?formato=kml',
                'kmz' => $downloadUrl.'?formato=kmz',
                'shp' => $downloadUrl.'?formato=shp',
            ],
        ];
    }

    private function normalizarPontos($json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;
        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['geometries']) && is_array($decoded['geometries'])) {
            $firstGeometry = $decoded['geometries'][0] ?? [];

            return $this->normalizarListaPontos(is_array($firstGeometry) ? ($firstGeometry['points'] ?? []) : []);
        }

        if (isset($decoded['points']) && is_array($decoded['points'])) {
            return $this->normalizarListaPontos($decoded['points']);
        }

        if (! $this->pareceListaPontos($decoded)) {
            foreach ($decoded as $item) {
                if (is_array($item) && $this->pareceListaPontos($item)) {
                    return $this->normalizarListaPontos($item);
                }
            }

            return [];
        }

        return $this->normalizarListaPontos($decoded);
    }

    private function normalizarListaPontos(array $decoded): array
    {
        $points = [];
        foreach ($decoded as $point) {
            if (! is_array($point)) {
                continue;
            }
            $lat = $point['lat'] ?? ($point[0] ?? null);
            $lng = $point['lng'] ?? ($point[1] ?? null);
            if (is_numeric($lat) && is_numeric($lng)) {
                $points[] = ['lat' => (float) $lat, 'lng' => (float) $lng];
            }
        }

        return $points;
    }

    private function pareceListaPontos(array $decoded): bool
    {
        foreach ($decoded as $point) {
            return is_array($point)
                && (
                    (isset($point['lat'], $point['lng']) && is_numeric($point['lat']) && is_numeric($point['lng']))
                    || (isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1]))
                );
        }

        return false;
    }

    private function pontosTalhao($talhao): array
    {
        $points = $this->normalizarPontos($talhao->coordenadas_json ?? '');
        if (! $points && $talhao->latitude !== null && $talhao->longitude !== null) {
            $points[] = ['lat' => (float) $talhao->latitude, 'lng' => (float) $talhao->longitude];
        }

        return $points;
    }

    private function geometriasTalhao($talhao): array
    {
        return $this->normalizarGeometrias(
            $talhao->coordenadas_json ?? '',
            $this->exclusoesJsonRings($talhao->exclusoes_json ?? null),
        );
    }

    private function normalizarGeometrias($json, array $fallbackExclusoes = []): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;
        if (! is_array($decoded)) {
            return [];
        }

        $candidatas = [];
        if (isset($decoded['geometries']) && is_array($decoded['geometries'])) {
            $candidatas = $decoded['geometries'];
        } elseif (isset($decoded['points']) && is_array($decoded['points'])) {
            $candidatas = [$decoded];
        } elseif ($this->pareceListaPontos($decoded)) {
            $candidatas = [['points' => $decoded, 'exclusions' => $fallbackExclusoes]];
        } else {
            foreach ($decoded as $item) {
                if (is_array($item) && (isset($item['points']) || $this->pareceListaPontos($item))) {
                    $candidatas[] = isset($item['points'])
                        ? $item
                        : ['points' => $item, 'exclusions' => []];
                }
            }
        }

        $geometrias = [];
        foreach ($candidatas as $candidata) {
            if (! is_array($candidata)) {
                continue;
            }

            $points = $this->normalizarListaPontos($candidata['points'] ?? $candidata);
            if (count($points) < 3) {
                continue;
            }

            $geometrias[] = [
                'points' => $points,
                'exclusions' => $this->exclusoesJsonRings($candidata['exclusions'] ?? []),
            ];
        }

        return $geometrias;
    }

    private function decodificarGeometriasDoFormulario(string $json): array
    {
        $geometrias = $this->normalizarGeometrias($json);
        if ($geometrias === []) {
            try {
                $geometrias = [[
                    'points' => $this->geometry->decodeJsonRing($json),
                    'exclusions' => [],
                ]];
            } catch (InvalidPolygon $exception) {
                throw ValidationException::withMessages(['coordenadas_json' => $exception->getMessage()]);
            }
        }

        if ($geometrias === []) {
            throw ValidationException::withMessages([
                'coordenadas_json' => 'Informe pelo menos um polígono válido para o talhão.',
            ]);
        }

        return $geometrias;
    }

    private function preservarExclusoesAoRedesenhar($talhao, array $geometrias): array
    {
        if (count($geometrias) === 1 && ($geometrias[0]['exclusions'] ?? []) === []) {
            $geometrias[0]['exclusions'] = $this->exclusoesTalhaoEstritas($talhao);

            return $geometrias;
        }

        $atuais = $this->geometriasTalhao($talhao);
        foreach ($geometrias as $indice => $geometria) {
            if (($geometria['exclusions'] ?? []) === [] && isset($atuais[$indice]['exclusions'])) {
                $geometrias[$indice]['exclusions'] = $atuais[$indice]['exclusions'];
            }
        }

        return $geometrias;
    }

    private function calcularAreasGeometriasEstritas(array $geometrias): array
    {
        $areas = ['bruta' => 0.0, 'excluida' => 0.0, 'liquida' => 0.0];
        foreach ($geometrias as $geometria) {
            try {
                $breakdown = $this->geometry->areaBreakdown(
                    $this->geometry->normalizeRing($geometria['points'] ?? []),
                    array_map(fn (array $ring) => $this->geometry->normalizeRing($ring), $geometria['exclusions'] ?? []),
                );
            } catch (InvalidPolygon $exception) {
                throw ValidationException::withMessages([
                    'coordenadas_json' => 'O novo polígono não comporta as exclusões existentes. Limpe ou ajuste as exclusões antes de redesenhar.',
                ]);
            }

            $areas['bruta'] += (float) $breakdown['bruta'];
            $areas['excluida'] += (float) $breakdown['excluida'];
            $areas['liquida'] += (float) $breakdown['liquida'];
        }

        return [
            'bruta' => round($areas['bruta'], 2),
            'excluida' => round($areas['excluida'], 2),
            'liquida' => round($areas['liquida'], 2),
        ];
    }

    private function exclusoesTalhao($talhao): array
    {
        $geometrias = $this->geometriasTalhao($talhao);
        if (count($geometrias) > 1) {
            return $this->flattenExclusoesGeometrias($geometrias);
        }

        return $this->exclusoesJsonRings($talhao->exclusoes_json ?? null);
    }

    private function exclusoesJsonRings($json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;
        if (! is_array($decoded)) {
            return [];
        }

        $rings = [];
        foreach ($decoded as $ring) {
            $points = $this->normalizarPontos($ring);
            if (count($points) >= 3) {
                $rings[] = $points;
            }
        }

        return $rings;
    }

    private function exclusoesTalhaoEstritas($talhao): array
    {
        if (count($this->geometriasTalhao($talhao)) > 1) {
            throw new RuntimeException('Talhao composto deve ser ajustado pelo editor de areas excluidas.');
        }

        if ($talhao->exclusoes_json === null || trim((string) $talhao->exclusoes_json) === '') {
            return [];
        }

        try {
            $decoded = json_decode((string) $talhao->exclusoes_json, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new RuntimeException('As áreas excluídas existentes não formam uma lista válida.');
            }

            return array_values(array_map(fn (array $ring) => $this->geometry->normalizeRing($ring), $decoded));
        } catch (\Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('As áreas excluídas existentes estão corrompidas.', previous: $exception);
        }
    }

    private function geometriasParaUnificacao(Collection $talhoes): array
    {
        $geometrias = [];
        foreach ($talhoes as $talhao) {
            foreach ($this->geometriasTalhao($talhao) as $geometria) {
                $geometrias[] = $geometria;
            }
        }

        return $geometrias;
    }

    private function serializarGeometrias(array $geometrias): array
    {
        $geometrias = array_values(array_filter($geometrias, fn (array $geometria) => count($geometria['points'] ?? []) >= 3));
        if ($geometrias === []) {
            return [null, null];
        }

        if (count($geometrias) === 1) {
            return [
                json_encode($geometrias[0]['points'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ($geometrias[0]['exclusions'] ?? []) !== []
                    ? json_encode($geometrias[0]['exclusions'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
            ];
        }

        return [
            json_encode([
                'type' => 'MultiPolygon',
                'geometries' => array_map(fn (array $geometria) => [
                    'points' => array_values($geometria['points']),
                    'exclusions' => array_values($geometria['exclusions'] ?? []),
                ], $geometrias),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            null,
        ];
    }

    private function centroideGeometrias(array $geometrias): array
    {
        $points = [];
        foreach ($geometrias as $geometria) {
            $points = array_merge($points, $geometria['points'] ?? []);
        }

        if ($points === []) {
            return ['lat' => null, 'lng' => null];
        }

        return [
            'lat' => array_sum(array_column($points, 'lat')) / count($points),
            'lng' => array_sum(array_column($points, 'lng')) / count($points),
        ];
    }

    private function flattenExclusoesGeometrias(array $geometrias): array
    {
        $exclusoes = [];
        foreach ($geometrias as $geometria) {
            foreach (($geometria['exclusions'] ?? []) as $ring) {
                if (count($ring) >= 3) {
                    $exclusoes[] = $ring;
                }
            }
        }

        return $exclusoes;
    }

    private function areasGeometrias(array $geometrias): array
    {
        $areas = ['bruta' => 0.0, 'excluida' => 0.0, 'liquida' => 0.0];
        foreach ($geometrias as $geometria) {
            $points = $geometria['points'] ?? [];
            if (count($points) < 3) {
                continue;
            }

            try {
                $breakdown = $this->geometry->areaBreakdown(
                    $this->geometry->normalizeRing($points),
                    array_map(fn (array $ring) => $this->geometry->normalizeRing($ring), $geometria['exclusions'] ?? []),
                );
            } catch (InvalidPolygon $exception) {
                $bruta = $this->areaHa($points);
                $breakdown = ['bruta' => $bruta, 'excluida' => 0.0, 'liquida' => $bruta];
            }

            $areas['bruta'] += (float) $breakdown['bruta'];
            $areas['excluida'] += (float) $breakdown['excluida'];
            $areas['liquida'] += (float) $breakdown['liquida'];
        }

        return [
            'bruta' => round($areas['bruta'], 2),
            'excluida' => round($areas['excluida'], 2),
            'liquida' => round($areas['liquida'], 2),
        ];
    }

    private function salvarExclusaoEmGeometriaComposta($talhao, int $propriedadeId, array $geometrias, array $exclusion): bool
    {
        foreach ($geometrias as $indice => $geometria) {
            try {
                $outer = $this->geometry->normalizeRing($geometria['points'] ?? []);
                $existingRings = array_map(
                    fn (array $ring) => $this->geometry->normalizeRing($ring),
                    $geometria['exclusions'] ?? [],
                );
                $this->geometry->areaBreakdown($outer, $existingRings);
                $rings = $this->geometry->appendExclusion($outer, $existingRings, $exclusion);
            } catch (InvalidPolygon $exception) {
                continue;
            }

            if ($rings === $existingRings) {
                return false;
            }

            $geometrias[$indice]['exclusions'] = $rings;
            [$coordenadasJson, $exclusoesJson] = $this->serializarGeometrias($geometrias);
            $areas = $this->areasGeometrias($geometrias);

            DB::table('talhoes')
                ->where('id', (int) $talhao->id)
                ->where('propriedade_id', $propriedadeId)
                ->update([
                    'coordenadas_json' => $coordenadasJson,
                    'exclusoes_json' => $exclusoesJson,
                    'area' => $areas['liquida'],
                    'area_bruta' => $areas['bruta'],
                    'area_excluida_ha' => $areas['excluida'],
                ]);

            return true;
        }

        throw ValidationException::withMessages([
            'exclusao_json' => 'A area excluida precisa ficar dentro de uma das partes do talhao unificado.',
        ]);
    }

    private function tipoGeo($talhao, array $points): string
    {
        $type = $talhao->geometria_tipo ?: (count($points) > 1 ? 'line' : 'point');
        if ($type === 'polygon' && count($points) < 3) {
            return count($points) > 1 ? 'line' : 'point';
        }
        if ($type === 'line' && count($points) < 2) {
            return count($points) === 1 ? 'point' : '';
        }
        if ($type === 'point' && count($points) < 1) {
            return '';
        }

        return $type;
    }

    private function kmlTalhao($talhao): string
    {
        $placemark = $this->placemarkTalhao($talhao);
        abort_if($placemark === '', 422, 'Este talhao nao possui geometria para exportar.');

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<kml xmlns="http://www.opengis.net/kml/2.2"><Document>'
            .'<name>'.$this->xml((string) $talhao->nome).'</name>'
            .$placemark
            .'</Document></kml>';
    }

    private function placemarkTalhao($talhao): string
    {
        $points = $this->pontosTalhao($talhao);
        $type = $this->tipoGeo($talhao, $points);
        if (! $type || ! $points) {
            return '';
        }

        $description = trim((string) ($talhao->descricao ?? '')."\nArea: ".FarmFormat::decimal($talhao->area ?? 0, 2).' ha');
        $geometry = $this->kmlGeometry($talhao, $points, $type);
        $pivo = $this->pivoPlacemark($talhao);

        return '<Placemark><name>'.$this->xml((string) $talhao->nome).'</name>'
            .'<description>'.$this->xml($description).'</description>'
            .$geometry
            .'</Placemark>'.$pivo;
    }

    private function kmlGeometry($talhao, array $points, string $type): string
    {
        if ($type === 'polygon') {
            $geometrias = $this->geometriasTalhao($talhao);
            if (count($geometrias) > 1) {
                return '<MultiGeometry>'.implode('', array_map(
                    fn (array $geometria) => $this->kmlPolygonGeometry($geometria['points'], $geometria['exclusions'] ?? []),
                    $geometrias
                )).'</MultiGeometry>';
            }

            return $this->kmlPolygonGeometry($points, $this->exclusoesTalhao($talhao));
        }

        if ($type === 'line') {
            return '<LineString><coordinates>'.$this->kmlCoordinates($points).'</coordinates></LineString>';
        }

        return '<Point><coordinates>'.$this->kmlCoordinates([$points[0]]).'</coordinates></Point>';
    }

    private function kmlPolygonGeometry(array $points, array $exclusions = []): string
    {
        if ($points[0] !== end($points)) {
            $points[] = $points[0];
        }

        $inner = '';
        foreach ($exclusions as $ring) {
            if ($ring[0] !== end($ring)) {
                $ring[] = $ring[0];
            }
            $inner .= '<innerBoundaryIs><LinearRing><coordinates>'.$this->kmlCoordinates($ring).'</coordinates></LinearRing></innerBoundaryIs>';
        }

        return '<Polygon><outerBoundaryIs><LinearRing><coordinates>'.$this->kmlCoordinates($points).'</coordinates></LinearRing></outerBoundaryIs>'.$inner.'</Polygon>';
    }

    private function pivoPlacemark($talhao): string
    {
        if (empty($talhao->pivo_ativo) || $talhao->pivo_lat === null || $talhao->pivo_lng === null || (float) $talhao->pivo_raio_m <= 0) {
            return '';
        }

        $points = $this->circlePoints((float) $talhao->pivo_lat, (float) $talhao->pivo_lng, (float) $talhao->pivo_raio_m);
        if (! $points) {
            return '';
        }

        $description = 'Raio: '.FarmFormat::decimal($talhao->pivo_raio_m, 2).' m'."\nArea: ".FarmFormat::decimal($talhao->pivo_area_ha ?? 0, 2).' ha';

        return '<Placemark><name>'.$this->xml((string) $talhao->nome.' - Pivo').'</name>'
            .'<description>'.$this->xml($description).'</description>'
            .'<LineString><coordinates>'.$this->kmlCoordinates($points).'</coordinates></LineString>'
            .'</Placemark>';
    }

    private function kmlCoordinates(array $points): string
    {
        return implode(' ', array_map(
            fn ($p) => number_format((float) $p['lng'], 12, '.', '').','.number_format((float) $p['lat'], 12, '.', '').',0',
            $points
        ));
    }

    private function circlePoints(float $lat, float $lng, float $radiusM, int $segments = 72): array
    {
        if ($radiusM <= 0) {
            return [];
        }

        $earth = 6378137;
        $latRad = deg2rad($lat);
        $lngRad = deg2rad($lng);
        $points = [];
        for ($i = 0; $i <= $segments; $i++) {
            $bearing = 2 * pi() * $i / $segments;
            $pointLat = asin(sin($latRad) * cos($radiusM / $earth) + cos($latRad) * sin($radiusM / $earth) * cos($bearing));
            $pointLng = $lngRad + atan2(sin($bearing) * sin($radiusM / $earth) * cos($latRad), cos($radiusM / $earth) - sin($latRad) * sin($pointLat));
            $points[] = ['lat' => rad2deg($pointLat), 'lng' => rad2deg($pointLng)];
        }

        return $points;
    }

    private function shpZip($talhao, string $baseName): BinaryFileResponse
    {
        $points = $this->pontosTalhao($talhao);
        $type = $this->tipoGeo($talhao, $points);
        abort_if(! $type || ! $points, 422, 'Este talhao nao possui geometria para exportar.');

        if ($type === 'polygon') {
            $shapeType = 5;
            $rings = [];
            $geometrias = $this->geometriasTalhao($talhao);
            if (count($geometrias) > 1) {
                foreach ($geometrias as $geometria) {
                    $outer = $geometria['points'];
                    if ($outer[0] !== end($outer)) {
                        $outer[] = $outer[0];
                    }
                    $rings[] = $outer;
                    foreach (($geometria['exclusions'] ?? []) as $ring) {
                        if ($ring[0] !== end($ring)) {
                            $ring[] = $ring[0];
                        }
                        $rings[] = $ring;
                    }
                }
            } else {
                if ($points[0] !== end($points)) {
                    $points[] = $points[0];
                }
                $rings[] = $points;
                foreach ($this->exclusoesTalhao($talhao) as $ring) {
                    if ($ring[0] !== end($ring)) {
                        $ring[] = $ring[0];
                    }
                    $rings[] = $ring;
                }
            }
            $shapePoints = array_merge(...$rings);
        } elseif ($type === 'line') {
            $shapeType = 3;
            $rings = [$points];
            $shapePoints = $points;
        } else {
            $shapeType = 1;
            $rings = [$points];
            $shapePoints = $points;
        }

        $bbox = $this->bbox($shapePoints);
        if ($shapeType === 1) {
            $content = pack('V', $shapeType).pack('e', $points[0]['lng']).pack('e', $points[0]['lat']);
        } else {
            $partOffsets = [];
            $offset = 0;
            foreach ($rings as $ring) {
                $partOffsets[] = $offset;
                $offset += count($ring);
            }
            $content = pack('V', $shapeType)
                .pack('e', $bbox['xmin']).pack('e', $bbox['ymin']).pack('e', $bbox['xmax']).pack('e', $bbox['ymax'])
                .pack('V', count($rings))
                .pack('V', count($shapePoints));
            foreach ($partOffsets as $partOffset) {
                $content .= pack('V', $partOffset);
            }
            foreach ($shapePoints as $point) {
                $content .= pack('e', $point['lng']).pack('e', $point['lat']);
            }
        }

        $contentWords = (int) (strlen($content) / 2);
        $record = pack('N', 1).pack('N', $contentWords).$content;
        $shp = $this->shpHeader($shapeType, (int) ((100 + strlen($record)) / 2), $bbox).$record;
        $shx = $this->shpHeader($shapeType, 54, $bbox).pack('N', 50).pack('N', $contentWords);
        $dbf = $this->dbfTalhao($talhao, $type);
        $prj = 'GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["Degree",0.017453292519943295]]';

        return $this->zipResponse($baseName.'_shp.zip', [
            $baseName.'.shp' => $shp,
            $baseName.'.shx' => $shx,
            $baseName.'.dbf' => $dbf,
            $baseName.'.prj' => $prj,
        ], 'application/zip');
    }

    private function zipResponse(string $fileName, array $files, string $contentType): BinaryFileResponse
    {
        abort_unless(class_exists(ZipArchive::class), 500, 'Extensao ZIP nao disponivel.');

        $tmp = tempnam(sys_get_temp_dir(), 'farmfort_zip_');
        $zipPath = $tmp.'.zip';
        @rename($tmp, $zipPath);

        $zip = new ZipArchive;
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Nao foi possivel gerar o arquivo.');
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return response()->download($zipPath, $fileName, ['Content-Type' => $contentType])->deleteFileAfterSend(true);
    }

    private function bbox(array $points): array
    {
        return [
            'xmin' => min(array_column($points, 'lng')),
            'ymin' => min(array_column($points, 'lat')),
            'xmax' => max(array_column($points, 'lng')),
            'ymax' => max(array_column($points, 'lat')),
        ];
    }

    private function shpHeader(int $shapeType, int $fileLengthWords, array $bbox): string
    {
        return pack('N', 9994)
            .str_repeat(pack('N', 0), 5)
            .pack('N', $fileLengthWords)
            .pack('V', 1000)
            .pack('V', $shapeType)
            .pack('e', $bbox['xmin']).pack('e', $bbox['ymin']).pack('e', $bbox['xmax']).pack('e', $bbox['ymax'])
            .pack('e', 0).pack('e', 0).pack('e', 0).pack('e', 0);
    }

    private function dbfTalhao($talhao, string $type): string
    {
        $fields = [
            ['NAME', 'C', 80, 0],
            ['AREA_HA', 'N', 14, 2],
            ['TIPO', 'C', 12, 0],
        ];
        $recordLength = 1 + array_sum(array_column($fields, 2));
        $headerLength = 32 + (32 * count($fields)) + 1;
        $header = chr(3).chr((int) date('Y') - 1900).chr((int) date('n')).chr((int) date('j'))
            .pack('V', 1).pack('v', $headerLength).pack('v', $recordLength)
            .str_repeat("\0", 20);
        foreach ($fields as $field) {
            $header .= $this->dbfField($field[0], $field[1], $field[2], $field[3]);
        }
        $header .= "\r";

        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $talhao->nome) ?: (string) $talhao->nome;
        $record = ' '
            .str_pad(substr($name, 0, 80), 80)
            .str_pad(number_format((float) ($talhao->area ?? 0), 2, '.', ''), 14, ' ', STR_PAD_LEFT)
            .str_pad($type, 12);

        return $header.$record.chr(0x1A);
    }

    private function dbfField(string $name, string $type, int $length, int $decimals = 0): string
    {
        return str_pad(substr($name, 0, 10), 11, "\0")
            .$type
            .str_repeat("\0", 4)
            .chr($length)
            .chr($decimals)
            .str_repeat("\0", 14);
    }

    private function slug(string $value): string
    {
        $safeName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $safeName);

        return strtolower(trim((string) $safeName, '_')) ?: 'talhao';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function centroide(array $points): array
    {
        return [
            'lat' => array_sum(array_column($points, 'lat')) / count($points),
            'lng' => array_sum(array_column($points, 'lng')) / count($points),
        ];
    }

    private function areaHa(array $points): float
    {
        return $this->geometry->areaHectares($points);
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

    private function payload(array $dados, float $area): array
    {
        return [
            'nome' => trim($dados['nome']),
            'area' => $area,
            'area_bruta' => $this->decimal($dados['area_bruta'] ?? $area),
            'area_excluida_ha' => $this->decimal($dados['area_excluida_ha'] ?? 0),
            'descricao' => trim($dados['descricao'] ?? '') ?: null,
            'latitude' => $this->nullableDecimal($dados['latitude'] ?? null),
            'longitude' => $this->nullableDecimal($dados['longitude'] ?? null),
            'geometria_tipo' => ($dados['geometria_tipo'] ?? null) ?: null,
            'pivo_ativo' => (bool) ($dados['pivo_ativo'] ?? false),
            'pivo_lat' => $this->nullableDecimal($dados['pivo_lat'] ?? null),
            'pivo_lng' => $this->nullableDecimal($dados['pivo_lng'] ?? null),
            'pivo_raio_m' => $this->nullableDecimal($dados['pivo_raio_m'] ?? null),
            'pivo_area_ha' => $this->nullableDecimal($dados['pivo_area_ha'] ?? null),
        ];
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

    private function auditarObrigatorio(
        ?int $usuarioId,
        string $acao,
        string $tabela,
        int $registroId,
        int $propriedadeId,
        string $detalhes,
    ): void {
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
    }
}

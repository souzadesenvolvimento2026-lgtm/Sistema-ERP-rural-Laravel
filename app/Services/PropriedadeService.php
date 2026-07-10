<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class PropriedadeService
{
    public function pagina(Request $request): array
    {
        $filtros = $this->filtros($request);
        $rows = $this->rows($filtros);

        return [
            'activeModule' => 'propriedades',
            'title' => 'Propriedades',
            'subtitle' => 'Consulta das fazendas, planos, usuarios, cotacao e georreferencia.',
            'filtros' => $filtros,
            'rows' => $rows,
            'statusOptions' => [
                'ativas' => 'Ativas',
                'inativas' => 'Inativas',
                'todas' => 'Todas',
            ],
            'cards' => [
                ['label' => 'Propriedades', 'value' => (string)$rows->count(), 'tone' => 'success'],
                ['label' => 'Ativas', 'value' => (string)$rows->where('ativo', true)->count(), 'tone' => 'success'],
                ['label' => 'Area total', 'value' => FarmFormat::decimal($rows->sum('area_total_raw'), 2).' ha', 'tone' => 'success'],
                ['label' => 'Usuarios vinculados', 'value' => (string)$rows->sum('usuarios_total'), 'tone' => 'warning'],
            ],
        ];
    }

    public function criar(array $dados, ?UploadedFile $arquivoGeo = null, ?int $usuarioId = null): int
    {
        $aprovadorId = $this->aprovadorValido($dados['aprovador_usuario_id'] ?? null);

        DB::table('propriedades')->insert($this->payload($dados) + [
            'ativo' => 1,
            'aprovador_usuario_id' => $aprovadorId,
        ]);

        $propriedadeId = (int)DB::getPdo()->lastInsertId();
        $this->vincularAprovador($propriedadeId, $aprovadorId);
        $this->importarGeo($propriedadeId, $arquivoGeo, $usuarioId, $dados['area_total'] ?? null);

        return $propriedadeId;
    }

    public function buscar(int $propriedadeId): object
    {
        $propriedade = DB::table('propriedades')->where('id', $propriedadeId)->first();
        abort_if(!$propriedade, 404);

        return $propriedade;
    }

    public function atualizar(int $propriedadeId, array $dados, ?UploadedFile $arquivoGeo = null, ?int $usuarioId = null): void
    {
        $this->buscar($propriedadeId);
        $novoPlano = $dados['plano'] ?: 'basico';
        $aprovadorId = $this->aprovadorValido($dados['aprovador_usuario_id'] ?? null);
        $usuariosTotal = DB::table('usuario_propriedades')
            ->where('propriedade_id', $propriedadeId)
            ->distinct('usuario_id')
            ->count('usuario_id');
        $limitePlano = $this->limiteUsuarios($novoPlano);

        if ($usuariosTotal > $limitePlano) {
            throw new RuntimeException('Esta propriedade tem '.$usuariosTotal.' usuarios e o plano '.$this->planoLabel($novoPlano).' permite '.$limitePlano.'. Remova usuarios antes de reduzir o plano.');
        }

        if ($aprovadorId && !$this->usuarioVinculado($propriedadeId, $aprovadorId) && $usuariosTotal >= $limitePlano) {
            throw new RuntimeException('Limite de usuarios do plano '.$this->planoLabel($novoPlano).' atingido para esta propriedade.');
        }

        DB::table('propriedades')
            ->where('id', $propriedadeId)
            ->update($this->payload($dados) + ['aprovador_usuario_id' => $aprovadorId]);

        $this->vincularAprovador($propriedadeId, $aprovadorId);
        $this->importarGeo($propriedadeId, $arquivoGeo, $usuarioId, $dados['area_total'] ?? null);
    }

    public static function aprovadores(): Collection
    {
        return DB::table('usuarios')
            ->where('ativo', 1)
            ->whereIn('perfil', ['administrador', 'gestor_financeiro', 'gestor_propriedade', 'gestao', 'financeiro'])
            ->orderBy('nome')
            ->get(['id', 'nome', 'email', 'perfil']);
    }

    public function alternarStatus(int $propriedadeId): bool
    {
        $propriedade = $this->buscar($propriedadeId);
        $ativo = (int)$propriedade->ativo === 1 ? 0 : 1;

        DB::table('propriedades')
            ->where('id', $propriedadeId)
            ->update(['ativo' => $ativo]);

        return $ativo === 1;
    }

    private function filtros(Request $request): array
    {
        $status = (string)$request->query('status', 'ativas');
        if (!in_array($status, ['ativas', 'inativas', 'todas'], true)) {
            $status = 'ativas';
        }

        return [
            'status' => $status,
            'search' => trim((string)$request->query('search', '')),
        ];
    }

    private function rows(array $filtros): Collection
    {
        $query = DB::table('propriedades as p')
            ->leftJoin('usuarios as aprovador', 'aprovador.id', '=', 'p.aprovador_usuario_id')
            ->leftJoin(DB::raw("(
                SELECT propriedade_id, COUNT(DISTINCT usuario_id) AS usuarios_total
                FROM usuario_propriedades
                GROUP BY propriedade_id
            ) as up"), 'up.propriedade_id', '=', 'p.id')
            ->leftJoin(DB::raw("(
                SELECT gfp.propriedade_id, GROUP_CONCAT(gf.nome ORDER BY gf.nome SEPARATOR ', ') AS grupos_nomes
                FROM grupo_fazenda_propriedades gfp
                JOIN grupos_fazendas gf ON gf.id = gfp.grupo_id
                GROUP BY gfp.propriedade_id
            ) as grupos"), 'grupos.propriedade_id', '=', 'p.id');

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
                'p.cnpj_cpf',
                'p.plano',
                'p.pecuaria_ativa',
                'p.ativo',
                'p.latitude',
                'p.longitude',
                'p.regiao_cotacao',
                'p.cotacao_soja',
                'p.cotacao_soja_atualizada_em',
                'p.cotacao_soja_fonte',
                'aprovador.nome as aprovador_nome',
                DB::raw('COALESCE(up.usuarios_total, 0) as usuarios_total'),
                DB::raw('COALESCE(grupos.grupos_nomes, "") as grupos_nomes'),
            ])
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function normalizar($row): object
    {
        $plano = (string)($row->plano ?: 'basico');
        $latitude = $row->latitude !== null ? (string)$row->latitude : '';
        $longitude = $row->longitude !== null ? (string)$row->longitude : '';

        return (object)[
            'id' => (int)$row->id,
            'nome' => FarmFormat::value($row->nome),
            'municipio_uf' => trim(FarmFormat::value($row->municipio).' / '.FarmFormat::value($row->estado), ' /'),
            'area_total_raw' => (float)$row->area_total,
            'area_total' => (float)$row->area_total > 0 ? FarmFormat::decimal($row->area_total, 2).' ha' : '-',
            'responsavel' => FarmFormat::value($row->responsavel),
            'cnpj_cpf' => FarmFormat::value($row->cnpj_cpf),
            'plano' => $this->planoLabel($plano),
            'limite_usuarios' => $this->limiteUsuarios($plano),
            'usuarios_total' => (int)$row->usuarios_total,
            'pecuaria' => (int)$row->pecuaria_ativa === 1 ? 'Ativa' : 'Desativada',
            'pecuaria_ativa' => (int)$row->pecuaria_ativa === 1,
            'ativo' => (int)$row->ativo === 1,
            'status' => (int)$row->ativo === 1 ? 'Ativa' : 'Inativa',
            'aprovador' => FarmFormat::value($row->aprovador_nome),
            'grupos' => FarmFormat::value($row->grupos_nomes),
            'cotacao_soja' => FarmFormat::money($row->cotacao_soja),
            'cotacao_data' => FarmFormat::date($row->cotacao_soja_atualizada_em),
            'cotacao_fonte' => FarmFormat::value($row->cotacao_soja_fonte),
            'regiao_cotacao' => FarmFormat::value($row->regiao_cotacao),
            'geo' => $latitude !== '' && $longitude !== '' ? $latitude.', '.$longitude : 'Sem georreferencia',
        ];
    }

    private function limiteUsuarios(string $plano): int
    {
        return ['basico' => 3, 'avancado' => 5, 'premium' => 10][$plano] ?? 3;
    }

    private function planoLabel(string $plano): string
    {
        return ['basico' => 'Basico', 'avancado' => 'Avancado', 'premium' => 'Premium'][$plano] ?? 'Basico';
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
            'cnpj_cpf' => preg_replace('/\D+/', '', (string)($dados['cnpj_cpf'] ?? '')) ?: null,
            'plano' => $dados['plano'] ?: 'basico',
            'pecuaria_ativa' => (bool)($dados['pecuaria_ativa'] ?? false),
            'latitude' => $this->nullableDecimal($dados['latitude'] ?? null),
            'longitude' => $this->nullableDecimal($dados['longitude'] ?? null),
            'regiao_cotacao' => trim($dados['regiao_cotacao'] ?? '') ?: null,
        ];
    }

    private function aprovadorValido($aprovadorId): ?int
    {
        if (!$aprovadorId) {
            return null;
        }

        $id = (int)$aprovadorId;
        $exists = DB::table('usuarios')
            ->where('id', $id)
            ->where('ativo', 1)
            ->whereIn('perfil', ['administrador', 'gestor_financeiro', 'gestor_propriedade', 'gestao', 'financeiro'])
            ->exists();

        return $exists ? $id : null;
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
        if (!$aprovadorId || $this->usuarioVinculado($propriedadeId, $aprovadorId)) {
            return;
        }

        DB::table('usuario_propriedades')->insert([
            'usuario_id' => $aprovadorId,
            'propriedade_id' => $propriedadeId,
        ]);
    }

    private function importarGeo(int $propriedadeId, ?UploadedFile $arquivoGeo, ?int $usuarioId, mixed $areaInformada): void
    {
        if (!$arquivoGeo || !$arquivoGeo->isValid()) {
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

        $latitude = (float)$talhoesImportados->avg('latitude');
        $longitude = (float)$talhoesImportados->avg('longitude');
        $areaTotal = (float)$talhoesImportados->sum('area');
        $areaFallback = $areaTotal > 0 ? $areaTotal : $this->decimal($areaInformada ?? 0);
        $regiao = number_format($latitude, 6, '.', '').', '.number_format($longitude, 6, '.', '');

        DB::table('propriedades')
            ->where('id', $propriedadeId)
            ->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'kml_arquivo' => $kmlArquivo,
                'area_total' => DB::raw('IF(COALESCE(area_total,0)>0, area_total, '.($areaFallback > 0 ? (string)$areaFallback : 'NULL').')'),
                'regiao_cotacao' => DB::raw("COALESCE(NULLIF(regiao_cotacao,''), '".$regiao."')"),
                'cotacao_soja_fonte' => DB::raw("COALESCE(NULLIF(cotacao_soja_fonte,''), 'Cadastro manual / referencia regional')"),
            ]);
    }

    private function decimal($value): float
    {
        $value = str_replace(',', '.', trim((string)$value));
        return max(0.0, (float)$value);
    }

    private function nullableDecimal($value): ?float
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        return (float)str_replace(',', '.', trim((string)$value));
    }
}

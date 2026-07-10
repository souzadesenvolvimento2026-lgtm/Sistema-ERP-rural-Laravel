<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ColheitaService
{
    public function pagina(int $propriedadeId, Request $request): array
    {
        $safras = DB::table('safras as s')
            ->leftJoin('culturas as c', 'c.id', '=', 's.cultura_id')
            ->where('s.propriedade_id', $propriedadeId)
            ->orderByDesc('s.data_inicio')
            ->orderByDesc('s.id')
            ->get(['s.id', 's.descricao', 's.status', 'c.nome as cultura_nome']);

        $talhoes = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome', 'area']);

        $filtros = $this->filtros($request, $safras, $talhoes);
        $rows = $this->rows($propriedadeId, $filtros);
        $totais = $this->totais($rows);
        $talhoesResumo = $this->talhoesResumo($propriedadeId, $filtros['safra_id']);

        return [
            'activeModule' => 'colheita',
            'title' => 'Entrada de Colheita',
            'subtitle' => 'Resumo das cargas colhidas por safra, talhão, romaneio e destino.',
            'filtros' => $filtros,
            'safras' => $safras,
            'talhoes' => $talhoes,
            'talhoesResumo' => $talhoesResumo,
            'rows' => $rows,
            'cards' => [
                ['label' => 'Produção total', 'value' => FarmFormat::decimal($totais['kg'], 2).' kg', 'tone' => 'success'],
                ['label' => 'Total em sacas', 'value' => FarmFormat::decimal($totais['sacas'], 2).' sc', 'tone' => 'success'],
                ['label' => 'Produtividade', 'value' => FarmFormat::decimal($totais['produtividade'], 2).' sc/ha', 'tone' => 'warning'],
                ['label' => 'Destinos', 'value' => (string)$totais['destinos'], 'tone' => 'success'],
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        $safraId = $this->safraIdValida($dados['safra_id'] ?? null, $propriedadeId);
        if (!$safraId) {
            throw new \RuntimeException('Informe uma safra valida para esta propriedade.');
        }

        $talhaoId = $this->talhaoIdValido($dados['talhao_id'] ?? null, $propriedadeId);
        if (!$talhaoId) {
            throw new \RuntimeException('Informe um talhão válido para esta propriedade.');
        }

        $pesoBruto = $this->decimal($dados['peso_bruto_kg'] ?? 0);
        $tara = $this->decimal($dados['tara_kg'] ?? 0);
        $desconto = $this->decimal($dados['desconto_kg'] ?? 0);
        $pesoLiquido = max(0, $pesoBruto - $tara);
        $pesoFinal = $this->decimal($dados['peso_final_kg'] ?? 0) ?: max(0, $pesoLiquido - $desconto);
        $sacas = $pesoFinal > 0 ? round($pesoFinal / 60, 2) : 0;
        $areaColhida = $this->decimal($dados['area_colhida'] ?? 0);
        $produtividade = $areaColhida > 0 ? round($sacas / $areaColhida, 2) : 0;

        DB::table('colheita_talhoes')->insert([
            'propriedade_id' => $propriedadeId,
            'safra_id' => $safraId,
            'talhao_id' => $talhaoId,
            'ticket_numero' => trim($dados['ticket_numero'] ?? '') ?: null,
            'motorista' => trim($dados['motorista'] ?? '') ?: null,
            'veiculo_placa' => trim($dados['veiculo_placa'] ?? '') ?: null,
            'destino_producao' => trim($dados['destino_producao'] ?? '') ?: null,
            'local_destino' => trim($dados['local_destino'] ?? '') ?: null,
            'data_colheita' => $dados['data_colheita'],
            'peso_bruto_kg' => $pesoBruto,
            'tara_kg' => $tara,
            'peso_liquido_kg' => $pesoLiquido,
            'desconto_kg' => $desconto,
            'peso_final_kg' => $pesoFinal,
            'sacas' => $sacas,
            'area_colhida' => $areaColhida ?: null,
            'produtividade_sc_ha' => $produtividade ?: null,
            'umidade' => $this->nullableDecimal($dados['umidade'] ?? null),
            'impureza_pct' => $this->nullableDecimal($dados['impureza_pct'] ?? null),
            'origem' => 'manual',
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            'usuario_id' => $usuarioId,
        ]);

        $colheitaId = (int)DB::getPdo()->lastInsertId();
        $ticket = trim($dados['ticket_numero'] ?? '') ?: '-';
        $this->auditar($usuarioId, 'salvar_colheita', 'colheita_talhoes', $colheitaId, $propriedadeId, 'Romaneio: '.$ticket);

        return $colheitaId;
    }

    public function buscar(int $id, int $propriedadeId): object
    {
        $carga = DB::table('colheita_talhoes')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->first();

        abort_unless($carga, 404);

        return $carga;
    }

    public function atualizar(int $id, array $dados, int $propriedadeId, ?int $usuarioId): void
    {
        $this->buscar($id, $propriedadeId);

        $safraId = $this->safraIdValida($dados['safra_id'] ?? null, $propriedadeId);
        if (!$safraId) {
            throw new \RuntimeException('Informe uma safra valida para esta propriedade.');
        }

        $talhaoId = $this->talhaoIdValido($dados['talhao_id'] ?? null, $propriedadeId);
        if (!$talhaoId) {
            throw new \RuntimeException('Informe um talhao valido para esta propriedade.');
        }

        $pesoBruto = $this->decimal($dados['peso_bruto_kg'] ?? 0);
        $tara = $this->decimal($dados['tara_kg'] ?? 0);
        $desconto = $this->decimal($dados['desconto_kg'] ?? 0);
        $pesoLiquido = max(0, $pesoBruto - $tara);
        $pesoFinal = $this->decimal($dados['peso_final_kg'] ?? 0) ?: max(0, $pesoLiquido - $desconto);
        $sacas = $pesoFinal > 0 ? round($pesoFinal / 60, 2) : 0;
        $areaColhida = $this->decimal($dados['area_colhida'] ?? 0);
        $produtividade = $areaColhida > 0 ? round($sacas / $areaColhida, 2) : 0;

        DB::table('colheita_talhoes')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->update([
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'ticket_numero' => trim($dados['ticket_numero'] ?? '') ?: null,
                'motorista' => trim($dados['motorista'] ?? '') ?: null,
                'veiculo_placa' => trim($dados['veiculo_placa'] ?? '') ?: null,
                'destino_producao' => trim($dados['destino_producao'] ?? '') ?: null,
                'local_destino' => trim($dados['local_destino'] ?? '') ?: null,
                'data_colheita' => $dados['data_colheita'],
                'peso_bruto_kg' => $pesoBruto,
                'tara_kg' => $tara,
                'peso_liquido_kg' => $pesoLiquido,
                'desconto_kg' => $desconto,
                'peso_final_kg' => $pesoFinal,
                'sacas' => $sacas,
                'area_colhida' => $areaColhida ?: null,
                'produtividade_sc_ha' => $produtividade ?: null,
                'umidade' => $this->nullableDecimal($dados['umidade'] ?? null),
                'impureza_pct' => $this->nullableDecimal($dados['impureza_pct'] ?? null),
                'origem' => 'manual',
                'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
                'usuario_id' => $usuarioId,
            ]);

        $this->auditar($usuarioId, 'editar_colheita', 'colheita_talhoes', $id, $propriedadeId, 'Carga de colheita editada');
    }

    public function excluir(int $id, int $propriedadeId, ?int $usuarioId): void
    {
        $alteradas = DB::table('colheita_talhoes')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->delete();

        if ($alteradas > 0) {
            $this->auditar($usuarioId, 'excluir_colheita', 'colheita_talhoes', $id, $propriedadeId, 'Carga de colheita excluida');
        }
    }

    private function filtros(Request $request, Collection $safras, Collection $talhoes): array
    {
        $safraId = $request->integer('safra_id') ?: (int)($safras->first()->id ?? 0);
        if ($safraId && !$safras->contains('id', $safraId)) {
            $safraId = (int)($safras->first()->id ?? 0);
        }

        $talhaoId = $request->integer('talhao_id') ?: null;
        if ($talhaoId && !$talhoes->contains('id', $talhaoId)) {
            $talhaoId = null;
        }

        return [
            'safra_id' => $safraId ?: null,
            'talhao_id' => $talhaoId,
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('date_from')) ? (string)$request->query('date_from') : '',
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('date_to')) ? (string)$request->query('date_to') : '',
            'destino' => trim((string)$request->query('destino', '')),
            'search' => trim((string)$request->query('search', '')),
        ];
    }

    private function rows(int $propriedadeId, array $filtros): Collection
    {
        $query = DB::table('colheita_talhoes as ct')
            ->join('talhoes as t', 't.id', '=', 'ct.talhao_id')
            ->leftJoin('safras as s', 's.id', '=', 'ct.safra_id')
            ->leftJoin('culturas as c', 'c.id', '=', 's.cultura_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'ct.usuario_id')
            ->where('ct.propriedade_id', $propriedadeId);

        if ($filtros['safra_id']) {
            $query->where('ct.safra_id', $filtros['safra_id']);
        }

        if ($filtros['talhao_id']) {
            $query->where('ct.talhao_id', $filtros['talhao_id']);
        }

        if ($filtros['date_from'] !== '') {
            $query->whereDate('ct.data_colheita', '>=', $filtros['date_from']);
        }

        if ($filtros['date_to'] !== '') {
            $query->whereDate('ct.data_colheita', '<=', $filtros['date_to']);
        }

        if ($filtros['destino'] !== '') {
            $query->where('ct.destino_producao', $filtros['destino']);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('ct.ticket_numero', 'like', $term)
                    ->orWhere('ct.motorista', 'like', $term)
                    ->orWhere('ct.veiculo_placa', 'like', $term)
                    ->orWhere('ct.local_destino', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('ct.data_colheita')
            ->orderByDesc('ct.id')
            ->limit(240)
            ->get([
                'ct.id',
                'ct.ticket_numero',
                'ct.motorista',
                'ct.veiculo_placa',
                'ct.destino_producao',
                'ct.local_destino',
                'ct.data_colheita',
                'ct.peso_bruto_kg',
                'ct.tara_kg',
                'ct.peso_liquido_kg',
                'ct.desconto_kg',
                'ct.peso_final_kg',
                'ct.sacas',
                'ct.area_colhida',
                'ct.produtividade_sc_ha',
                'ct.umidade',
                'ct.impureza_pct',
                'ct.observacoes',
                't.nome as talhao_nome',
                't.area as talhao_area',
                's.descricao as safra_nome',
                'c.nome as cultura_nome',
                'u.nome as usuario_nome',
            ])
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function normalizar($row): object
    {
        $pesoFinal = (float)($row->peso_final_kg ?: 0);
        if ($pesoFinal <= 0 && (float)$row->peso_liquido_kg > 0) {
            $pesoFinal = max(0, (float)$row->peso_liquido_kg - (float)$row->desconto_kg);
        }
        if ($pesoFinal <= 0) {
            $pesoFinal = (float)$row->sacas * 60;
        }

        $sacas = $pesoFinal > 0 ? $pesoFinal / 60 : (float)$row->sacas;
        $area = (float)($row->area_colhida ?: $row->talhao_area);
        $produtividade = (float)($row->produtividade_sc_ha ?: ($area > 0 ? $sacas / $area : 0));

        return (object)[
            'id' => (int)$row->id,
            'data' => FarmFormat::date($row->data_colheita),
            'ticket' => FarmFormat::value($row->ticket_numero),
            'safra' => FarmFormat::value($row->safra_nome),
            'cultura' => FarmFormat::value($row->cultura_nome),
            'talhao' => FarmFormat::value($row->talhao_nome),
            'motorista' => FarmFormat::value($row->motorista),
            'veiculo' => FarmFormat::value($row->veiculo_placa),
            'destino_key' => (string)($row->destino_producao ?: 'sem_destino'),
            'destino' => $this->destinoLabel((string)($row->destino_producao ?: 'sem_destino')),
            'local_destino' => FarmFormat::value($row->local_destino),
            'peso_final_raw' => $pesoFinal,
            'peso_final' => FarmFormat::decimal($pesoFinal, 2).' kg',
            'sacas_raw' => $sacas,
            'sacas' => FarmFormat::decimal($sacas, 2).' sc',
            'area_raw' => $area,
            'area' => FarmFormat::decimal($area, 2).' ha',
            'produtividade' => FarmFormat::decimal($produtividade, 2).' sc/ha',
            'umidade' => $row->umidade !== null ? FarmFormat::decimal($row->umidade, 2).'%' : '-',
            'impureza' => $row->impureza_pct !== null ? FarmFormat::decimal($row->impureza_pct, 2).'%' : '-',
            'usuario' => FarmFormat::value($row->usuario_nome),
            'observacoes' => FarmFormat::value($row->observacoes),
        ];
    }

    private function totais(Collection $rows): array
    {
        $areaPorTalhao = [];
        foreach ($rows as $row) {
            $areaPorTalhao[$row->talhao] = $row->area_raw;
        }

        $kg = (float)$rows->sum('peso_final_raw');
        $sacas = (float)$rows->sum('sacas_raw');
        $area = array_sum($areaPorTalhao);

        return [
            'kg' => $kg,
            'sacas' => $sacas,
            'produtividade' => $area > 0 ? $sacas / $area : 0,
            'destinos' => $rows->pluck('destino_key')->filter()->unique()->count(),
        ];
    }

    public function destinos(): array
    {
        return [
            'silo_proprio' => 'Silo proprio',
            'cooperativa' => 'Cooperativa',
            'armazem' => 'Armazem',
            'venda_direta' => 'Venda direta',
            'sem_destino' => 'Sem destino',
        ];
    }

    private function destinoLabel(string $destino): string
    {
        return $this->destinos()[$destino] ?? FarmFormat::statusLabel($destino);
    }

    public function finalizarTalhao(int $propriedadeId, int $safraId, int $talhaoId, ?int $usuarioId): void
    {
        $this->validarSafraTalhao($propriedadeId, $safraId, $talhaoId);

        $temCarga = DB::table('colheita_talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('safra_id', $safraId)
            ->where('talhao_id', $talhaoId)
            ->exists();

        if (!$temCarga) {
            throw new \RuntimeException('Lance pelo menos uma carga antes de finalizar o talhao.');
        }

        DB::table('safra_talhoes')->upsert([
            [
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'propriedade_id' => $propriedadeId,
                'colheita_finalizada_em' => now(),
                'colheita_finalizada_por' => $usuarioId,
            ],
        ], ['safra_id', 'talhao_id'], ['propriedade_id', 'colheita_finalizada_em', 'colheita_finalizada_por']);

        $this->auditar($usuarioId, 'finalizar_colheita_talhao', 'safra_talhoes', $talhaoId, $propriedadeId, 'Talhao finalizado na safra');
        $this->atualizarStatusSafra($safraId);
    }

    public function reabrirTalhao(int $propriedadeId, int $safraId, int $talhaoId, ?int $usuarioId): void
    {
        $this->validarSafraTalhao($propriedadeId, $safraId, $talhaoId);

        DB::table('safra_talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('safra_id', $safraId)
            ->where('talhao_id', $talhaoId)
            ->update([
                'colheita_finalizada_em' => null,
                'colheita_finalizada_por' => null,
            ]);

        $this->auditar($usuarioId, 'reabrir_colheita_talhao', 'safra_talhoes', $talhaoId, $propriedadeId, 'Talhao reaberto na safra');
        $this->atualizarStatusSafra($safraId);
    }

    private function talhoesResumo(int $propriedadeId, ?int $safraId): Collection
    {
        if (!$safraId) {
            return collect();
        }

        $rows = DB::table('talhoes as t')
            ->leftJoin('safra_talhoes as st', function ($join) use ($safraId, $propriedadeId) {
                $join->on('st.talhao_id', '=', 't.id')
                    ->where('st.safra_id', $safraId)
                    ->where('st.propriedade_id', $propriedadeId);
            })
            ->where('t.propriedade_id', $propriedadeId)
            ->where('t.ativo', 1)
            ->orderBy('t.nome')
            ->get([
                't.id',
                't.nome',
                't.area',
                'st.colheita_finalizada_em',
                DB::raw('(SELECT COUNT(*) FROM colheita_talhoes ct WHERE ct.propriedade_id = t.propriedade_id AND ct.safra_id = '.$safraId.' AND ct.talhao_id = t.id) as cargas'),
                DB::raw('(SELECT COALESCE(SUM(ct.peso_final_kg), 0) FROM colheita_talhoes ct WHERE ct.propriedade_id = t.propriedade_id AND ct.safra_id = '.$safraId.' AND ct.talhao_id = t.id) as peso_final'),
            ]);

        return $rows->map(function ($row) {
            $pesoFinal = (float)$row->peso_final;
            $sacas = $pesoFinal / 60;
            $area = (float)$row->area;

            return (object)[
                'id' => (int)$row->id,
                'nome' => FarmFormat::value($row->nome),
                'area' => FarmFormat::decimal($area, 2).' ha',
                'cargas' => (int)$row->cargas,
                'peso' => FarmFormat::decimal($pesoFinal, 2).' kg',
                'sacas' => FarmFormat::decimal($sacas, 2).' sc',
                'produtividade' => $area > 0 ? FarmFormat::decimal($sacas / $area, 2).' sc/ha' : '-',
                'finalizado' => !empty($row->colheita_finalizada_em),
                'finalizado_em' => FarmFormat::date($row->colheita_finalizada_em),
            ];
        });
    }

    private function validarSafraTalhao(int $propriedadeId, int $safraId, int $talhaoId): void
    {
        $safraOk = DB::table('safras')->where('id', $safraId)->where('propriedade_id', $propriedadeId)->exists();
        $talhaoOk = DB::table('talhoes')->where('id', $talhaoId)->where('propriedade_id', $propriedadeId)->where('ativo', 1)->exists();

        if (!$safraOk || !$talhaoOk) {
            throw new \RuntimeException('Informe safra e talhao validos para esta propriedade.');
        }
    }

    private function atualizarStatusSafra(int $safraId): void
    {
        $status = DB::table('safras as s')
            ->leftJoin('safra_talhoes as st', 'st.safra_id', '=', 's.id')
            ->where('s.id', $safraId)
            ->groupBy('s.id', 's.status')
            ->first([
                's.status',
                DB::raw('COUNT(st.talhao_id) as total_talhoes'),
                DB::raw('SUM(st.colheita_finalizada_em IS NULL) as talhoes_pendentes'),
            ]);

        if (!$status || (int)$status->total_talhoes <= 0) {
            return;
        }

        if ($status->status === 'em_andamento' && (int)$status->talhoes_pendentes === 0) {
            DB::table('safras')->where('id', $safraId)->where('status', 'em_andamento')->update(['status' => 'colhida']);
        } elseif ($status->status === 'colhida' && (int)$status->talhoes_pendentes > 0) {
            DB::table('safras')->where('id', $safraId)->where('status', 'colhida')->update(['status' => 'em_andamento']);
        }
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

        return $this->decimal($value);
    }

    private function safraIdValida($safraId, int $propriedadeId): ?int
    {
        $safraId = (int)($safraId ?: 0);
        if ($safraId <= 0) {
            return null;
        }

        return DB::table('safras')
            ->where('id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->exists()
            ? $safraId
            : null;
    }

    private function talhaoIdValido($talhaoId, int $propriedadeId): ?int
    {
        $talhaoId = (int)($talhaoId ?: 0);
        if ($talhaoId <= 0) {
            return null;
        }

        return DB::table('talhoes')
            ->where('id', $talhaoId)
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->exists()
            ? $talhaoId
            : null;
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
        } catch (\Throwable) {
            // Auditoria nao deve impedir a operacao de colheita.
        }
    }
}

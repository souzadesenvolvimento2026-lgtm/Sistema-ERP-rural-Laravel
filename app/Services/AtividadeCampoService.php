<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AtividadeCampoService
{
    public function validSafraId(mixed $safraId, int $propertyId): ?int
    {
        return $this->idValido('safras', $safraId, $propertyId);
    }

    public function pagina(int $propriedadeId, ?int $safraId = null): array
    {
        $query = DB::table('atividades_campo as a')
            ->leftJoin('safras as s', 's.id', '=', 'a.safra_id')
            ->leftJoin('talhoes as t', 't.id', '=', 'a.talhao_id')
            ->where('a.propriedade_id', $propriedadeId);

        if ($safraId) {
            $query->where('a.safra_id', $safraId);
        }

        $atividades = $query
            ->orderByDesc('a.data_inicio')
            ->orderByDesc('a.id')
            ->get([
                'a.id',
                'a.safra_id',
                'a.talhao_id',
                'a.area_executada',
                'a.tipo',
                'a.data_inicio',
                'a.data_fim',
                'a.status',
                'a.descricao',
                'a.responsavel',
                'a.servico',
                'a.produto',
                'a.dose',
                'a.custo_estimado',
                'a.observacoes',
                's.descricao as safra_nome',
                't.nome as talhao_nome',
            ])
            ->each(function ($atividade): void {
                $atividade->can_complete = $atividade->status !== 'concluida';
            });

        $concluidas = $atividades->where('status', 'concluida')->count();

        return [
            'activeModule' => 'talhoes',
            'atividades' => $atividades,
            'safras' => DB::table('safras')
                ->where('propriedade_id', $propriedadeId)
                ->orderByDesc('data_inicio')
                ->get(['id', 'descricao']),
            'talhoes' => DB::table('talhoes')
                ->where('propriedade_id', $propriedadeId)
                ->where('ativo', 1)
                ->orderBy('nome')
                ->get(['id', 'nome', 'area']),
            'tipos' => $this->tipos(),
            'statusOptions' => $this->statusOptions(),
            'filtroSafraId' => $safraId,
            'porTipo' => $atividades->groupBy('tipo')->map->count(),
            'totais' => [
                'atividades' => $atividades->count(),
                'concluidas' => $concluidas,
                'pendentes' => $atividades->count() - $concluidas,
                'custo' => (float) $atividades->sum('custo_estimado'),
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        DB::table('atividades_campo')->insert([
            'propriedade_id' => $propriedadeId,
            'safra_id' => $this->idValido('safras', $dados['safra_id'] ?? null, $propriedadeId),
            'talhao_id' => $this->idValido('talhoes', $dados['talhao_id'] ?? null, $propriedadeId, true),
            'area_executada' => $this->decimalOuNulo($dados['area_executada'] ?? null),
            'tipo' => $dados['tipo'] ?? 'manejo',
            'data_inicio' => $dados['data_inicio'],
            'data_fim' => ($dados['data_fim'] ?? null) ?: null,
            'status' => $dados['status'] ?? 'planejada',
            'descricao' => trim($dados['descricao']),
            'responsavel' => trim($dados['responsavel'] ?? '') ?: null,
            'servico' => trim($dados['servico'] ?? '') ?: null,
            'produto' => trim($dados['produto'] ?? '') ?: null,
            'dose' => trim($dados['dose'] ?? '') ?: null,
            'custo_estimado' => $this->decimal($dados['custo_estimado'] ?? 0),
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            'usuario_id' => $usuarioId,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    public function atualizarStatus(int $id, int $propriedadeId, string $status): void
    {
        DB::table('atividades_campo')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->update(['status' => $status]);
    }

    public function tipos(): array
    {
        return [
            'preparo_solo' => 'Preparo do solo',
            'plantio' => 'Plantio',
            'manejo' => 'Manejo',
            'colheita' => 'Colheita',
            'monitoramento' => 'Monitoramento',
            'recomendacao' => 'Recomendação',
            'outro' => 'Outro',
        ];
    }

    public function statusOptions(): array
    {
        return [
            'planejada' => 'Planejada',
            'em_execucao' => 'Em execução',
            'concluida' => 'Concluída',
            'cancelada' => 'Cancelada',
        ];
    }

    private function decimal($value): float
    {
        $value = trim((string) $value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return max(0.0, (float) $value);
    }

    private function decimalOuNulo($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->decimal($value);
    }

    private function idValido(string $tabela, $id, int $propriedadeId, bool $somenteAtivo = false): ?int
    {
        $id = (int) ($id ?: 0);
        if ($id <= 0) {
            return null;
        }

        $query = DB::table($tabela)
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId);

        if ($somenteAtivo) {
            $query->where('ativo', 1);
        }

        return $query->exists() ? $id : null;
    }
}

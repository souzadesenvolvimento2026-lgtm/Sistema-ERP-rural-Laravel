<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ChuvaService
{
    public function pagina(int $propriedadeId, ?int $ano = null): array
    {
        $ano = $ano ?: (int)date('Y');
        $registros = DB::table('chuvas as c')
            ->leftJoin('talhoes as t', 't.id', '=', 'c.talhao_id')
            ->where('c.propriedade_id', $propriedadeId)
            ->whereYear('c.data_chuva', $ano)
            ->orderByDesc('c.data_chuva')
            ->orderByDesc('c.id')
            ->get([
                'c.id',
                'c.data_chuva',
                'c.volume_mm',
                'c.fonte',
                'c.observacoes',
                't.nome as talhao_nome',
            ]);

        $dias = $registros->pluck('data_chuva')->unique()->count();
        $total = (float)$registros->sum('volume_mm');
        $maior = (float)($registros->max('volume_mm') ?? 0);

        return [
            'activeModule' => 'talhoes',
            'ano' => $ano,
            'registros' => $registros,
            'talhoes' => DB::table('talhoes')
                ->where('propriedade_id', $propriedadeId)
                ->where('ativo', 1)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'mensal' => $this->mensal($propriedadeId, $ano),
            'totais' => [
                'total' => $total,
                'dias' => $dias,
                'media' => $dias > 0 ? $total / $dias : 0,
                'maior' => $maior,
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        DB::table('chuvas')->insert([
            'propriedade_id' => $propriedadeId,
            'talhao_id' => $this->talhaoIdValido($dados['talhao_id'] ?? null, $propriedadeId),
            'data_chuva' => $dados['data_chuva'],
            'volume_mm' => $this->decimal($dados['volume_mm'] ?? 0),
            'fonte' => $dados['fonte'] ?? 'manual',
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            'usuario_id' => $usuarioId,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    private function mensal(int $propriedadeId, int $ano): array
    {
        $rows = DB::table('chuvas')
            ->where('propriedade_id', $propriedadeId)
            ->whereYear('data_chuva', $ano)
            ->selectRaw('MONTH(data_chuva) as mes, SUM(volume_mm) as total')
            ->groupByRaw('MONTH(data_chuva)')
            ->pluck('total', 'mes');

        $mensal = [];
        foreach (['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'] as $index => $label) {
            $mensal[] = ['mes' => $label, 'total' => (float)($rows[$index + 1] ?? 0)];
        }

        return $mensal;
    }

    private function decimal($value): float
    {
        $value = trim((string)$value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return max(0.0, (float)$value);
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
}

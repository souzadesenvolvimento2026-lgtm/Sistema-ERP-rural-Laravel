<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnaliseDespesasService
{
    public function dados(int $propertyId, Request $request): array
    {
        $safras = DB::table('safras')
            ->where('propriedade_id', $propertyId)
            ->orderByDesc('data_inicio')
            ->get(['id', 'descricao', 'area_plantada']);

        $talhoes = DB::table('talhoes')
            ->where('propriedade_id', $propertyId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome', 'area']);

        $categorias = DB::table('categorias')
            ->where('ativo', 1)
            ->whereNull('categoria_pai_id')
            ->orderBy('nome')
            ->get(['id', 'nome', 'tipo', 'cor']);

        $filtros = $this->filtros($request, $safras, $talhoes, $categorias);
        $area = $this->areaConsiderada($propertyId, $filtros, $safras, $talhoes);
        $precoMedio = $this->precoMedioSaca($propertyId, $filtros);
        $categoriasResumo = $this->categoriasResumo($propertyId, $filtros);
        $total = $categoriasResumo->sum('value');
        $totalLancamentos = $categoriasResumo->sum('count');
        $valorHa = $area > 0 ? $total / $area : 0.0;
        $sacasHa = ($valorHa > 0 && $precoMedio > 0) ? $valorHa / $precoMedio : 0.0;

        return [
            'activeModule' => 'financeiro',
            'title' => 'Categorias e subcategorias',
            'topbarLabel' => 'Análise de Categorias',
            'eyebrow' => 'Análise financeira',
            'subtitle' => 'Distribuição gerencial por receita, custo e despesa com detalhamento interativo.',
            'filtros' => $filtros,
            'safras' => $safras,
            'talhoes' => $talhoes,
            'categoriasFiltro' => $categorias,
            'total' => $total,
            'area' => $area,
            'valorHa' => $valorHa,
            'precoMedio' => $precoMedio,
            'sacasHa' => $sacasHa,
            'totalLancamentos' => $totalLancamentos,
            'categoriasResumo' => $categoriasResumo,
            'chartData' => [
                'total' => $total,
                'area' => $area,
                'valuePerHa' => $valorHa,
                'avgPrice' => $precoMedio,
                'sacksPerHa' => $sacasHa,
                'categories' => $categoriasResumo->values()->all(),
            ],
        ];
    }

    private function filtros(Request $request, Collection $safras, Collection $talhoes, Collection $categorias): array
    {
        $safraId = $request->integer('safra_id') ?: $request->integer('fd_safra') ?: null;
        if ($safraId && !$safras->contains('id', $safraId)) {
            $safraId = null;
        }

        $categoriaId = $request->integer('categoria_id') ?: $request->integer('fd_categoria') ?: null;
        if ($categoriaId && !$categorias->contains('id', $categoriaId)) {
            $categoriaId = null;
        }

        $talhaoId = $request->integer('talhao_id') ?: null;
        if ($talhaoId && !$talhoes->contains('id', $talhaoId)) {
            $talhaoId = null;
        }

        $tipo = (string)$request->query('tipo', 'custos_despesas');
        if (!in_array($tipo, ['custos_despesas', 'despesas', 'receitas'], true)) {
            $tipo = 'custos_despesas';
        }

        return [
            'safra_id' => $safraId,
            'categoria_id' => $categoriaId,
            'talhao_id' => $talhaoId,
            'tipo' => $tipo,
        ];
    }

    private function categoriasResumo(int $propertyId, array $filtros): Collection
    {
        $rows = collect();

        if (in_array($filtros['tipo'], ['custos_despesas', 'despesas'], true)) {
            $rows = $rows->merge($this->despesasPorCategoria($propertyId, $filtros));
        }

        if (in_array($filtros['tipo'], ['custos_despesas', 'receitas'], true) && !$filtros['talhao_id']) {
            $rows = $rows->merge($this->receitasPorCategoria($propertyId, $filtros));
        }

        $cores = ['#009966', '#2563eb', '#f59e0b', '#dc3545', '#7c3aed', '#14b8a6', '#f97316', '#64748b', '#84cc16', '#0ea5e9'];

        return $rows
            ->groupBy('group_key')
            ->map(function (Collection $groupRows, string $key) {
                $first = $groupRows->first();
                $subcategories = $groupRows
                    ->groupBy('subcategory')
                    ->map(fn (Collection $items, string $label) => [
                        'label' => $label,
                        'value' => (float)$items->sum('value'),
                        'count' => (int)$items->sum('count'),
                    ])
                    ->sortByDesc('value')
                    ->values()
                    ->all();

                return [
                    'key' => $key,
                    'label' => $first['group_label'],
                    'value' => (float)$groupRows->sum('value'),
                    'count' => (int)$groupRows->sum('count'),
                    'subcategories' => $subcategories,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->map(function (array $row, int $idx) use ($cores) {
                $row['color'] = $cores[$idx % count($cores)];
                return $row;
            });
    }

    private function despesasPorCategoria(int $propertyId, array $filtros): Collection
    {
        return DB::table('despesas as d')
            ->leftJoin('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->leftJoin('categorias as sc', 'sc.id', '=', 'd.subcategoria_id')
            ->where('d.propriedade_id', $propertyId)
            ->where('d.status_pagamento', '!=', 'cancelado')
            ->where('d.status_aprovacao', '!=', 'reprovada')
            ->when($filtros['safra_id'], fn ($query, $id) => $query->where('d.safra_id', $id))
            ->when($filtros['categoria_id'], fn ($query, $id) => $query->where('d.categoria_id', $id))
            ->when($filtros['talhao_id'], fn ($query, $id) => $query->where('d.talhao_id', $id))
            ->groupBy('c.tipo', 'c.nome', 'sc.nome')
            ->get([
                DB::raw("COALESCE(c.tipo, 'outros') as group_key"),
                DB::raw("COALESCE(c.tipo, 'outros') as group_type"),
                DB::raw("COALESCE(c.nome, 'Sem categoria') as category"),
                DB::raw("COALESCE(sc.nome, c.nome, 'Sem categoria') as subcategory"),
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(d.valor_total), 0) as value'),
            ])
            ->map(fn ($row) => [
                'group_key' => 'despesa_'.$row->group_key,
                'group_label' => $this->tipoLabel((string)$row->group_type),
                'subcategory' => (string)$row->subcategory,
                'count' => (int)$row->count,
                'value' => (float)$row->value,
            ]);
    }

    private function receitasPorCategoria(int $propertyId, array $filtros): Collection
    {
        if (!Schema::hasTable('receitas')) {
            return collect();
        }

        return DB::table('receitas as r')
            ->leftJoin('categorias as c', 'c.id', '=', 'r.categoria_id')
            ->leftJoin('categorias as sc', 'sc.id', '=', 'r.subcategoria_id')
            ->where('r.propriedade_id', $propertyId)
            ->where('r.status', '!=', 'cancelado')
            ->where('r.status_aprovacao', '!=', 'reprovada')
            ->when($filtros['safra_id'], fn ($query, $id) => $query->where('r.safra_id', $id))
            ->when($filtros['categoria_id'], fn ($query, $id) => $query->where('r.categoria_id', $id))
            ->groupBy('c.nome', 'sc.nome')
            ->get([
                DB::raw("'receitas' as group_key"),
                DB::raw("COALESCE(sc.nome, c.nome, 'Receitas') as subcategory"),
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(r.valor_total), 0) as value'),
            ])
            ->map(fn ($row) => [
                'group_key' => 'receitas',
                'group_label' => 'Receitas',
                'subcategory' => (string)$row->subcategory,
                'count' => (int)$row->count,
                'value' => (float)$row->value,
            ]);
    }

    private function areaConsiderada(int $propertyId, array $filtros, Collection $safras, Collection $talhoes): float
    {
        if ($filtros['talhao_id']) {
            return (float)($talhoes->firstWhere('id', $filtros['talhao_id'])->area ?? 0);
        }

        if ($filtros['safra_id']) {
            $areaSafra = (float)($safras->firstWhere('id', $filtros['safra_id'])->area_plantada ?? 0);
            if ($areaSafra > 0) {
                return $areaSafra;
            }
        }

        return (float)DB::table('talhoes')
            ->where('propriedade_id', $propertyId)
            ->where('ativo', 1)
            ->sum('area');
    }

    private function precoMedioSaca(int $propertyId, array $filtros): float
    {
        if (!Schema::hasTable('receitas')) {
            return 0.0;
        }

        $row = DB::table('receitas as r')
            ->where('r.propriedade_id', $propertyId)
            ->where('r.status', '!=', 'cancelado')
            ->where('r.status_aprovacao', '!=', 'reprovada')
            ->when($filtros['safra_id'], fn ($query, $id) => $query->where('r.safra_id', $id))
            ->selectRaw('COALESCE(SUM(r.valor_total), 0) as total_valor, COALESCE(SUM(r.quantidade), 0) as total_quantidade')
            ->first();

        $quantidade = (float)($row->total_quantidade ?? 0);
        return $quantidade > 0 ? (float)$row->total_valor / $quantidade : 0.0;
    }

    private function tipoLabel(string $tipo): string
    {
        return [
            'insumo' => 'Insumos',
            'manutencao' => 'Manutenção',
            'folha' => 'Mão de obra',
            'servico' => 'Serviços',
            'combustivel' => 'Combustível',
            'administrativo' => 'Administrativo',
            'bancario' => 'Financeiro',
            'outros' => 'Operacionais',
        ][$tipo] ?? FarmFormat::statusLabel($tipo);
    }
}

<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnaliseDespesasService
{
    public function dados(int $propertyId, Request $request): array
    {
        $safras = DB::table('safras')
            ->where('propriedade_id', $propertyId)
            ->orderByDesc('data_inicio')
            ->get(['id', 'descricao', 'data_inicio']);

        $categorias = DB::table('categorias')
            ->where('ativo', 1)
            ->orderBy('tipo')
            ->orderBy('nome')
            ->get(['id', 'nome', 'tipo', 'cor']);

        $filtros = $this->filtros($request, $safras, $categorias);
        [$totalOrcado, $totalRealizado] = $this->totais($propertyId, $filtros);
        $categoriasResumo = $this->categoriasResumo($propertyId, $filtros);
        $mensal = $this->mensal($propertyId, $filtros);
        $anual = $this->anual($propertyId, $filtros);

        $pctAtingido = $totalOrcado > 0 ? ($totalRealizado / $totalOrcado) * 100 : 0;

        return [
            'activeModule' => 'financeiro',
            'title' => 'Analise de Despesas',
            'subtitle' => 'Resultado realizado contra o projetado por ano, mes, safra e categoria.',
            'filtros' => $filtros,
            'anos' => range((int)date('Y') + 1, (int)date('Y') - 4),
            'meses' => $this->meses(),
            'safras' => $safras,
            'categorias' => $categorias,
            'tipos' => $this->tipos($categorias),
            'contexto' => $this->contexto($filtros, $safras, $categorias),
            'cards' => [
                ['label' => 'Realizado', 'value' => FarmFormat::money($totalRealizado), 'tone' => 'danger'],
                ['label' => '% Atingido', 'value' => number_format($pctAtingido, 2, ',', '.') . '%', 'tone' => $pctAtingido <= 100 ? 'success' : 'warning'],
                ['label' => 'Projetado', 'value' => FarmFormat::money($totalOrcado), 'tone' => 'success'],
                ['label' => 'Desvio', 'value' => FarmFormat::money($totalRealizado - $totalOrcado), 'tone' => $totalRealizado <= $totalOrcado ? 'success' : 'danger'],
            ],
            'categoriasResumo' => $categoriasResumo,
            'mensal' => $mensal,
            'anual' => $anual,
        ];
    }

    private function filtros(Request $request, Collection $safras, Collection $categorias): array
    {
        $anoAtual = (int)date('Y');
        $ano = $request->integer('fd_ano') ?: $anoAtual;
        if ($ano < 2000 || $ano > 2100) {
            $ano = $anoAtual;
        }

        $mes = $request->integer('fd_mes') ?: null;
        if ($mes && ($mes < 1 || $mes > 12)) {
            $mes = null;
        }

        $safraId = $request->integer('fd_safra') ?: null;
        if ($safraId && !$safras->contains('id', $safraId)) {
            $safraId = null;
        }

        $tipo = (string)$request->query('fd_tipo', 'todos');
        if ($tipo !== 'todos' && !$categorias->contains('tipo', $tipo)) {
            $tipo = 'todos';
        }

        $categoriaId = $request->integer('fd_categoria') ?: null;
        if ($categoriaId && !$categorias->contains('id', $categoriaId)) {
            $categoriaId = null;
        }

        return [
            'ano' => $ano,
            'mes' => $mes,
            'safra_id' => $safraId,
            'tipo' => $tipo,
            'categoria_id' => $categoriaId,
        ];
    }

    private function totais(int $propertyId, array $filtros): array
    {
        return [
            (float)$this->projecoesBase($propertyId, $filtros)->sum('fp.valor_projetado'),
            (float)$this->despesasBase($propertyId, $filtros)->sum('d.valor_total'),
        ];
    }

    private function categoriasResumo(int $propertyId, array $filtros): Collection
    {
        $orcado = $this->projecoesBase($propertyId, $filtros)
            ->groupBy('c.id', 'c.nome', 'c.tipo', 'c.cor')
            ->get(['c.id', 'c.nome', 'c.tipo', 'c.cor', DB::raw('COALESCE(SUM(fp.valor_projetado), 0) as total')]);

        $realizado = $this->despesasBase($propertyId, $filtros)
            ->groupBy('c.id', 'c.nome', 'c.tipo', 'c.cor')
            ->get(['c.id', 'c.nome', 'c.tipo', 'c.cor', DB::raw('COALESCE(SUM(d.valor_total), 0) as total')]);

        $rows = collect();
        foreach ($orcado as $row) {
            $rows[(int)$row->id] = [
                'id' => (int)$row->id,
                'nome' => $row->nome,
                'tipo' => $this->tipoLabel((string)$row->tipo),
                'cor' => $row->cor,
                'orcado_raw' => (float)$row->total,
                'realizado_raw' => 0.0,
            ];
        }

        foreach ($realizado as $row) {
            $item = $rows[(int)$row->id] ?? [
                'id' => (int)$row->id,
                'nome' => $row->nome,
                'tipo' => $this->tipoLabel((string)$row->tipo),
                'cor' => $row->cor,
                'orcado_raw' => 0.0,
                'realizado_raw' => 0.0,
            ];
            $item['realizado_raw'] = (float)$row->total;
            $rows[(int)$row->id] = $item;
        }

        return $rows
            ->map(function (array $row) {
                $pct = $row['orcado_raw'] > 0 ? ($row['realizado_raw'] / $row['orcado_raw']) * 100 : 0;
                return (object)[
                    'nome' => $row['nome'],
                    'tipo' => $row['tipo'],
                    'realizado' => FarmFormat::money($row['realizado_raw']),
                    'atingido' => number_format($pct, 2, ',', '.') . '%',
                    'orcado' => FarmFormat::money($row['orcado_raw']),
                    'peso' => $row['realizado_raw'] + $row['orcado_raw'],
                ];
            })
            ->sortByDesc('peso')
            ->values()
            ->map(function ($row) {
                unset($row->peso);
                return $row;
            });
    }

    private function mensal(int $propertyId, array $filtros): Collection
    {
        $meses = $filtros['mes'] ? [$filtros['mes']] : range(1, 12);
        $orcado = $this->projecoesBase($propertyId, $filtros)
            ->selectRaw('MONTH(fp.mes_referencia) as mes, COALESCE(SUM(fp.valor_projetado), 0) as total')
            ->groupByRaw('MONTH(fp.mes_referencia)')
            ->pluck('total', 'mes');

        $realizado = $this->despesasBase($propertyId, $filtros)
            ->selectRaw('MONTH(d.data_lancamento) as mes, COALESCE(SUM(d.valor_total), 0) as total')
            ->groupByRaw('MONTH(d.data_lancamento)')
            ->pluck('total', 'mes');

        return collect($meses)->map(function (int $mes) use ($orcado, $realizado) {
            $orcadoValor = (float)($orcado[$mes] ?? 0);
            $realizadoValor = (float)($realizado[$mes] ?? 0);

            return (object)[
                'mes' => $this->meses()[$mes],
                'realizado' => FarmFormat::money($realizadoValor),
                'orcado' => FarmFormat::money($orcadoValor),
                'desvio' => FarmFormat::money($realizadoValor - $orcadoValor),
            ];
        });
    }

    private function anual(int $propertyId, array $filtros): Collection
    {
        $orcado = $this->projecoesBase($propertyId, $filtros, false)
            ->selectRaw('YEAR(fp.mes_referencia) as ano, COALESCE(SUM(fp.valor_projetado), 0) as total')
            ->groupByRaw('YEAR(fp.mes_referencia)')
            ->pluck('total', 'ano');

        $realizado = $this->despesasBase($propertyId, $filtros, false)
            ->selectRaw('YEAR(d.data_lancamento) as ano, COALESCE(SUM(d.valor_total), 0) as total')
            ->groupByRaw('YEAR(d.data_lancamento)')
            ->pluck('total', 'ano');

        return collect([$filtros['ano']])
            ->merge($orcado->keys())
            ->merge($realizado->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function ($ano) use ($orcado, $realizado) {
                $orcadoValor = (float)($orcado[$ano] ?? 0);
                $realizadoValor = (float)($realizado[$ano] ?? 0);

                return (object)[
                    'ano' => (string)$ano,
                    'realizado' => FarmFormat::money($realizadoValor),
                    'orcado' => FarmFormat::money($orcadoValor),
                    'desvio' => FarmFormat::money($realizadoValor - $orcadoValor),
                ];
            });
    }

    private function projecoesBase(int $propertyId, array $filtros, bool $aplicarAno = true)
    {
        return DB::table('financeiro_projecoes as fp')
            ->join('categorias as c', 'c.id', '=', 'fp.categoria_id')
            ->where('fp.propriedade_id', $propertyId)
            ->when($aplicarAno, fn ($query) => $query->whereYear('fp.mes_referencia', $filtros['ano']))
            ->when($filtros['mes'], fn ($query) => $query->whereMonth('fp.mes_referencia', $filtros['mes']))
            ->when($filtros['safra_id'], fn ($query) => $query->where('fp.safra_id', $filtros['safra_id']))
            ->when($filtros['tipo'] !== 'todos', fn ($query) => $query->where('c.tipo', $filtros['tipo']))
            ->when($filtros['categoria_id'], fn ($query) => $query->where('c.id', $filtros['categoria_id']));
    }

    private function despesasBase(int $propertyId, array $filtros, bool $aplicarAno = true)
    {
        return DB::table('despesas as d')
            ->join('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->where('d.propriedade_id', $propertyId)
            ->where('d.status_pagamento', '!=', 'cancelado')
            ->where('d.status_aprovacao', '!=', 'reprovada')
            ->when($aplicarAno, fn ($query) => $query->whereYear('d.data_lancamento', $filtros['ano']))
            ->when($filtros['mes'], fn ($query) => $query->whereMonth('d.data_lancamento', $filtros['mes']))
            ->when($filtros['safra_id'], fn ($query) => $query->where('d.safra_id', $filtros['safra_id']))
            ->when($filtros['tipo'] !== 'todos', fn ($query) => $query->where('c.tipo', $filtros['tipo']))
            ->when($filtros['categoria_id'], fn ($query) => $query->where('c.id', $filtros['categoria_id']));
    }

    private function contexto(array $filtros, Collection $safras, Collection $categorias): array
    {
        return [
            'Ano: '.$filtros['ano'],
            'Mes: '.($filtros['mes'] ? $this->meses()[$filtros['mes']] : 'Todos'),
            'Safra: '.($safras->firstWhere('id', $filtros['safra_id'])->descricao ?? 'Todas'),
            'Categoria: '.($filtros['tipo'] === 'todos' ? 'Todas' : $this->tipoLabel($filtros['tipo'])),
            'Subcategoria: '.($categorias->firstWhere('id', $filtros['categoria_id'])->nome ?? 'Todas'),
        ];
    }

    private function tipos(Collection $categorias): array
    {
        return $categorias
            ->pluck('tipo')
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($tipo) => [$tipo => $this->tipoLabel((string)$tipo)])
            ->sort()
            ->all();
    }

    private function tipoLabel(string $tipo): string
    {
        return [
            'insumo' => 'Insumos',
            'manutencao' => 'Manutencao',
            'folha' => 'Folha',
            'servico' => 'Servicos',
            'combustivel' => 'Combustivel',
            'administrativo' => 'Administrativo',
            'bancario' => 'Bancario',
            'outros' => 'Outros',
        ][$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
    }

    private function meses(): array
    {
        return [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    }
}

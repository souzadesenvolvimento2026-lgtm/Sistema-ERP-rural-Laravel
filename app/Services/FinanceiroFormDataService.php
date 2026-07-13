<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FinanceiroFormDataService
{
    public function options(int $propertyId, ?Collection $buyers = null): array
    {
        $patrimonioColumns = Schema::hasTable('maquinas')
            ? ['id', 'nome', Schema::hasColumn('maquinas', 'marca_modelo') ? 'marca_modelo' : DB::raw("'' as marca_modelo")]
            : [];

        return [
            'categorias' => DB::table('categorias')->where('ativo', 1)->whereNull('categoria_pai_id')->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'subcategorias' => DB::table('categorias')->where('ativo', 1)->whereNotNull('categoria_pai_id')->orderBy('nome')->get(['id', 'categoria_pai_id', 'nome', 'tipo']),
            'contas' => DB::table('contas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'banco']),
            'compradores' => $buyers ?? collect(),
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'talhoes' => DB::table('talhoes')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'produtores' => DB::table('produtores')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'patrimonios' => Schema::hasTable('maquinas')
                ? DB::table('maquinas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get($patrimonioColumns)
                : collect(),
        ];
    }
}

<?php

namespace App\Services;

use App\Support\FarmFormat;
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
            'contas' => $this->contas($propertyId),
            'compradores' => $buyers ?? collect(),
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'talhoes' => DB::table('talhoes')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'produtores' => DB::table('produtores')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'patrimonios' => Schema::hasTable('maquinas')
                ? DB::table('maquinas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get($patrimonioColumns)
                : collect(),
        ];
    }

    private function contas(int $propertyId): Collection
    {
        return DB::table('contas')
            ->where('propriedade_id', $propertyId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->select(['contas.id', 'contas.nome', 'contas.banco', DB::raw($this->saldoSql().' AS saldo_atual')])
            ->get()
            ->map(function (object $conta): object {
                $saldo = (float) ($conta->saldo_atual ?? 0);
                $conta->saldo_numero = $saldo;
                $conta->saldo = FarmFormat::money($saldo);

                return $conta;
            });
    }

    private function saldoSql(): string
    {
        $receitasSql = Schema::hasTable('receitas')
            ? "(SELECT SUM(r.valor_total) FROM receitas r WHERE r.conta_id = contas.id AND r.propriedade_id = contas.propriedade_id AND r.status = 'recebido')"
            : '0';

        $despesasSql = Schema::hasTable('despesas')
            ? "(SELECT SUM(d.valor_total) FROM despesas d WHERE d.conta_id = contas.id AND d.propriedade_id = contas.propriedade_id AND d.status_pagamento = 'pago' AND d.status_aprovacao = 'aprovada')"
            : '0';

        $transferenciasOrigemSql = Schema::hasTable('transferencias')
            ? "(SELECT SUM(tf.valor) FROM transferencias tf WHERE tf.conta_origem_id = contas.id AND tf.propriedade_id = contas.propriedade_id)"
            : '0';

        $transferenciasDestinoSql = Schema::hasTable('transferencias')
            ? "(SELECT SUM(tf.valor) FROM transferencias tf WHERE tf.conta_destino_id = contas.id AND tf.propriedade_id = contas.propriedade_id)"
            : '0';

        return "COALESCE(contas.saldo_inicial, 0)
            + COALESCE({$receitasSql}, 0)
            - COALESCE({$despesasSql}, 0)
            - COALESCE({$transferenciasOrigemSql}, 0)
            + COALESCE({$transferenciasDestinoSql}, 0)";
    }
}

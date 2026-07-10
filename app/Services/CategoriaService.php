<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoriaService
{
    public function pagina(): array
    {
        $categorias = DB::table('categorias as c')
            ->leftJoin('categorias as p', 'p.id', '=', 'c.categoria_pai_id')
            ->orderByDesc('c.ativo')
            ->orderByRaw('COALESCE(p.nome, c.nome)')
            ->orderByRaw('c.categoria_pai_id IS NOT NULL')
            ->orderBy('c.nome')
            ->get([
                'c.id',
                'c.categoria_pai_id',
                'c.nome',
                'c.tipo',
                'c.cor',
                'c.icone',
                'c.ativo',
                'p.nome as categoria_pai_nome',
            ]);

        return [
            'activeModule' => 'financeiro',
            'categorias' => $categorias,
            'principais' => $categorias->whereNull('categoria_pai_id')->where('ativo', 1)->values(),
            'totais' => [
                'categorias' => $categorias->count(),
                'ativas' => $categorias->where('ativo', 1)->count(),
                'principais' => $categorias->whereNull('categoria_pai_id')->count(),
                'subcategorias' => $categorias->whereNotNull('categoria_pai_id')->count(),
            ],
            'tipos' => $this->tipos(),
        ];
    }

    public function criar(array $dados): int
    {
        DB::table('categorias')->insert($this->payload($dados));

        return (int)DB::getPdo()->lastInsertId();
    }

    public function atualizar(int $id, array $dados): void
    {
        $payload = $this->payload($dados);
        if (($payload['categoria_pai_id'] ?? null) === $id) {
            $payload['categoria_pai_id'] = null;
        }

        DB::table('categorias')->where('id', $id)->update($payload);
    }

    public function excluirOuDesativar(int $id): array
    {
        if ($this->possuiFilhas($id)) {
            return [
                'type' => 'error',
                'message' => 'Exclua ou mova as subcategorias antes de excluir esta categoria principal.',
            ];
        }

        if ($this->possuiUsoNaSafraAtual($id)) {
            return [
                'type' => 'error',
                'message' => 'Esta categoria/subcategoria tem lancamentos ou planejamento na safra atual e nao pode ser excluida.',
            ];
        }

        if ($this->possuiUso($id)) {
            DB::table('categorias')->where('id', $id)->update(['ativo' => 0]);

            return [
                'type' => 'success',
                'message' => 'Categoria desativada para preservar o histórico.',
            ];
        }

        DB::table('categorias')->where('id', $id)->delete();

        return [
            'type' => 'success',
            'message' => 'Categoria excluída.',
        ];
    }

    public function tipos(): array
    {
        return ['insumo', 'manutencao', 'folha', 'servico', 'combustivel', 'administrativo', 'bancario', 'outros'];
    }

    private function payload(array $dados): array
    {
        return [
            'categoria_pai_id' => ($dados['categoria_pai_id'] ?? null) ?: null,
            'nome' => trim($dados['nome']),
            'tipo' => $dados['tipo'] ?? 'outros',
            'cor' => $dados['cor'] ?? '#6c757d',
            'icone' => trim($dados['icone'] ?? '') ?: 'bi-tag',
            'ativo' => (bool)($dados['ativo'] ?? true),
        ];
    }

    private function possuiFilhas(int $id): bool
    {
        return DB::table('categorias')->where('categoria_pai_id', $id)->exists();
    }

    private function possuiUso(int $id): bool
    {
        $checks = [
            DB::table('despesas')->where(fn ($query) => $query->where('categoria_id', $id)->orWhere('subcategoria_id', $id))->exists(),
            DB::table('receitas')->where(fn ($query) => $query->where('categoria_id', $id)->orWhere('subcategoria_id', $id))->exists(),
            DB::table('produtos')->where('categoria_id', $id)->exists(),
            DB::table('financeiro_projecoes')->where('categoria_id', $id)->exists(),
        ];

        return in_array(true, $checks, true);
    }

    private function possuiUsoNaSafraAtual(int $id): bool
    {
        $safraId = (int)session('safra_id', 0);
        if ($safraId <= 0) {
            return false;
        }

        $checks = [
            DB::table('despesas')
                ->where('safra_id', $safraId)
                ->where('status_pagamento', '<>', 'cancelado')
                ->where(fn ($query) => $query->where('categoria_id', $id)->orWhere('subcategoria_id', $id))
                ->exists(),
            DB::table('financeiro_projecoes')
                ->where('safra_id', $safraId)
                ->where('categoria_id', $id)
                ->exists(),
        ];

        if (Schema::hasTable('orcamentos')) {
            $checks[] = DB::table('orcamentos')
                ->where('safra_id', $safraId)
                ->where('categoria_id', $id)
                ->exists();
        }

        return in_array(true, $checks, true);
    }
}

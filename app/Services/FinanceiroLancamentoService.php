<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceiroLancamentoService
{
    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        return $dados['tipo'] === 'receita'
            ? $this->criarReceita($dados, $propriedadeId, $usuarioId)
            : $this->criarDespesa($dados, $propriedadeId, $usuarioId);
    }

    private function criarDespesa(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        $numeroParcelas = max(1, min(36, (int)($dados['numero_parcelas'] ?? 1)));
        $quantidade = $this->decimal($dados['quantidade'] ?? 0);
        $valorUnitario = $this->decimal($dados['preco_unitario'] ?? 0);
        $valorTotal = $this->money($dados['valor_total'] ?? 0);
        if ($valorTotal <= 0 && $quantidade > 0 && $valorUnitario > 0) {
            $valorTotal = $quantidade * $valorUnitario;
        }

        $valorParcela = $valorTotal / $numeroParcelas;
        $dataLancamento = Carbon::parse($dados['data_lancamento']);
        $dataVencimento = !empty($dados['data_vencimento']) ? Carbon::parse($dados['data_vencimento']) : null;

        for ($parcela = 1; $parcela <= $numeroParcelas; $parcela++) {
            $meses = $parcela - 1;

            DB::table('despesas')->insert([
                'propriedade_id' => $propriedadeId,
                'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propriedadeId),
                'talhao_id' => $this->idDaPropriedade('talhoes', $dados['talhao_id'] ?? null, $propriedadeId),
                'categoria_id' => (int)$dados['categoria_id'],
                'subcategoria_id' => $this->subcategoriaId($dados['subcategoria_id'] ?? null, $dados['categoria_id'] ?? null),
                'conta_id' => $this->idDaPropriedade('contas', $dados['conta_id'] ?? null, $propriedadeId),
                'produtor_id' => $this->idDaPropriedade('produtores', $dados['produtor_id'] ?? null, $propriedadeId),
                'descricao' => trim($dados['descricao']),
                'fornecedor' => trim($dados['pessoa'] ?? '') ?: null,
                'quantidade' => $quantidade > 0 ? $quantidade : null,
                'unidade' => $quantidade > 0 ? (trim($dados['unidade'] ?? '') ?: 'un') : null,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $valorParcela,
                'data_lancamento' => $dataLancamento->copy()->addMonthsNoOverflow($meses)->toDateString(),
                'data_vencimento' => ($dataVencimento ?: $dataLancamento)->copy()->addMonthsNoOverflow($meses)->toDateString(),
                'status_pagamento' => $dados['baixado'] ? 'pago' : 'pendente',
                'data_pagamento' => $dados['baixado'] ? now()->toDateString() : null,
                'status_aprovacao' => 'aprovada',
                'aprovado_por' => $usuarioId,
                'aprovado_em' => now(),
                'forma_pagamento' => ($dados['forma_pagamento'] ?? null) ?: 'pix',
                'numero_parcelas' => $numeroParcelas,
                'parcela_atual' => $parcela,
                'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
                'usuario_id' => $usuarioId,
            ]);
        }

        return (int)DB::getPdo()->lastInsertId();
    }

    private function criarReceita(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        $quantidade = $this->decimal($dados['quantidade'] ?? 0);
        $precoUnitario = $this->decimal($dados['preco_unitario'] ?? 0);
        $valorTotal = $this->money($dados['valor_total'] ?? 0);
        if ($valorTotal <= 0 && $quantidade > 0 && $precoUnitario > 0) {
            $valorTotal = $quantidade * $precoUnitario;
        }

        DB::table('receitas')->insert([
            'propriedade_id' => $propriedadeId,
            'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propriedadeId),
            'categoria_id' => ($dados['categoria_id'] ?? null) ?: null,
            'subcategoria_id' => $this->subcategoriaId($dados['subcategoria_id'] ?? null, $dados['categoria_id'] ?? null),
            'conta_id' => $this->idDaPropriedade('contas', $dados['conta_id'] ?? null, $propriedadeId),
            'produtor_id' => $this->idDaPropriedade('produtores', $dados['produtor_id'] ?? null, $propriedadeId),
            'descricao' => trim($dados['descricao']),
            'comprador' => $this->compradorNome($dados, $propriedadeId),
            'quantidade' => $quantidade > 0 ? $quantidade : null,
            'unidade' => $quantidade > 0 ? (trim($dados['unidade'] ?? '') ?: 'sc') : '',
            'preco_unitario' => $precoUnitario,
            'valor_total' => $valorTotal,
            'data_venda' => $dados['data_lancamento'],
            'data_recebimento' => $dados['baixado'] ? $dados['data_lancamento'] : null,
            'status' => $dados['baixado'] ? 'recebido' : 'pendente',
            'status_aprovacao' => 'aprovada',
            'aprovado_por' => $usuarioId,
            'aprovado_em' => now(),
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            'usuario_id' => $usuarioId,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    private function money($value): float
    {
        $value = trim((string)$value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return max(0.0, (float)$value);
    }

    private function decimal($value): float
    {
        return $this->money($value);
    }

    private function compradorNome(array $dados, int $propriedadeId): ?string
    {
        $compradorId = (int)($dados['comprador_id'] ?? 0);
        if ($compradorId > 0) {
            $nome = DB::table('compradores')
                ->where('id', $compradorId)
                ->where('propriedade_id', $propriedadeId)
                ->where('ativo', 1)
                ->value('nome');

            if ($nome) {
                return (string)$nome;
            }
        }

        return trim($dados['pessoa'] ?? '') ?: null;
    }

    private function idDaPropriedade(string $tabela, $id, int $propriedadeId): ?int
    {
        $id = (int)($id ?? 0);
        if ($id <= 0) {
            return null;
        }

        return DB::table($tabela)
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->exists() ? $id : null;
    }

    private function subcategoriaId($id, $categoriaId): ?int
    {
        $id = (int)($id ?? 0);
        if ($id <= 0) {
            return null;
        }

        $query = DB::table('categorias')
            ->where('id', $id)
            ->where('ativo', 1)
            ->whereNotNull('categoria_pai_id');

        $categoriaId = (int)($categoriaId ?? 0);
        if ($categoriaId > 0) {
            $query->where('categoria_pai_id', $categoriaId);
        }

        return $query->exists() ? $id : null;
    }
}

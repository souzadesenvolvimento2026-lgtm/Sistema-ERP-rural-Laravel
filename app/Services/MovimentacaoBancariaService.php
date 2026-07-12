<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MovimentacaoBancariaService
{
    public function pagina(int $propriedadeId): array
    {
        $movimentacoes = DB::table('movimentacoes_bancarias as mb')
            ->join('contas as ct', 'ct.id', '=', 'mb.conta_id')
            ->where('mb.propriedade_id', $propriedadeId)
            ->orderByDesc('mb.data_movimento')
            ->orderByDesc('mb.id')
            ->get([
                'mb.id',
                'mb.data_movimento',
                'mb.tipo',
                'mb.descricao',
                'mb.valor',
                'mb.origem',
                'mb.status',
                'ct.nome as conta_nome',
            ])
            ->each(function ($movimentacao): void {
                $movimentacao->can_reconcile = $movimentacao->status === 'pendente';
                $movimentacao->can_ignore = $movimentacao->status === 'pendente';
                $movimentacao->status_tone = $movimentacao->status === 'pendente' ? 'warning' : 'success';
            });

        $validas = $movimentacoes->where('status', '!=', 'ignorado');
        $entradas = (float) $validas->where('tipo', 'entrada')->sum('valor');
        $saidas = (float) $validas->where('tipo', 'saida')->sum('valor');

        return [
            'activeModule' => 'financeiro',
            'movimentacoes' => $movimentacoes,
            'totais' => [
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => $entradas - $saidas,
                'pendentes' => $movimentacoes->where('status', 'pendente')->count(),
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        $contaPertence = DB::table('contas')
            ->where('id', (int) $dados['conta_id'])
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->exists();

        abort_unless($contaPertence, 422, 'Selecione uma conta válida.');

        DB::table('movimentacoes_bancarias')->insert([
            'propriedade_id' => $propriedadeId,
            'conta_id' => (int) $dados['conta_id'],
            'data_movimento' => $dados['data_movimento'],
            'tipo' => $dados['tipo'],
            'descricao' => trim($dados['descricao']),
            'valor' => $this->decimal($dados['valor']),
            'origem' => $dados['origem'] ?? 'manual',
            'status' => 'pendente',
            'usuario_id' => $usuarioId,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    public function atualizarStatus(int $id, int $propriedadeId, string $status): void
    {
        abort_unless(in_array($status, ['pendente', 'conciliado', 'ignorado'], true), 422, 'Status inválido.');

        DB::transaction(function () use ($id, $propriedadeId, $status): void {
            $movimentacao = DB::table('movimentacoes_bancarias')
                ->where('id', $id)
                ->where('propriedade_id', $propriedadeId)
                ->lockForUpdate()
                ->first(['id', 'status']);
            abort_if(! $movimentacao, 404);

            if ($movimentacao->status === $status) {
                return;
            }

            abort_unless(
                $movimentacao->status === 'pendente' && in_array($status, ['conciliado', 'ignorado'], true),
                422,
                'Somente movimentações pendentes podem ser conciliadas ou ignoradas.',
            );

            DB::table('movimentacoes_bancarias')
                ->where('id', $id)
                ->where('propriedade_id', $propriedadeId)
                ->update(['status' => $status]);
        }, 3);
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
}

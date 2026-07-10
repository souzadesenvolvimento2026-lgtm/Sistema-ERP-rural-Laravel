<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ContratoService
{
    public function pagina(int $propriedadeId): array
    {
        $contratos = DB::table('contratos as c')
            ->leftJoin('safras as s', 's.id', '=', 'c.safra_id')
            ->where('c.propriedade_id', $propriedadeId)
            ->orderByDesc('c.data_contrato')
            ->orderByDesc('c.id')
            ->get([
                'c.id',
                'c.safra_id',
                'c.tipo',
                'c.numero',
                'c.contraparte',
                'c.produto',
                'c.quantidade',
                'c.unidade',
                'c.preco_unitario',
                'c.valor_total',
                'c.data_contrato',
                'c.data_vencimento',
                'c.status',
                'c.observacoes',
                's.descricao as safra_nome',
                DB::raw('(SELECT COALESCE(SUM(ce.quantidade), 0) FROM contrato_entregas ce WHERE ce.contrato_id = c.id) as entregue'),
                DB::raw('(SELECT COALESCE(SUM(ce.valor), 0) FROM contrato_entregas ce WHERE ce.contrato_id = c.id) as valor_entregue'),
            ]);

        return [
            'activeModule' => 'estoque-producao',
            'contratos' => $contratos,
            'safras' => DB::table('safras')->where('propriedade_id', $propriedadeId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'tipos' => ['venda' => 'Venda', 'deposito' => 'Depósito', 'armazenagem' => 'Armazenagem', 'fixacao' => 'Fixação', 'compra' => 'Compra'],
            'totais' => [
                'contratos' => $contratos->count(),
                'abertos' => $contratos->filter(fn ($contrato) => !in_array($contrato->status, ['entregue', 'cancelado'], true))->count(),
                'valor_contratado' => (float)$contratos->sum('valor_total'),
                'valor_entregue' => (float)$contratos->sum('valor_entregue'),
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        $quantidade = $this->decimal($dados['quantidade'] ?? 0);
        $preco = $this->decimal($dados['preco_unitario'] ?? 0);
        $valor = $this->decimal($dados['valor_total'] ?? 0);
        if ($valor <= 0 && $quantidade > 0 && $preco > 0) {
            $valor = $quantidade * $preco;
        }

        DB::table('contratos')->insert([
            'propriedade_id' => $propriedadeId,
            'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propriedadeId),
            'tipo' => $dados['tipo'] ?? 'venda',
            'numero' => trim($dados['numero']),
            'contraparte' => trim($dados['contraparte'] ?? '') ?: null,
            'produto' => trim($dados['produto'] ?? '') ?: null,
            'quantidade' => $quantidade,
            'unidade' => trim($dados['unidade'] ?? '') ?: 'sc',
            'preco_unitario' => $preco,
            'valor_total' => $valor,
            'data_contrato' => $dados['data_contrato'],
            'data_vencimento' => ($dados['data_vencimento'] ?? null) ?: null,
            'status' => 'aberto',
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            'usuario_id' => $usuarioId,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    public function registrarEntrega(array $dados, int $propriedadeId): int
    {
        $contrato = DB::table('contratos')
            ->where('id', (int)$dados['contrato_id'])
            ->where('propriedade_id', $propriedadeId)
            ->first();

        abort_unless($contrato, 404, 'Contrato inválido.');

        DB::table('contrato_entregas')->insert([
            'contrato_id' => $contrato->id,
            'data_entrega' => $dados['data_entrega'],
            'quantidade' => $this->decimal($dados['quantidade'] ?? 0),
            'unidade' => trim($dados['unidade'] ?? '') ?: $contrato->unidade,
            'valor' => $this->decimal($dados['valor'] ?? 0),
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
        ]);

        $entregaId = (int)DB::getPdo()->lastInsertId();
        $this->atualizarStatusEntrega((int)$contrato->id, (float)$contrato->quantidade);

        return $entregaId;
    }

    private function atualizarStatusEntrega(int $contratoId, float $quantidadeContratada): void
    {
        $entregue = (float)DB::table('contrato_entregas')->where('contrato_id', $contratoId)->sum('quantidade');
        $status = $entregue >= $quantidadeContratada && $quantidadeContratada > 0 ? 'entregue' : 'parcial';

        DB::table('contratos')->where('id', $contratoId)->update(['status' => $status]);
    }

    private function idDaPropriedade(string $table, mixed $id, int $propriedadeId): ?int
    {
        if (!$id) {
            return null;
        }

        $id = (int)$id;

        return DB::table($table)
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->exists() ? $id : null;
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
}

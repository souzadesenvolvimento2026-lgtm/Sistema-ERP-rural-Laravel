<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgendaFinanceiraService
{
    public function pagina(int $propriedadeId, ?Request $request = null): array
    {
        $filtros = $this->filtros($request);
        $eventos = $this->eventos($propriedadeId, $filtros);
        $hoje = now()->toDateString();
        $limiteSemana = now()->addDays(7)->toDateString();

        return [
            'activeModule' => 'financeiro',
            'eventos' => $eventos,
            'filtros' => $filtros,
            'contas' => DB::table('contas')
                ->where('propriedade_id', $propriedadeId)
                ->where('ativo', 1)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'totais' => [
                'pagar' => (float)$eventos->where('origem', 'despesa')->sum('valor'),
                'receber' => (float)$eventos->where('origem', 'receita')->sum('valor'),
                'vencidos' => $eventos->filter(fn ($evento) => $evento->data_evento && $evento->data_evento < $hoje)->count(),
                'semana' => $eventos->filter(fn ($evento) => $evento->data_evento && $evento->data_evento >= $hoje && $evento->data_evento <= $limiteSemana)->count(),
                'boletos' => $eventos->filter(fn ($evento) => $evento->origem === 'despesa' && $evento->forma_pagamento === 'boleto' && $evento->data_evento && $evento->data_evento >= $hoje && $evento->data_evento <= $limiteSemana)->count(),
            ],
        ];
    }

    public function pagarDespesa(array $dados, int $propriedadeId, ?int $usuarioId): void
    {
        $payload = [
            'status_pagamento' => 'pago',
            'data_pagamento' => ($dados['data_pagamento'] ?? null) ?: now()->toDateString(),
        ];

        $contaId = $this->contaId($dados['conta_id'] ?? null, $propriedadeId);
        if ($contaId) {
            $payload['conta_id'] = $contaId;
        }

        $despesaId = (int)$dados['id'];
        $alteradas = DB::table('despesas')
            ->where('id', (int)$dados['id'])
            ->where('propriedade_id', $propriedadeId)
            ->where('status_aprovacao', 'aprovada')
            ->update($payload);

        if ($alteradas > 0) {
            $this->auditar($usuarioId, 'agenda_pagar_despesa', 'despesas', $despesaId, $propriedadeId, 'Pagamento confirmado pela agenda');
        }
    }

    public function receberReceita(array $dados, int $propriedadeId, ?int $usuarioId): void
    {
        $payload = [
            'status' => 'recebido',
            'data_recebimento' => ($dados['data_recebimento'] ?? null) ?: now()->toDateString(),
        ];

        $contaId = $this->contaId($dados['conta_id'] ?? null, $propriedadeId);
        if ($contaId) {
            $payload['conta_id'] = $contaId;
        }

        $receitaId = (int)$dados['id'];
        $alteradas = DB::table('receitas')
            ->where('id', (int)$dados['id'])
            ->where('propriedade_id', $propriedadeId)
            ->update($payload);

        if ($alteradas > 0) {
            $this->auditar($usuarioId, 'agenda_receber_receita', 'receitas', $receitaId, $propriedadeId, 'Recebimento confirmado pela agenda');
        }
    }

    private function eventos(int $propriedadeId, array $filtros)
    {
        $despesas = DB::table('despesas as d')
            ->leftJoin('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'd.conta_id')
            ->where('d.propriedade_id', $propriedadeId)
            ->where('d.status_pagamento', 'pendente')
            ->where('d.status_aprovacao', 'aprovada')
            ->when($filtros['forma_pagamento'] === 'boleto', fn ($query) => $query->where('d.forma_pagamento', 'boleto'))
            ->when($filtros['alerta'] === 'boletos_vencendo', function ($query) {
                $query->whereBetween('d.data_vencimento', [now()->toDateString(), now()->addDays(7)->toDateString()]);
            })
            ->selectRaw("
                'despesa' as origem,
                d.id,
                d.descricao as titulo,
                d.fornecedor as pessoa,
                d.valor_total as valor,
                d.data_vencimento as data_evento,
                d.status_pagamento as status,
                d.forma_pagamento,
                COALESCE(c.nome, 'Sem categoria') as categoria,
                ct.nome as conta_nome
            ");

        return DB::query()
            ->fromSub(
                DB::table('receitas as r')
                    ->leftJoin('contas as ct', 'ct.id', '=', 'r.conta_id')
                    ->where('r.propriedade_id', $propriedadeId)
                    ->where('r.status', 'pendente')
                    ->when($filtros['forma_pagamento'] === 'boleto' || $filtros['alerta'] === 'boletos_vencendo', fn ($query) => $query->whereRaw('1 = 0'))
                    ->selectRaw("
                        'receita' as origem,
                        r.id,
                        r.descricao as titulo,
                        r.comprador as pessoa,
                        r.valor_total as valor,
                        COALESCE(r.data_recebimento, r.data_venda) as data_evento,
                        r.status,
                        '' as forma_pagamento,
                        'Receita' as categoria,
                        ct.nome as conta_nome
                    ")
                    ->unionAll($despesas),
                'agenda'
            )
            ->orderByRaw('data_evento IS NULL')
            ->orderBy('data_evento')
            ->orderByDesc('valor')
            ->get();
    }

    private function filtros(?Request $request): array
    {
        $formaPagamento = $request?->query('fp') === 'boleto' ? 'boleto' : '';
        $alerta = $request?->query('alerta') === 'boletos_vencendo' ? 'boletos_vencendo' : '';

        return [
            'forma_pagamento' => $formaPagamento,
            'alerta' => $alerta,
        ];
    }

    private function contaId($contaId, int $propriedadeId): ?int
    {
        if (!$contaId) {
            return null;
        }

        $exists = DB::table('contas')
            ->where('id', (int)$contaId)
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->exists();

        return $exists ? (int)$contaId : null;
    }

    private function auditar(?int $usuarioId, string $acao, string $tabela, int $registroId, int $propriedadeId, string $detalhes): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'propriedade_id' => $propriedadeId,
                'detalhes' => $detalhes,
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable) {
            // Auditoria nao deve impedir a baixa pela agenda.
        }
    }
}

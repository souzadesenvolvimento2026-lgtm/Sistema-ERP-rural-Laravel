<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ContaBancariaService
{
    public function pagina(int $propriedadeId): array
    {
        $this->garantirEstruturaTransferencias();

        $contas = $this->contas($propriedadeId);
        $saldoInicial = (float)$contas->sum('saldo_inicial');

        return [
            'activeModule' => 'financeiro',
            'contas' => $contas,
            'contasAtivas' => $contas->where('ativo', 1)->values(),
            'transferencias' => $this->ultimasTransferencias($propriedadeId),
            'totais' => [
                'contas' => $contas->count(),
                'ativas' => $contas->where('ativo', 1)->count(),
                'saldo_inicial' => $saldoInicial,
                'saldo_atual' => (float)$contas->sum('saldo_atual'),
                'inativas' => $contas->where('ativo', 0)->count(),
            ],
        ];
    }

    public function contasAtivas(int $propriedadeId): Collection
    {
        return DB::table('contas')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    public function criar(array $dados, int $propriedadeId): int
    {
        $this->garantirEstruturaTransferencias();

        DB::table('contas')->insert([
            'propriedade_id' => $propriedadeId,
            ...$this->payload($dados),
            'ativo' => 1,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    public function buscar(int $contaId, int $propriedadeId): object
    {
        $conta = DB::table('contas')
            ->where('id', $contaId)
            ->where('propriedade_id', $propriedadeId)
            ->first();

        abort_if($conta === null, 404);

        return $conta;
    }

    public function atualizar(int $contaId, array $dados, int $propriedadeId): void
    {
        $this->garantirEstruturaTransferencias();

        DB::table('contas')
            ->where('id', $contaId)
            ->where('propriedade_id', $propriedadeId)
            ->update($this->payload($dados));
    }

    public function alternarStatus(int $contaId, int $propriedadeId): void
    {
        $this->garantirEstruturaTransferencias();

        $conta = $this->buscar($contaId, $propriedadeId);

        DB::table('contas')
            ->where('id', $contaId)
            ->where('propriedade_id', $propriedadeId)
            ->update(['ativo' => $conta->ativo ? 0 : 1]);
    }

    public function registrarTransferencia(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        $this->garantirEstruturaTransferencias();

        $origemId = (int)($dados['origem'] ?? 0);
        $destinoId = (int)($dados['destino'] ?? 0);

        if ($origemId <= 0 || $destinoId <= 0) {
            throw new RuntimeException('Selecione a conta de origem e a conta de destino.');
        }

        if ($origemId === $destinoId) {
            throw new RuntimeException('A conta de origem e a conta de destino precisam ser diferentes.');
        }

        $origem = $this->buscarAtivaComSaldo($propriedadeId, $origemId);
        $destino = $this->buscarAtivaComSaldo($propriedadeId, $destinoId);

        if ($origem === null || $destino === null) {
            throw new RuntimeException('Selecione contas validas da propriedade atual.');
        }

        $valor = $this->decimal($dados['valor'] ?? 0);
        if ($valor <= 0) {
            throw new RuntimeException('Informe um valor de transferencia maior que zero.');
        }

        if ($valor > (float)$origem->saldo_atual) {
            throw new RuntimeException('Saldo insuficiente na conta de origem para concluir a transferencia.');
        }

        $data = (string)($dados['data_transferencia'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $data = date('Y-m-d');
        }

        $descricao = trim((string)($dados['descricao'] ?? '')) ?: 'Transferencia entre contas';

        DB::table('transferencias')->insert([
            'propriedade_id' => $propriedadeId,
            'conta_origem_id' => $origemId,
            'conta_destino_id' => $destinoId,
            'valor' => $valor,
            'data_transferencia' => $data,
            'descricao' => $descricao,
            'usuario_id' => $usuarioId,
            'criado_em' => now(),
        ]);

        $transferenciaId = (int)DB::getPdo()->lastInsertId();
        $this->auditarTransferencia($transferenciaId, $propriedadeId, $usuarioId, $origem, $destino, $valor, $data, $descricao);

        return $transferenciaId;
    }

    private function auditarTransferencia(
        int $transferenciaId,
        int $propriedadeId,
        ?int $usuarioId,
        object $origem,
        object $destino,
        float $valor,
        string $data,
        string $descricao
    ): void {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $usuarioId,
                'acao' => 'registrar_transferencia_contas',
                'tabela' => 'transferencias',
                'registro_id' => $transferenciaId,
                'propriedade_id' => $propriedadeId,
                'detalhes' => sprintf(
                    'Transferencia de %s para %s no valor de R$ %s em %s. %s',
                    (string)$origem->nome,
                    (string)$destino->nome,
                    number_format($valor, 2, ',', '.'),
                    date('d/m/Y', strtotime($data) ?: time()),
                    $descricao
                ),
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable) {
            // A auditoria nao deve impedir a transferencia financeira.
        }
    }

    private function contas(int $propriedadeId): Collection
    {
        $saldoSql = $this->saldoSql();

        return DB::table('contas')
            ->where('propriedade_id', $propriedadeId)
            ->orderByDesc('ativo')
            ->orderBy('nome')
            ->select(['contas.*', DB::raw($saldoSql.' AS saldo_atual')])
            ->get();
    }

    private function buscarAtivaComSaldo(int $propriedadeId, int $contaId): ?object
    {
        return DB::table('contas')
            ->where('id', $contaId)
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->select(['contas.*', DB::raw($this->saldoSql().' AS saldo_atual')])
            ->first();
    }

    private function ultimasTransferencias(int $propriedadeId): Collection
    {
        return DB::table('transferencias as tf')
            ->join('contas as origem', 'origem.id', '=', 'tf.conta_origem_id')
            ->join('contas as destino', 'destino.id', '=', 'tf.conta_destino_id')
            ->leftJoin('usuarios as usuario', 'usuario.id', '=', 'tf.usuario_id')
            ->where('tf.propriedade_id', $propriedadeId)
            ->orderByDesc('tf.data_transferencia')
            ->orderByDesc('tf.id')
            ->limit(10)
            ->get([
                'tf.id',
                'tf.valor',
                'tf.data_transferencia',
                'tf.descricao',
                'origem.nome as origem_nome',
                'destino.nome as destino_nome',
                'usuario.nome as usuario_nome',
            ]);
    }

    private function saldoSql(): string
    {
        return "contas.saldo_inicial
            + COALESCE((SELECT SUM(r.valor_total) FROM receitas r WHERE r.conta_id=contas.id AND r.status='recebido'),0)
            - COALESCE((SELECT SUM(d.valor_total) FROM despesas d WHERE d.conta_id=contas.id AND d.status_pagamento='pago' AND d.status_aprovacao='aprovada'),0)
            - COALESCE((SELECT SUM(tf.valor) FROM transferencias tf WHERE tf.conta_origem_id=contas.id),0)
            + COALESCE((SELECT SUM(tf.valor) FROM transferencias tf WHERE tf.conta_destino_id=contas.id),0)";
    }

    private function garantirEstruturaTransferencias(): void
    {
        if (Schema::hasTable('transferencias')) {
            return;
        }

        Schema::create('transferencias', function ($table) {
            $table->id();
            $table->unsignedBigInteger('propriedade_id');
            $table->unsignedBigInteger('conta_origem_id');
            $table->unsignedBigInteger('conta_destino_id');
            $table->decimal('valor', 12, 2);
            $table->date('data_transferencia');
            $table->string('descricao', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('criado_em')->useCurrent();
            $table->index('propriedade_id');
            $table->index('conta_origem_id');
            $table->index('conta_destino_id');
            $table->index('usuario_id');
        });
    }

    private function decimal($value): float
    {
        return (float)str_replace(['.', ','], ['', '.'], trim((string)$value));
    }

    private function payload(array $dados): array
    {
        return [
            'nome' => trim($dados['nome']),
            'tipo' => $dados['tipo'] ?? 'conta_corrente',
            'banco' => trim($dados['banco'] ?? '') ?: null,
            'agencia' => trim($dados['agencia'] ?? '') ?: null,
            'numero_conta' => trim($dados['numero_conta'] ?? '') ?: null,
            'saldo_inicial' => $this->decimal($dados['saldo_inicial'] ?? 0),
        ];
    }
}

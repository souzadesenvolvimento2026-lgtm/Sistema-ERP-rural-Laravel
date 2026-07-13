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

        return [
            'activeModule' => 'financeiro',
            'contas' => $contas,
            'contasAtivas' => $contas,
            'transferencias' => $this->ultimasTransferencias($propriedadeId),
            'totais' => [
                'contas' => $contas->count(),
                'ativas' => $contas->count(),
                'saldo_inicial' => (float) $contas->sum('saldo_inicial'),
                'saldo_atual' => (float) $contas->sum('saldo_atual'),
                'inativas' => $this->contasInativas($propriedadeId),
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

        return (int) DB::getPdo()->lastInsertId();
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

        $transferencia = $this->validarTransferencia($dados, $propriedadeId);

        DB::table('transferencias')->insert([
            'propriedade_id' => $propriedadeId,
            'conta_origem_id' => $transferencia['origem']->id,
            'conta_destino_id' => $transferencia['destino']->id,
            'valor' => $transferencia['valor'],
            'data_transferencia' => $transferencia['data'],
            'descricao' => $transferencia['descricao'],
            'usuario_id' => $usuarioId,
            'criado_em' => now(),
        ]);

        $transferenciaId = (int) DB::getPdo()->lastInsertId();
        $this->auditarTransferenciaCriada($transferenciaId, $propriedadeId, $usuarioId, $transferencia);

        return $transferenciaId;
    }

    public function buscarTransferencia(int $transferenciaId, int $propriedadeId): object
    {
        $this->garantirEstruturaTransferencias();

        $transferencia = DB::table('transferencias as tf')
            ->join('contas as origem', 'origem.id', '=', 'tf.conta_origem_id')
            ->join('contas as destino', 'destino.id', '=', 'tf.conta_destino_id')
            ->leftJoin('usuarios as usuario', 'usuario.id', '=', 'tf.usuario_id')
            ->where('tf.id', $transferenciaId)
            ->where('tf.propriedade_id', $propriedadeId)
            ->first([
                'tf.id',
                'tf.propriedade_id',
                'tf.conta_origem_id',
                'tf.conta_destino_id',
                'tf.valor',
                'tf.data_transferencia',
                'tf.descricao',
                'tf.usuario_id',
                'origem.nome as origem_nome',
                'destino.nome as destino_nome',
                'usuario.nome as usuario_nome',
            ]);

        abort_if($transferencia === null, 404);

        return $transferencia;
    }

    public function atualizarTransferencia(int $transferenciaId, array $dados, int $propriedadeId, ?int $usuarioId): void
    {
        $this->garantirEstruturaTransferencias();

        DB::transaction(function () use ($transferenciaId, $dados, $propriedadeId, $usuarioId) {
            $antes = $this->buscarTransferencia($transferenciaId, $propriedadeId);
            $transferencia = $this->validarTransferencia($dados, $propriedadeId, $antes);

            $payload = [
                'conta_origem_id' => $transferencia['origem']->id,
                'conta_destino_id' => $transferencia['destino']->id,
                'valor' => $transferencia['valor'],
                'data_transferencia' => $transferencia['data'],
                'descricao' => $transferencia['descricao'],
                'usuario_id' => $usuarioId,
            ];

            if (Schema::hasColumn('transferencias', 'atualizado_em')) {
                $payload['atualizado_em'] = now();
            }

            DB::table('transferencias')
                ->where('id', $transferenciaId)
                ->where('propriedade_id', $propriedadeId)
                ->update($payload);

            $depois = $this->buscarTransferencia($transferenciaId, $propriedadeId);
            $this->auditarTransferenciaEditada($transferenciaId, $propriedadeId, $usuarioId, $antes, $depois);
        });
    }

    private function validarTransferencia(array $dados, int $propriedadeId, ?object $transferenciaOriginal = null): array
    {
        if ($this->contasAtivasQuantidade($propriedadeId) < 2) {
            throw new RuntimeException('Cadastre pelo menos duas contas ativas para registrar transferências.');
        }

        $origemId = (int) ($dados['origem'] ?? $dados['conta_origem_id'] ?? 0);
        $destinoId = (int) ($dados['destino'] ?? $dados['conta_destino_id'] ?? 0);

        if ($origemId <= 0 || $destinoId <= 0) {
            throw new RuntimeException('Selecione a conta de origem e a conta de destino.');
        }

        if ($origemId === $destinoId) {
            throw new RuntimeException('A conta de origem e a conta de destino precisam ser diferentes.');
        }

        $origem = $this->buscarAtivaComSaldo($propriedadeId, $origemId);
        $destino = $this->buscarAtivaComSaldo($propriedadeId, $destinoId);

        if ($origem === null || $destino === null) {
            throw new RuntimeException('Selecione contas válidas e ativas da propriedade atual.');
        }

        $valor = $this->decimal($dados['valor'] ?? 0);
        if ($valor <= 0) {
            throw new RuntimeException('Informe um valor de transferência maior que zero.');
        }

        $saldoDisponivel = (float) $origem->saldo_atual;
        if ($transferenciaOriginal !== null) {
            $valorAntigo = (float) $transferenciaOriginal->valor;

            if ((int) $transferenciaOriginal->conta_origem_id === $origemId) {
                $saldoDisponivel += $valorAntigo;
            }

            if ((int) $transferenciaOriginal->conta_destino_id === $origemId) {
                $saldoDisponivel -= $valorAntigo;
            }
        }

        if ($valor > $saldoDisponivel + 0.00001) {
            throw new RuntimeException('Saldo insuficiente na conta de origem para concluir a transferência.');
        }

        $data = trim((string) ($dados['data_transferencia'] ?? ''));
        if ($data === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $data = date('Y-m-d');
        }

        return [
            'origem' => $origem,
            'destino' => $destino,
            'valor' => $valor,
            'data' => $data,
            'descricao' => trim((string) ($dados['descricao'] ?? '')) ?: 'Transferência entre contas',
        ];
    }

    private function auditarTransferenciaCriada(int $transferenciaId, int $propriedadeId, ?int $usuarioId, array $transferencia): void
    {
        $this->registrarAuditoria([
            'usuario_id' => $usuarioId,
            'acao' => 'registrar_transferencia_contas',
            'tabela' => 'transferencias',
            'registro_id' => $transferenciaId,
            'propriedade_id' => $propriedadeId,
            'detalhes' => sprintf(
                'Transferência de %s para %s no valor de R$ %s em %s. %s',
                (string) $transferencia['origem']->nome,
                (string) $transferencia['destino']->nome,
                number_format((float) $transferencia['valor'], 2, ',', '.'),
                date('d/m/Y', strtotime((string) $transferencia['data']) ?: time()),
                (string) $transferencia['descricao']
            ),
        ]);
    }

    private function auditarTransferenciaEditada(int $transferenciaId, int $propriedadeId, ?int $usuarioId, object $antes, object $depois): void
    {
        $this->registrarAuditoria([
            'usuario_id' => $usuarioId,
            'acao' => 'editar_transferencia_contas',
            'tabela' => 'transferencias',
            'registro_id' => $transferenciaId,
            'propriedade_id' => $propriedadeId,
            'detalhes' => sprintf(
                'Transferência editada. Antes: %s. Depois: %s.',
                $this->resumoTransferencia($antes),
                $this->resumoTransferencia($depois)
            ),
        ]);
    }

    private function registrarAuditoria(array $dados): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                ...$dados,
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function resumoTransferencia(object $transferencia): string
    {
        return sprintf(
            '%s para %s, R$ %s em %s, descrição "%s"',
            (string) $transferencia->origem_nome,
            (string) $transferencia->destino_nome,
            number_format((float) $transferencia->valor, 2, ',', '.'),
            date('d/m/Y', strtotime((string) $transferencia->data_transferencia) ?: time()),
            trim((string) $transferencia->descricao) ?: 'Transferência entre contas'
        );
    }

    private function contas(int $propriedadeId): Collection
    {
        return DB::table('contas')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->select(['contas.*', DB::raw($this->saldoSql().' AS saldo_atual')])
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
            ->limit(12)
            ->get([
                'tf.id',
                'tf.conta_origem_id',
                'tf.conta_destino_id',
                'tf.valor',
                'tf.data_transferencia',
                'tf.descricao',
                'tf.usuario_id',
                'origem.nome as origem_nome',
                'destino.nome as destino_nome',
                'usuario.nome as usuario_nome',
            ]);
    }

    private function saldoSql(): string
    {
        return "COALESCE(contas.saldo_inicial, 0)
            + COALESCE((SELECT SUM(r.valor_total) FROM receitas r WHERE r.conta_id = contas.id AND r.propriedade_id = contas.propriedade_id AND r.status = 'recebido'), 0)
            - COALESCE((SELECT SUM(d.valor_total) FROM despesas d WHERE d.conta_id = contas.id AND d.propriedade_id = contas.propriedade_id AND d.status_pagamento = 'pago' AND d.status_aprovacao = 'aprovada'), 0)
            - COALESCE((SELECT SUM(tf.valor) FROM transferencias tf WHERE tf.conta_origem_id = contas.id AND tf.propriedade_id = contas.propriedade_id), 0)
            + COALESCE((SELECT SUM(tf.valor) FROM transferencias tf WHERE tf.conta_destino_id = contas.id AND tf.propriedade_id = contas.propriedade_id), 0)";
    }

    private function contasAtivasQuantidade(int $propriedadeId): int
    {
        return (int) DB::table('contas')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->count();
    }

    private function contasInativas(int $propriedadeId): int
    {
        return (int) DB::table('contas')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 0)
            ->count();
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
        $value = preg_replace('/[^\d,.\-]/', '', trim((string) $value));

        if ($value === '' || $value === null) {
            return 0.0;
        }

        if (str_contains($value, ',')) {
            return (float) str_replace(['.', ','], ['', '.'], $value);
        }

        return (float) $value;
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

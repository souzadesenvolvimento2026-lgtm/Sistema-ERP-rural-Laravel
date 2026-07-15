<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class FinanceiroLancamentoService
{
    public function criar(array $dados, int $propriedadeId, ?int $usuarioId, ?UploadedFile $comprovante = null): int
    {
        return $dados['tipo'] === 'receita'
            ? $this->criarReceita($dados, $propriedadeId, $usuarioId)
            : $this->criarDespesa($dados, $propriedadeId, $usuarioId, $comprovante);
    }

    private function criarDespesa(array $dados, int $propriedadeId, ?int $usuarioId, ?UploadedFile $comprovante = null): int
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
        $arquivoComprovante = $this->salvarComprovante($comprovante);
        $contaId = !empty($dados['baixado'])
            ? (int) $this->contaAtiva($propriedadeId, (int) ($dados['conta_id'] ?? 0))->id
            : $this->idDaPropriedade('contas', $dados['conta_id'] ?? null, $propriedadeId);
        $ultimoId = 0;

        for ($parcela = 1; $parcela <= $numeroParcelas; $parcela++) {
            $meses = $parcela - 1;

            DB::table('despesas')->insert($this->filtrarColunas('despesas', [
                'propriedade_id' => $propriedadeId,
                'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propriedadeId),
                'talhao_id' => $this->idDaPropriedade('talhoes', $dados['talhao_id'] ?? null, $propriedadeId),
                'categoria_id' => (int)$dados['categoria_id'],
                'subcategoria_id' => $this->subcategoriaId($dados['subcategoria_id'] ?? null, $dados['categoria_id'] ?? null),
                'conta_id' => $contaId,
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
                'nota_fiscal' => trim($dados['nota_fiscal'] ?? '') ?: null,
                'comprovante' => $parcela === 1 ? $arquivoComprovante : null,
                'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
                'usuario_id' => $usuarioId,
            ]));

            $ultimoId = (int)DB::getPdo()->lastInsertId();
            $this->sincronizarPatrimonio($propriedadeId, $ultimoId, $dados['maquina_id'] ?? null);
        }

        return $ultimoId;
    }

    private function criarReceita(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        $quantidade = $this->decimal($dados['quantidade'] ?? 0);
        $precoUnitario = $this->decimal($dados['preco_unitario'] ?? 0);
        $valorTotal = $this->money($dados['valor_total'] ?? 0);
        if (($dados['tipo_receita'] ?? 'graos') === 'outras') {
            $quantidade = 0.0;
            $precoUnitario = 0.0;
        }

        if ($valorTotal <= 0 && $quantidade > 0 && $precoUnitario > 0) {
            $valorTotal = $quantidade * $precoUnitario;
        }

        $dataRecebimento = ($dados['data_recebimento'] ?? null) ?: ($dados['data_vencimento'] ?? null);
        if (!empty($dados['baixado']) && empty($dataRecebimento)) {
            $dataRecebimento = $dados['data_lancamento'];
        }

        DB::table('receitas')->insert($this->filtrarColunas('receitas', [
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
            'data_recebimento' => $dataRecebimento ?: null,
            'status' => $dados['baixado'] ? 'recebido' : 'pendente',
            'status_aprovacao' => 'aprovada',
            'aprovado_por' => $usuarioId,
            'aprovado_em' => now(),
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            'usuario_id' => $usuarioId,
        ]));

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

    private function contaAtiva(int $propriedadeId, int $contaId): object
    {
        if ($contaId <= 0) {
            throw new RuntimeException('Informe a conta real usada no pagamento.');
        }

        $conta = DB::table('contas')
            ->where('id', $contaId)
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->first(['id']);

        if (! $conta) {
            throw new RuntimeException('Selecione uma conta ativa da propriedade para baixar o pagamento.');
        }

        return $conta;
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

    private function salvarComprovante(?UploadedFile $arquivo): ?string
    {
        if (! $arquivo) {
            return null;
        }

        File::ensureDirectoryExists(base_path('../uploads/comprovantes'));

        $nome = 'fin_'.uniqid().'.'.$arquivo->getClientOriginalExtension();
        $arquivo->move(base_path('../uploads/comprovantes'), $nome);

        return $nome;
    }

    private function sincronizarPatrimonio(int $propriedadeId, int $despesaId, $maquinaId): void
    {
        $maquinaId = (int)($maquinaId ?? 0);
        if (! Schema::hasTable('maquina_lancamentos') || ! Schema::hasTable('maquinas')) {
            return;
        }

        $marcador = 'FINANCEIRO_PATRIMONIO_DESPESA';
        DB::table('maquina_lancamentos')
            ->where('propriedade_id', $propriedadeId)
            ->where('observacoes', 'like', "%{$marcador} #{$despesaId}%")
            ->delete();

        if ($maquinaId <= 0) {
            return;
        }

        $maquinaExiste = DB::table('maquinas')
            ->where('id', $maquinaId)
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->exists();

        if (! $maquinaExiste) {
            return;
        }

        $despesa = DB::table('despesas')
            ->where('id', $despesaId)
            ->where('propriedade_id', $propriedadeId)
            ->where('status_pagamento', '!=', 'cancelado')
            ->first();

        if (! $despesa) {
            return;
        }

        DB::table('maquina_lancamentos')->insert($this->filtrarColunas('maquina_lancamentos', [
            'propriedade_id' => $propriedadeId,
            'maquina_id' => $maquinaId,
            'safra_id' => $despesa->safra_id ?: null,
            'talhao_id' => $despesa->talhao_id ?: null,
            'tipo' => $this->tipoPatrimonio((int)$despesa->categoria_id, $despesa->subcategoria_id ? (int)$despesa->subcategoria_id : null, (string)$despesa->descricao),
            'data_lancamento' => $despesa->data_lancamento,
            'descricao' => mb_substr((string)$despesa->descricao, 0, 180),
            'fornecedor' => mb_substr((string)($despesa->fornecedor ?? ''), 0, 150),
            'quantidade' => $despesa->quantidade ?: null,
            'unidade' => (string)($despesa->unidade ?? ''),
            'valor_unitario' => (float)($despesa->valor_unitario ?: $despesa->valor_total),
            'valor_total' => (float)$despesa->valor_total,
            'comprovante' => (string)($despesa->comprovante ?? ''),
            'observacoes' => "{$marcador} #{$despesaId}",
            'usuario_id' => $despesa->usuario_id ?: null,
        ]));
    }

    private function tipoPatrimonio(int $categoriaId, ?int $subcategoriaId, string $descricao): string
    {
        $nomes = DB::table('categorias')
            ->whereIn('id', array_values(array_filter([$categoriaId, $subcategoriaId])))
            ->pluck('nome')
            ->implode(' ');

        $texto = mb_strtolower($nomes.' '.$descricao, 'UTF-8');
        $semAcentos = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        if (str_contains($semAcentos, 'combust')) return 'abastecimento';
        if (str_contains($semAcentos, 'peca')) return 'pecas';
        if (str_contains($semAcentos, 'seguro')) return 'seguro';
        if (str_contains($semAcentos, 'oleo')) return 'troca_oleo';
        if (str_contains($semAcentos, 'revis')) return 'manutencao_preventiva';
        if (str_contains($semAcentos, 'manuten')) return 'manutencao_corretiva';

        return 'outro';
    }

    private function filtrarColunas(string $tabela, array $dados): array
    {
        static $colunas = [];

        if (! isset($colunas[$tabela])) {
            $colunas[$tabela] = array_flip(Schema::getColumnListing($tabela));
        }

        return array_intersect_key($dados, $colunas[$tabela]);
    }
}

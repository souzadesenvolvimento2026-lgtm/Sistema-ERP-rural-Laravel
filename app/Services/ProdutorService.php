<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProdutorService
{
    public function pagina(int $propriedadeId): array
    {
        $this->garantirPadrao($propriedadeId);
        $produtores = DB::table('produtores')
            ->where('propriedade_id', $propriedadeId)
            ->orderByDesc('ativo')
            ->orderBy('nome')
            ->get();

        return [
            'activeModule' => 'fiscal',
            'produtores' => $produtores,
            'totais' => [
                'produtores' => $produtores->count(),
                'ativos' => $produtores->where('ativo', 1)->count(),
                'inativos' => $produtores->where('ativo', 0)->count(),
                'participacao' => (float)$produtores->where('ativo', 1)->sum('participacao_percentual'),
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId): int
    {
        DB::table('produtores')->updateOrInsert(
            ['propriedade_id' => $propriedadeId, 'nome' => trim($dados['nome'])],
            [
                'documento' => trim($dados['documento'] ?? '') ?: null,
                'participacao_percentual' => $this->decimalOuNulo($dados['participacao_percentual'] ?? null),
                'ativo' => 1,
            ]
        );

        return (int)DB::table('produtores')
            ->where('propriedade_id', $propriedadeId)
            ->where('nome', trim($dados['nome']))
            ->value('id');
    }

    public function atualizar(int $id, array $dados, int $propriedadeId): void
    {
        $this->garantirPertenceAPropriedade($id, $propriedadeId);

        DB::table('produtores')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->update([
                'nome' => trim($dados['nome']),
                'documento' => trim($dados['documento'] ?? '') ?: null,
                'participacao_percentual' => $this->decimalOuNulo($dados['participacao_percentual'] ?? null),
            ]);
    }

    public function alternarAtivo(int $id, int $propriedadeId): void
    {
        $produtor = DB::table('produtores')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->first(['ativo']);

        if (!$produtor) {
            abort(404, 'Produtor nao encontrado para a propriedade atual.');
        }

        DB::table('produtores')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->update(['ativo' => $produtor->ativo ? 0 : 1]);
    }

    private function garantirPertenceAPropriedade(int $id, int $propriedadeId): void
    {
        $existe = DB::table('produtores')
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->exists();

        if (!$existe) {
            abort(404, 'Produtor nao encontrado para a propriedade atual.');
        }
    }

    private function garantirPadrao(int $propriedadeId): void
    {
        $existe = DB::table('produtores')->where('propriedade_id', $propriedadeId)->exists();
        if ($existe) {
            return;
        }

        $propriedade = DB::table('propriedades')->where('id', $propriedadeId)->first(['responsavel', 'cnpj_cpf']);
        DB::table('produtores')->insert([
            'propriedade_id' => $propriedadeId,
            'nome' => trim((string)($propriedade->responsavel ?? '')) ?: 'Produtor principal',
            'documento' => trim((string)($propriedade->cnpj_cpf ?? '')) ?: null,
            'participacao_percentual' => 100,
            'ativo' => 1,
        ]);
    }

    private function decimalOuNulo($value): ?float
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        $value = trim((string)$value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return (float)$value;
    }
}

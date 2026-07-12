<?php

namespace App\Services;

use App\Domain\Property\FarmGroupEligibility;
use Illuminate\Support\Facades\DB;

class GrupoFazendaService
{
    public function __construct(private readonly FarmGroupEligibility $eligibility) {}

    public function pagina(): array
    {
        $grupos = DB::table('grupos_fazendas as gf')
            ->leftJoin('usuarios as u', 'u.id', '=', 'gf.aprovador_usuario_id')
            ->leftJoin('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
            ->leftJoin('propriedades as p', 'p.id', '=', 'gfp.propriedade_id')
            ->leftJoin('usuario_grupos_fazendas as ugf', 'ugf.grupo_id', '=', 'gf.id')
            ->where('gf.ativo', 1)
            ->groupBy('gf.id', 'gf.nome', 'gf.descricao', 'gf.aprovador_usuario_id', 'gf.ativo', 'gf.criado_em', 'u.nome')
            ->orderBy('gf.nome')
            ->get([
                'gf.id',
                'gf.nome',
                'gf.descricao',
                'gf.aprovador_usuario_id',
                'gf.ativo',
                'u.nome as aprovador_nome',
                DB::raw('COUNT(DISTINCT gfp.propriedade_id) as qtd_propriedades'),
                DB::raw('COUNT(DISTINCT ugf.usuario_id) as qtd_usuarios'),
                DB::raw("GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ', ') as propriedades_nomes"),
            ])
            ->map(function ($grupo) {
                $grupo->propriedades_ids = DB::table('grupo_fazenda_propriedades')
                    ->where('grupo_id', $grupo->id)
                    ->pluck('propriedade_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                return $grupo;
            });

        $propriedades = DB::table('propriedades')
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome', 'plano', 'ativo'])
            ->map(function (object $propriedade): object {
                foreach ($this->eligibility->for(
                    (bool) $propriedade->ativo,
                    $propriedade->plano,
                ) as $capability => $value) {
                    $propriedade->{$capability} = $value;
                }

                $propriedade->plan_label = ucfirst((string) ($propriedade->plano ?? 'basico'));

                return $propriedade;
            });

        return [
            'activeModule' => 'propriedades',
            'grupos' => $grupos,
            'propriedades' => $propriedades,
            'aprovadores' => DB::table('usuarios')
                ->where('ativo', 1)
                ->whereIn('perfil', ['administrador', 'gestor_financeiro', 'gestor_propriedade', 'gestao', 'financeiro'])
                ->orderBy('nome')
                ->get(['id', 'nome', 'email', 'perfil']),
            'totais' => [
                'grupos' => $grupos->count(),
                'fazendas' => (int) $grupos->sum('qtd_propriedades'),
                'usuarios' => (int) $grupos->sum('qtd_usuarios'),
                'premium' => DB::table('propriedades')->where('ativo', 1)->where('plano', 'premium')->count(),
            ],
        ];
    }

    public function criar(array $dados): int
    {
        $propriedades = $this->propriedadesPremium($dados['propriedades'] ?? []);
        abort_if($propriedades->isEmpty(), 422, 'Selecione pelo menos uma fazenda Premium.');
        $this->validarAprovador($dados['aprovador_usuario_id'] ?? null, $propriedades->pluck('id')->all());

        DB::table('grupos_fazendas')->insert([
            'nome' => trim($dados['nome']),
            'descricao' => trim($dados['descricao'] ?? '') ?: null,
            'aprovador_usuario_id' => ($dados['aprovador_usuario_id'] ?? null) ?: null,
            'ativo' => 1,
        ]);

        $grupoId = (int) DB::getPdo()->lastInsertId();
        $this->sincronizarPropriedades($grupoId, $propriedades->pluck('id')->all());
        $this->vincularAprovador($grupoId, $dados['aprovador_usuario_id'] ?? null);

        return $grupoId;
    }

    public function atualizar(int $id, array $dados): void
    {
        $propriedades = $this->propriedadesPremium($dados['propriedades'] ?? []);
        abort_if($propriedades->isEmpty(), 422, 'Selecione pelo menos uma fazenda Premium.');
        $this->validarAprovador($dados['aprovador_usuario_id'] ?? null, $propriedades->pluck('id')->all());

        DB::table('grupos_fazendas')
            ->where('id', $id)
            ->update([
                'nome' => trim($dados['nome']),
                'descricao' => trim($dados['descricao'] ?? '') ?: null,
                'aprovador_usuario_id' => ($dados['aprovador_usuario_id'] ?? null) ?: null,
                'ativo' => (bool) ($dados['ativo'] ?? true),
            ]);

        $this->sincronizarPropriedades($id, $propriedades->pluck('id')->all());
        $this->vincularAprovador($id, $dados['aprovador_usuario_id'] ?? null);
    }

    public function desativar(int $id): void
    {
        DB::table('grupos_fazendas')->where('id', $id)->update(['ativo' => 0]);
    }

    private function propriedadesPremium(array $ids)
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        abort_if($ids->isEmpty(), 422, 'Selecione pelo menos uma fazenda Premium.');

        $propriedades = DB::table('propriedades')
            ->whereIn('id', $ids)
            ->where('ativo', 1)
            ->get(['id', 'nome', 'plano', 'ativo']);

        abort_if(
            $propriedades->count() !== $ids->count(),
            422,
            'Uma ou mais fazendas selecionadas nao estao ativas.'
        );

        $foraPremium = $propriedades->filter(fn ($propriedade) => ! $this->eligibility->for(
            (bool) $propriedade->ativo,
            $propriedade->plano,
        )['eligible_for_group']);
        abort_if(
            $foraPremium->isNotEmpty(),
            422,
            'Grupo de fazendas esta disponivel somente para plano Premium. Ajuste o plano de: '.$foraPremium->pluck('nome')->implode(', ').'.'
        );

        return $propriedades;
    }

    private function sincronizarPropriedades(int $grupoId, array $propriedadesIds): void
    {
        DB::table('grupo_fazenda_propriedades')->where('grupo_id', $grupoId)->delete();

        foreach ($propriedadesIds as $propriedadeId) {
            DB::table('grupo_fazenda_propriedades')->insertOrIgnore([
                'grupo_id' => $grupoId,
                'propriedade_id' => $propriedadeId,
            ]);
        }
    }

    private function vincularAprovador(int $grupoId, $aprovadorId): void
    {
        if (! $aprovadorId) {
            return;
        }

        DB::table('usuario_grupos_fazendas')->insertOrIgnore([
            'usuario_id' => (int) $aprovadorId,
            'grupo_id' => $grupoId,
        ]);
    }

    private function validarAprovador($aprovadorId, array $propriedadesIds): void
    {
        if (! $aprovadorId) {
            return;
        }

        $propriedadesIds = collect($propriedadesIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $placeholders = implode(',', array_fill(0, count($propriedadesIds), '?'));

        $vinculado = DB::selectOne(
            "
            SELECT 1
            FROM usuarios u
            JOIN (
                SELECT up.usuario_id, up.propriedade_id
                FROM usuario_propriedades up
                JOIN propriedades p ON p.id = up.propriedade_id AND p.ativo = 1
                UNION
                SELECT ugf.usuario_id, gfp.propriedade_id
                FROM usuario_grupos_fazendas ugf
                JOIN grupos_fazendas gf ON gf.id = ugf.grupo_id AND gf.ativo = 1
                JOIN grupo_fazenda_propriedades gfp ON gfp.grupo_id = gf.id
                JOIN propriedades p ON p.id = gfp.propriedade_id AND p.ativo = 1
            ) acessos ON acessos.usuario_id = u.id
            WHERE u.id = ?
              AND u.ativo = 1
              AND u.perfil IN ('administrador','gestor_financeiro','gestor_propriedade','gestao','financeiro')
              AND acessos.propriedade_id IN ($placeholders)
            LIMIT 1
            ",
            array_merge([(int) $aprovadorId], $propriedadesIds)
        );

        abort_unless($vinculado, 422, 'O aprovador precisa ser usuario vinculado a uma das fazendas do grupo.');
    }
}

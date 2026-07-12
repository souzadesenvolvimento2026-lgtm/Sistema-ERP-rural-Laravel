<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestingBaselineSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('usuarios')->updateOrInsert(
            ['email' => 'teste.ci@farmfort.local'],
            [
                'nome' => 'Usuario de Testes',
                'senha' => password_hash('senha-testes', PASSWORD_DEFAULT),
                'perfil' => 'gestor_propriedade',
                'ativo' => 1,
            ]
        );

        DB::table('propriedades')->updateOrInsert(
            ['nome' => 'Fazenda teste'],
            [
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'plano' => 'premium',
                'ativo' => 1,
                'cotacao_soja_auto' => 0,
            ]
        );

        $usuarioId = (int) DB::table('usuarios')->where('email', 'teste.ci@farmfort.local')->value('id');
        $propriedadeId = (int) DB::table('propriedades')->where('nome', 'Fazenda teste')->value('id');

        DB::table('categorias')->updateOrInsert(
            ['nome' => 'Pedidos Fiscais'],
            [
                'tipo' => 'outros',
                'cor' => '#0f8d4d',
                'ativo' => 1,
            ]
        );

        DB::table('usuario_propriedades')->updateOrInsert([
            'usuario_id' => $usuarioId,
            'propriedade_id' => $propriedadeId,
        ]);

        DB::table('talhoes')->updateOrInsert(
            [
                'propriedade_id' => $propriedadeId,
                'nome' => 'Talhao de testes',
            ],
            [
                'area' => 100,
                'area_bruta' => 100,
                'area_excluida_ha' => 0,
                'latitude' => -17.7923,
                'longitude' => -50.9192,
                'geometria_tipo' => 'polygon',
                'coordenadas_json' => json_encode([
                    ['lat' => -17.7970, 'lng' => -50.9240],
                    ['lat' => -17.7970, 'lng' => -50.9140],
                    ['lat' => -17.7870, 'lng' => -50.9140],
                    ['lat' => -17.7870, 'lng' => -50.9240],
                ]),
                'ativo' => 1,
            ]
        );
    }
}

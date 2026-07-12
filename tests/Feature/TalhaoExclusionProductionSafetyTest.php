<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TalhaoExclusionProductionSafetyTest extends TestCase
{
    private int $usuarioId;

    private int $propriedadeId;

    private int $talhaoId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->usuarioId = (int) DB::table('usuarios')->insertGetId([
            'nome' => 'Usuario Exclusao CI',
            'email' => 'exclusao-ci-'.uniqid().'@farmfort.local',
            'senha' => password_hash('senha-exclusao', PASSWORD_DEFAULT),
            'perfil' => 'gestor_propriedade',
            'ativo' => 1,
        ]);
        $this->propriedadeId = $this->criarPropriedade('Principal');
        $this->talhaoId = $this->criarTalhao($this->propriedadeId, 'Talhao exclusao CI');
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_field_map_opens(): void
    {
        $this->withSession($this->sessao())
            ->get('/talhoes/mapa')
            ->assertOk()
            ->assertViewIs('talhoes.mapa');
    }

    public function test_exclusion_is_created_inside_field_polygon(): void
    {
        $this->salvarExclusaoInterna()->assertSessionHas('success');

        $talhao = DB::table('talhoes')->where('id', $this->talhaoId)->first();
        $this->assertNotEmpty($talhao->exclusoes_json);
        $this->assertGreaterThan(0, (float) $talhao->area_excluida_ha);
    }

    public function test_exclusion_outside_field_polygon_is_rejected(): void
    {
        $fora = json_encode([
            ['lat' => -15.7500, 'lng' => -47.9500],
            ['lat' => -15.7500, 'lng' => -47.9450],
            ['lat' => -15.7450, 'lng' => -47.9450],
            ['lat' => -15.7450, 'lng' => -47.9500],
        ]);

        $this->withSession($this->sessao())
            ->post('/talhoes/'.$this->talhaoId.'/mapa/exclusoes', ['exclusao_json' => $fora])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors();

        $this->assertNull(DB::table('talhoes')->where('id', $this->talhaoId)->value('exclusoes_json'));
    }

    public function test_exclusions_can_be_cleared(): void
    {
        $this->salvarExclusaoInterna();

        $this->withSession($this->sessao())
            ->delete('/talhoes/'.$this->talhaoId.'/mapa/exclusoes')
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHas('success');

        $talhao = DB::table('talhoes')->where('id', $this->talhaoId)->first();
        $this->assertNull($talhao->exclusoes_json);
        $this->assertSame(0.0, (float) $talhao->area_excluida_ha);
        $this->assertEqualsWithDelta((float) $talhao->area_bruta, (float) $talhao->area, 0.01);
    }

    public function test_gross_excluded_and_net_areas_are_recalculated(): void
    {
        $this->salvarExclusaoInterna();
        $talhao = DB::table('talhoes')->where('id', $this->talhaoId)->first();

        $bruta = (float) $talhao->area_bruta;
        $excluida = (float) $talhao->area_excluida_ha;
        $liquida = (float) $talhao->area;

        $this->assertGreaterThan(0, $bruta);
        $this->assertGreaterThan(0, $excluida);
        $this->assertLessThan($bruta, $liquida);
        $this->assertEqualsWithDelta($bruta - $excluida, $liquida, 0.02);
    }

    public function test_missing_or_other_property_field_cannot_be_changed(): void
    {
        $outraPropriedadeId = $this->criarPropriedade('Externa');
        $talhaoExternoId = $this->criarTalhao($outraPropriedadeId, 'Talhao externo CI');

        $this->withSession($this->sessao())
            ->post('/talhoes/999999999/mapa/exclusoes', ['exclusao_json' => $this->exclusaoInterna()])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors();

        $this->withSession($this->sessao())
            ->post('/talhoes/'.$talhaoExternoId.'/mapa/exclusoes', ['exclusao_json' => $this->exclusaoInterna()])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors();

        $this->assertNull(DB::table('talhoes')->where('id', $talhaoExternoId)->value('exclusoes_json'));
    }

    public function test_exclusion_endpoint_rejects_get_method(): void
    {
        $url = '/talhoes/'.$this->talhaoId.'/mapa/exclusoes';

        $this->withSession($this->sessao())->get($url)->assertStatus(405);
        $this->withSession($this->sessao())->post($url, ['exclusao_json' => $this->exclusaoInterna()])->assertRedirect('/talhoes/mapa');
        $this->withSession($this->sessao())->delete($url)->assertRedirect('/talhoes/mapa');
    }

    private function salvarExclusaoInterna()
    {
        return $this->withSession($this->sessao())
            ->post('/talhoes/'.$this->talhaoId.'/mapa/exclusoes', [
                'exclusao_json' => $this->exclusaoInterna(),
            ])
            ->assertRedirect('/talhoes/mapa');
    }

    private function exclusaoInterna(): string
    {
        return (string) json_encode([
            ['lat' => -15.7180, 'lng' => -47.9180],
            ['lat' => -15.7180, 'lng' => -47.9140],
            ['lat' => -15.7140, 'lng' => -47.9140],
            ['lat' => -15.7140, 'lng' => -47.9180],
        ]);
    }

    private function criarPropriedade(string $sufixo): int
    {
        return (int) DB::table('propriedades')->insertGetId([
            'nome' => 'Fazenda Exclusao CI '.$sufixo.' '.uniqid(),
            'municipio' => 'Rio Verde',
            'estado' => 'GO',
            'plano' => 'premium',
            'ativo' => 1,
            'cotacao_soja_auto' => 0,
        ]);
    }

    private function criarTalhao(int $propriedadeId, string $nome): int
    {
        $pontos = json_encode([
            ['lat' => -15.7200, 'lng' => -47.9200],
            ['lat' => -15.7200, 'lng' => -47.9000],
            ['lat' => -15.7000, 'lng' => -47.9000],
            ['lat' => -15.7000, 'lng' => -47.9200],
        ]);

        return (int) DB::table('talhoes')->insertGetId([
            'propriedade_id' => $propriedadeId,
            'nome' => $nome,
            'area' => 480,
            'area_bruta' => 480,
            'area_excluida_ha' => 0,
            'latitude' => -15.71,
            'longitude' => -47.91,
            'geometria_tipo' => 'polygon',
            'coordenadas_json' => $pontos,
            'ativo' => 1,
        ]);
    }

    private function sessao(): array
    {
        return [
            'usuario_id' => $this->usuarioId,
            'usuario_nome' => 'Usuario Exclusao CI',
            'perfil' => 'gestor_propriedade',
            'propriedade_id' => $this->propriedadeId,
        ];
    }
}

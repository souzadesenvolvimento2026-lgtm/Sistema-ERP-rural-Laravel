<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TalhaoExcludedAreaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_field_map_opens_for_the_current_property(): void
    {
        $propertyId = $this->propertyId();

        $this->withSession($this->sessionData($propertyId))
            ->get('/talhoes/mapa')
            ->assertOk()
            ->assertSee('Mapa dos Talhões');
    }

    public function test_map_exposes_crop_aware_capabilities_for_each_field(): void
    {
        $propertyId = $this->propertyId();
        $planningTalhaoId = $this->createTalhao($propertyId);
        $ongoingTalhaoId = $this->createTalhao($propertyId);
        $harvestedTalhaoId = $this->createTalhao($propertyId);
        $closedTalhaoId = $this->createTalhao($propertyId);
        $freeTalhaoId = $this->createTalhao($propertyId);
        $this->seedExistingExclusion($planningTalhaoId);
        $this->seedExistingExclusion($closedTalhaoId);

        $planningName = $this->attachCrop($propertyId, $planningTalhaoId, 'planejamento');
        $ongoingName = $this->attachCrop($propertyId, $ongoingTalhaoId, 'em_andamento');
        $this->attachCrop($propertyId, $harvestedTalhaoId, 'colhida');
        $this->attachCrop($propertyId, $closedTalhaoId, 'encerrada');

        $response = $this->withSession($this->sessionData($propertyId))
            ->get('/talhoes/mapa')
            ->assertOk();
        $talhoes = collect($response->viewData('talhoes'))->keyBy('id');

        $planning = $talhoes->get($planningTalhaoId);
        $this->assertFalse($planning['can_edit_geometry']);
        $this->assertFalse($planning['can_add_exclusion']);
        $this->assertFalse($planning['can_clear_exclusions']);
        $this->assertStringContainsString($planningName, (string) $planning['block_reason']);

        $ongoing = $talhoes->get($ongoingTalhaoId);
        $this->assertFalse($ongoing['can_edit_geometry']);
        $this->assertFalse($ongoing['can_add_exclusion']);
        $this->assertFalse($ongoing['can_clear_exclusions']);
        $this->assertStringContainsString($ongoingName, (string) $ongoing['block_reason']);

        foreach ([$harvestedTalhaoId, $closedTalhaoId, $freeTalhaoId] as $talhaoId) {
            $capabilities = $talhoes->get($talhaoId);
            $this->assertTrue($capabilities['can_edit_geometry']);
            $this->assertTrue($capabilities['can_add_exclusion']);
            $this->assertNull($capabilities['block_reason']);
        }

        $this->assertFalse($talhoes->get($harvestedTalhaoId)['can_clear_exclusions']);
        $this->assertTrue($talhoes->get($closedTalhaoId)['can_clear_exclusions']);
        $this->assertFalse($talhoes->get($freeTalhaoId)['can_clear_exclusions']);
    }

    public function test_planning_crop_blocks_forged_map_mutations(): void
    {
        $this->assertCropBlocksForgedMapMutations('planejamento');
    }

    public function test_ongoing_crop_blocks_forged_map_mutations(): void
    {
        $this->assertCropBlocksForgedMapMutations('em_andamento');
    }

    public function test_inactive_field_blocks_polygon_and_exclusion_mutations(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);
        $this->seedExistingExclusion($talhaoId);
        DB::table('talhoes')->where('id', $talhaoId)->update(['ativo' => 0]);
        $session = $this->sessionData($propertyId);
        $before = DB::table('talhoes')->where('id', $talhaoId)->first();
        $blockedMessage = 'Talhão inativo.';

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->post('/talhoes/mapa', [
                'talhao_id' => $talhaoId,
                'nome' => 'Tentativa em talhão inativo',
                'descricao' => 'Não deve persistir',
                'coordenadas_json' => json_encode($this->outerPolygon()),
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['coordenadas_json' => $blockedMessage]);

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
                'exclusao_json' => json_encode($this->insideExclusion()),
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['exclusao_json' => $blockedMessage]);

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->delete('/talhoes/'.$talhaoId.'/mapa/exclusoes')
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['exclusao_json' => $blockedMessage]);

        $after = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertSame($before->nome, $after->nome);
        $this->assertSame($before->coordenadas_json, $after->coordenadas_json);
        $this->assertSame($before->exclusoes_json, $after->exclusoes_json);
        $this->assertSame(0, DB::table('logs_auditoria')
            ->where('tabela', 'talhoes')
            ->where('registro_id', $talhaoId)
            ->whereIn('acao', [
                'salvar_poligono_talhao',
                'criar_exclusao_talhao',
                'limpar_exclusoes_talhao',
            ])
            ->count());
    }

    public function test_map_data_update_preserves_polygon_calculated_areas(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);
        $this->seedExistingExclusion($talhaoId);
        $this->attachCrop($propertyId, $talhaoId, 'em_andamento');
        $before = DB::table('talhoes')->where('id', $talhaoId)->first();

        $this->withSession($this->sessionData($propertyId))
            ->from('/talhoes/mapa')
            ->post('/talhoes/'.$talhaoId.'/mapa/dados', [
                'nome' => 'Talhão com metadados atualizados',
                'area' => '9999,99',
                'descricao' => 'Somente metadados foram alterados',
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $after = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertSame('Talhão com metadados atualizados', $after->nome);
        $this->assertSame('Somente metadados foram alterados', $after->descricao);
        $this->assertSame($before->coordenadas_json, $after->coordenadas_json);
        $this->assertSame($before->exclusoes_json, $after->exclusoes_json);
        $this->assertEqualsWithDelta((float) $before->area, (float) $after->area, 0.001);
        $this->assertEqualsWithDelta((float) $before->area_bruta, (float) $after->area_bruta, 0.001);
        $this->assertEqualsWithDelta((float) $before->area_excluida_ha, (float) $after->area_excluida_ha, 0.001);
        $this->assertDatabaseHas('logs_auditoria', [
            'acao' => 'editar_talhao_mapa',
            'tabela' => 'talhoes',
            'registro_id' => $talhaoId,
            'propriedade_id' => $propertyId,
        ]);
    }

    public function test_active_crop_blocks_manual_area_change_for_a_field_without_polygon(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhaoWithoutPolygon($propertyId);
        $cropName = $this->attachCrop($propertyId, $talhaoId, 'em_andamento');
        $blockedMessage = $this->blockedMapMutationMessage($cropName, 'em_andamento');
        $before = DB::table('talhoes')->where('id', $talhaoId)->first();

        $this->withSession($this->sessionData($propertyId))
            ->from('/talhoes/mapa')
            ->post('/talhoes/'.$talhaoId.'/mapa/dados', [
                'nome' => 'Tentativa de área manual bloqueada',
                'area' => '40,50',
                'descricao' => 'Não deve persistir',
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['area' => $blockedMessage]);

        $after = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertSame($before->nome, $after->nome);
        $this->assertSame($before->descricao, $after->descricao);
        $this->assertEqualsWithDelta((float) $before->area, (float) $after->area, 0.001);
        $this->assertEqualsWithDelta((float) $before->area_bruta, (float) $after->area_bruta, 0.001);
        $this->assertEqualsWithDelta((float) $before->area_excluida_ha, (float) $after->area_excluida_ha, 0.001);
        $this->assertDatabaseMissing('logs_auditoria', [
            'acao' => 'editar_talhao_mapa',
            'tabela' => 'talhoes',
            'registro_id' => $talhaoId,
            'propriedade_id' => $propertyId,
        ]);
    }

    public function test_an_exclusion_inside_the_polygon_updates_gross_excluded_and_net_areas(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);

        $this->withSession($this->sessionData($propertyId))
            ->from('/talhoes/mapa')
            ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
                'exclusao_json' => json_encode($this->insideExclusion()),
            ])
            ->assertRedirect('/talhoes/mapa');

        $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertNotNull($talhao);
        $this->assertEqualsWithDelta(476.10, (float) $talhao->area_bruta, 0.01);
        $this->assertEqualsWithDelta(19.04, (float) $talhao->area_excluida_ha, 0.01);
        $this->assertEqualsWithDelta(457.06, (float) $talhao->area, 0.01);
        $this->assertCount(1, json_decode((string) $talhao->exclusoes_json, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_an_exclusion_outside_the_field_polygon_is_rejected(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);

        $this->withSession($this->sessionData($propertyId))
            ->from('/talhoes/mapa')
            ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
                'exclusao_json' => json_encode([
                    ['lat' => -16.00, 'lng' => -48.00],
                    ['lat' => -16.00, 'lng' => -47.99],
                    ['lat' => -15.99, 'lng' => -47.99],
                ]),
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors('exclusao_json');

        $this->assertNull(DB::table('talhoes')->where('id', $talhaoId)->value('exclusoes_json'));
    }

    public function test_exclusions_can_be_cleared_and_the_gross_area_is_restored(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);

        $this->withSession($this->sessionData($propertyId))
            ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
                'exclusao_json' => json_encode($this->insideExclusion()),
            ])
            ->assertRedirect('/talhoes/mapa');

        $this->withSession($this->sessionData($propertyId))
            ->delete('/talhoes/'.$talhaoId.'/mapa/exclusoes')
            ->assertRedirect('/talhoes/mapa');

        $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertNull($talhao->exclusoes_json);
        $this->assertSame(0.0, (float) $talhao->area_excluida_ha);
        $this->assertEqualsWithDelta(476.10, (float) $talhao->area_bruta, 0.01);
        $this->assertEqualsWithDelta(476.10, (float) $talhao->area, 0.01);
    }

    public function test_saving_the_same_exclusion_twice_is_idempotent(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);
        $session = $this->sessionData($propertyId);
        $payload = ['exclusao_json' => json_encode($this->insideExclusion())];

        $this->withSession($session)
            ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', $payload)
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();
        $this->withSession($session)
            ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', $payload)
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertCount(1, json_decode((string) $talhao->exclusoes_json, true, flags: JSON_THROW_ON_ERROR));
        $this->assertEqualsWithDelta(19.04, (float) $talhao->area_excluida_ha, 0.01);
        $this->assertSame(1, DB::table('logs_auditoria')
            ->where('tabela', 'talhoes')
            ->where('registro_id', $talhaoId)
            ->where('acao', 'criar_exclusao_talhao')
            ->count());
    }

    public function test_redrawing_a_field_preserves_and_recalculates_compatible_exclusions(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);
        $session = $this->sessionData($propertyId);

        $this->withSession($session)->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
            'exclusao_json' => json_encode($this->insideExclusion()),
        ])->assertRedirect('/talhoes/mapa');

        $expandedPolygon = [
            ['lat' => -15.7220, 'lng' => -47.9220],
            ['lat' => -15.7220, 'lng' => -47.8980],
            ['lat' => -15.6980, 'lng' => -47.8980],
            ['lat' => -15.6980, 'lng' => -47.9220],
        ];

        $this->withSession($session)
            ->post('/talhoes/mapa', [
                'talhao_id' => $talhaoId,
                'nome' => 'Talhão redesenhado',
                'descricao' => 'Polígono ampliado',
                'coordenadas_json' => json_encode($expandedPolygon),
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertCount(1, json_decode((string) $talhao->exclusoes_json, true, flags: JSON_THROW_ON_ERROR));
        $this->assertGreaterThan(0.0, (float) $talhao->area_excluida_ha);
        $this->assertEqualsWithDelta(
            (float) $talhao->area_bruta - (float) $talhao->area_excluida_ha,
            (float) $talhao->area,
            0.01,
        );
    }

    public function test_composite_polygon_can_be_redrawn_from_the_map_editor(): void
    {
        $propertyId = $this->propertyId();
        $session = $this->sessionData($propertyId);
        $geometrias = [
            [
                'points' => $this->outerPolygon(),
                'exclusions' => [],
            ],
            [
                'points' => [
                    ['lat' => -15.7200, 'lng' => -47.8950],
                    ['lat' => -15.7200, 'lng' => -47.8750],
                    ['lat' => -15.7000, 'lng' => -47.8750],
                    ['lat' => -15.7000, 'lng' => -47.8950],
                ],
                'exclusions' => [],
            ],
        ];

        DB::table('talhoes')->insert([
            'propriedade_id' => $propertyId,
            'nome' => 'Talhao composto teste',
            'area' => 960,
            'area_bruta' => 960,
            'area_excluida_ha' => 0,
            'latitude' => -15.71,
            'longitude' => -47.90,
            'geometria_tipo' => 'polygon',
            'coordenadas_json' => json_encode(['type' => 'MultiPolygon', 'geometries' => $geometrias]),
            'ativo' => 1,
        ]);
        $talhaoId = (int) DB::getPdo()->lastInsertId();

        $geometrias[1]['points'][1]['lng'] = -47.8700;
        $geometrias[1]['points'][2]['lng'] = -47.8700;

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->post('/talhoes/mapa', [
                'talhao_id' => $talhaoId,
                'nome' => 'Talhao composto editado',
                'descricao' => 'Editado como multipoligono no mapa',
                'coordenadas_json' => json_encode(['type' => 'MultiPolygon', 'geometries' => $geometrias]),
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
        $decoded = json_decode((string) $talhao->coordenadas_json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('MultiPolygon', $decoded['type']);
        $this->assertCount(2, $decoded['geometries']);
        $this->assertNull($talhao->exclusoes_json);
        $this->assertGreaterThan(0.0, (float) $talhao->area_bruta);
        $this->assertGreaterThan(0.0, (float) $talhao->area);
        $this->assertSame('Talhao composto editado', $talhao->nome);
    }

    public function test_overlapping_exclusions_are_rejected_without_changing_the_saved_area(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);
        $session = $this->sessionData($propertyId);

        $this->withSession($session)->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
            'exclusao_json' => json_encode($this->insideExclusion()),
        ])->assertRedirect('/talhoes/mapa');

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
                'exclusao_json' => json_encode([
                    ['lat' => -15.7160, 'lng' => -47.9160],
                    ['lat' => -15.7160, 'lng' => -47.9120],
                    ['lat' => -15.7120, 'lng' => -47.9120],
                    ['lat' => -15.7120, 'lng' => -47.9160],
                ]),
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors('exclusao_json');

        $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertCount(1, json_decode((string) $talhao->exclusoes_json, true, flags: JSON_THROW_ON_ERROR));
        $this->assertEqualsWithDelta(19.04, (float) $talhao->area_excluida_ha, 0.01);
    }

    public function test_missing_or_foreign_property_fields_are_not_accessible(): void
    {
        $propertyId = $this->propertyId();
        DB::table('propriedades')->insert([
            'nome' => 'Propriedade externa exclusão '.uniqid(),
            'plano' => 'premium',
            'ativo' => 1,
        ]);
        $foreignPropertyId = (int) DB::getPdo()->lastInsertId();
        $foreignTalhaoId = $this->createTalhao($foreignPropertyId);

        $payload = ['exclusao_json' => json_encode($this->insideExclusion())];

        $this->withSession($this->sessionData($propertyId))
            ->post('/talhoes/999999999/mapa/exclusoes', $payload)
            ->assertNotFound();

        $this->withSession($this->sessionData($propertyId))
            ->post('/talhoes/'.$foreignTalhaoId.'/mapa/exclusoes', $payload)
            ->assertNotFound();
    }

    public function test_exclusion_routes_reject_the_get_method(): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);

        $this->withSession($this->sessionData($propertyId))
            ->get('/talhoes/'.$talhaoId.'/mapa/exclusoes')
            ->assertStatus(405);
    }

    public function test_map_region_prefers_property_city_over_quote_region(): void
    {
        $propertyId = $this->propertyId();
        DB::table('propriedades')->where('id', $propertyId)->update([
            'municipio' => 'São João da Paraúna',
            'estado' => 'GO',
            'regiao_cotacao' => 'Rio Verde/GO (Comigo)',
        ]);
        $this->createTalhao($propertyId);

        $response = $this->withSession($this->sessionData($propertyId))
            ->get('/talhoes/mapa')
            ->assertOk();

        $cards = $response->viewData('mapCards');
        $this->assertSame('São João da Paraúna/GO', $cards['regiao']);
        $this->assertNotSame('Rio Verde/GO (Comigo)', $cards['regiao']);
    }

    private function createTalhao(int $propertyId): int
    {
        DB::table('talhoes')->insert([
            'propriedade_id' => $propertyId,
            'nome' => 'Talhão exclusão '.uniqid(),
            'area' => 480,
            'area_bruta' => 480,
            'area_excluida_ha' => 0,
            'latitude' => -15.71,
            'longitude' => -47.91,
            'geometria_tipo' => 'polygon',
            'coordenadas_json' => json_encode($this->outerPolygon()),
            'ativo' => 1,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function createTalhaoWithoutPolygon(int $propertyId): int
    {
        DB::table('talhoes')->insert([
            'propriedade_id' => $propertyId,
            'nome' => 'Talhão manual '.uniqid(),
            'area' => 25,
            'area_bruta' => 25,
            'area_excluida_ha' => 0,
            'descricao' => 'Cadastro sem polígono',
            'geometria_tipo' => null,
            'coordenadas_json' => null,
            'ativo' => 1,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function assertCropBlocksForgedMapMutations(string $status): void
    {
        $propertyId = $this->propertyId();
        $talhaoId = $this->createTalhao($propertyId);
        $this->seedExistingExclusion($talhaoId);
        $this->seedExistingPivot($talhaoId);
        $cropName = $this->attachCrop($propertyId, $talhaoId, $status);
        $blockedMessage = $this->blockedMapMutationMessage($cropName, $status);
        $session = $this->sessionData($propertyId);
        $before = DB::table('talhoes')->where('id', $talhaoId)->first();

        $expandedPolygon = [
            ['lat' => -15.7220, 'lng' => -47.9220],
            ['lat' => -15.7220, 'lng' => -47.8980],
            ['lat' => -15.6980, 'lng' => -47.8980],
            ['lat' => -15.6980, 'lng' => -47.9220],
        ];

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->post('/talhoes/mapa', [
                'talhao_id' => $talhaoId,
                'nome' => 'Tentativa bloqueada '.$status,
                'descricao' => 'Não deve persistir',
                'coordenadas_json' => json_encode($expandedPolygon),
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['coordenadas_json' => $blockedMessage]);

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
                'exclusao_json' => json_encode($this->insideExclusion()),
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['exclusao_json' => $blockedMessage]);

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->delete('/talhoes/'.$talhaoId.'/mapa/exclusoes')
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['exclusao_json' => $blockedMessage]);

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->post('/talhoes/'.$talhaoId.'/mapa/pivo', [
                'pivo_lat' => '-15.705',
                'pivo_lng' => '-47.905',
                'pivo_raio_m' => '300',
            ])
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['pivo' => $blockedMessage]);

        $this->withSession($session)
            ->from('/talhoes/mapa')
            ->delete('/talhoes/'.$talhaoId.'/mapa/pivo')
            ->assertRedirect('/talhoes/mapa')
            ->assertSessionHasErrors(['pivo' => $blockedMessage]);

        $after = DB::table('talhoes')->where('id', $talhaoId)->first();
        $this->assertSame($before->nome, $after->nome);
        $this->assertSame($before->descricao, $after->descricao);
        $this->assertSame($before->coordenadas_json, $after->coordenadas_json);
        $this->assertSame($before->exclusoes_json, $after->exclusoes_json);
        $this->assertEqualsWithDelta((float) $before->area_bruta, (float) $after->area_bruta, 0.001);
        $this->assertEqualsWithDelta((float) $before->area_excluida_ha, (float) $after->area_excluida_ha, 0.001);
        $this->assertEqualsWithDelta((float) $before->area, (float) $after->area, 0.001);
        $this->assertSame((int) $before->pivo_ativo, (int) $after->pivo_ativo);
        $this->assertEqualsWithDelta((float) $before->pivo_lat, (float) $after->pivo_lat, 0.000001);
        $this->assertEqualsWithDelta((float) $before->pivo_lng, (float) $after->pivo_lng, 0.000001);
        $this->assertEqualsWithDelta((float) $before->pivo_raio_m, (float) $after->pivo_raio_m, 0.001);
        $this->assertEqualsWithDelta((float) $before->pivo_area_ha, (float) $after->pivo_area_ha, 0.001);
        $this->assertSame(0, DB::table('logs_auditoria')
            ->where('tabela', 'talhoes')
            ->where('registro_id', $talhaoId)
            ->whereIn('acao', [
                'salvar_poligono_talhao',
                'criar_exclusao_talhao',
                'limpar_exclusoes_talhao',
                'salvar_pivo_talhao',
                'remover_pivo_talhao',
            ])
            ->count());
    }

    private function attachCrop(int $propertyId, int $talhaoId, string $status): string
    {
        $cropName = 'Safra '.$status.' '.uniqid();
        DB::table('safras')->insert([
            'propriedade_id' => $propertyId,
            'safra_referencia' => 'primeira',
            'descricao' => $cropName,
            'data_inicio' => '2026-07-01',
            'status' => $status,
        ]);
        $safraId = (int) DB::getPdo()->lastInsertId();
        DB::table('safra_talhoes')->insert([
            'safra_id' => $safraId,
            'talhao_id' => $talhaoId,
            'propriedade_id' => $propertyId,
        ]);

        return $cropName;
    }

    private function blockedMapMutationMessage(string $cropName, string $status): string
    {
        $statusLabel = $status === 'planejamento' ? 'em planejamento' : 'em andamento';

        return 'Este talhão não pode ser editado por estar vinculado à safra '.$cropName.' '.$statusLabel.'.';
    }

    private function seedExistingExclusion(int $talhaoId): void
    {
        DB::table('talhoes')->where('id', $talhaoId)->update([
            'exclusoes_json' => json_encode([$this->insideExclusion()]),
            'area_bruta' => 476.10,
            'area_excluida_ha' => 19.04,
            'area' => 457.06,
        ]);
    }

    private function seedExistingPivot(int $talhaoId): void
    {
        DB::table('talhoes')->where('id', $talhaoId)->update([
            'pivo_ativo' => 1,
            'pivo_lat' => -15.71,
            'pivo_lng' => -47.91,
            'pivo_raio_m' => 250,
            'pivo_area_ha' => 19.63,
        ]);
    }

    private function outerPolygon(): array
    {
        return [
            ['lat' => -15.7200, 'lng' => -47.9200],
            ['lat' => -15.7200, 'lng' => -47.9000],
            ['lat' => -15.7000, 'lng' => -47.9000],
            ['lat' => -15.7000, 'lng' => -47.9200],
        ];
    }

    private function insideExclusion(): array
    {
        return [
            ['lat' => -15.7180, 'lng' => -47.9180],
            ['lat' => -15.7180, 'lng' => -47.9140],
            ['lat' => -15.7140, 'lng' => -47.9140],
            ['lat' => -15.7140, 'lng' => -47.9180],
        ];
    }

    private function sessionData(int $propertyId): array
    {
        return [
            'usuario_id' => $this->userId($propertyId),
            'usuario_nome' => 'Teste Talhão',
            'perfil' => 'gestor_propriedade',
            'propriedade_id' => $propertyId,
        ];
    }

    private function propertyId(): int
    {
        DB::table('propriedades')->insert([
            'nome' => 'Propriedade exclusão '.uniqid(),
            'plano' => 'premium',
            'ativo' => 1,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function userId(int $propertyId): int
    {
        DB::table('usuarios')->insert([
            'nome' => 'Usuário exclusão '.uniqid(),
            'email' => 'exclusao-session-'.uniqid().'@teste.local',
            'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
            'perfil' => 'gestor_propriedade',
            'ativo' => 1,
        ]);

        $userId = (int) DB::getPdo()->lastInsertId();
        DB::table('usuario_propriedades')->insert([
            'usuario_id' => $userId,
            'propriedade_id' => $propertyId,
        ]);

        return $userId;
    }
}

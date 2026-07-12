<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardBusinessRulesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_redirects_a_property_user_to_the_dashboard(): void
    {
        $propertyId = $this->propertyId();
        $email = 'dashboard-login-'.uniqid().'@teste.local';
        DB::table('usuarios')->insert([
            'nome' => 'Usuário Dashboard',
            'email' => $email,
            'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
            'perfil' => 'gestor_propriedade',
            'ativo' => 1,
        ]);
        $userId = (int) DB::getPdo()->lastInsertId();
        DB::table('usuario_propriedades')->insert([
            'usuario_id' => $userId,
            'propriedade_id' => $propertyId,
        ]);

        $this->post('/login', ['email' => $email, 'senha' => 'senha-segura'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('usuario_id', $userId)
            ->assertSessionHas('perfil', 'gestor_propriedade')
            ->assertSessionHas('propriedade_id', $propertyId);
    }

    public function test_login_rejects_a_property_user_without_an_active_property_link(): void
    {
        $this->propertyId();
        $email = 'dashboard-sem-vinculo-'.uniqid().'@teste.local';
        DB::table('usuarios')->insert([
            'nome' => 'Usuário sem vínculo',
            'email' => $email,
            'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
            'perfil' => 'gestor_propriedade',
            'ativo' => 1,
        ]);

        $this->from('/login')
            ->post('/login', ['email' => $email, 'senha' => 'senha-segura'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Usuário sem propriedade ativa vinculada. Contate o administrador.',
            ])
            ->assertSessionMissing('usuario_id');
    }

    public function test_an_authenticated_session_is_invalidated_when_property_access_is_revoked(): void
    {
        $propertyId = $this->propertyId();
        $session = $this->sessionData($propertyId);
        DB::table('usuario_propriedades')
            ->where('usuario_id', $session['usuario_id'])
            ->where('propriedade_id', $propertyId)
            ->delete();

        $this->withSession($session)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('usuario_id');
    }

    public function test_dashboard_loads_with_an_active_crop_season(): void
    {
        $propertyId = $this->propertyId();
        $safraId = $this->createActiveSafra($propertyId, 'Safra ativa dashboard');

        $this->withSession($this->sessionData($propertyId))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Safra ativa dashboard');

        $this->assertGreaterThan(0, $safraId);
    }

    public function test_dashboard_groups_named_null_and_blank_buyers_and_sums_their_revenues(): void
    {
        $propertyId = $this->propertyId();
        $safraId = $this->createActiveSafra($propertyId, 'Safra compradores dashboard');
        DB::table('safras')->insert([
            'propriedade_id' => $propertyId,
            'safra_referencia' => 'segunda',
            'descricao' => 'Safra fora do filtro dashboard',
            'data_inicio' => '2098-07-01',
            'data_fim' => '2099-06-30',
            'status' => 'encerrada',
        ]);
        $otherSafraId = (int) DB::getPdo()->lastInsertId();

        DB::table('receitas')->insert([
            $this->receita($propertyId, $safraId, 'Receita comprador 1', 'Cooperativa Teste', 100),
            $this->receita($propertyId, $safraId, 'Receita comprador 2', 'Cooperativa Teste', 250),
            $this->receita($propertyId, $safraId, 'Receita sem comprador', null, 40),
            $this->receita($propertyId, $safraId, 'Receita comprador vazio', '   ', 60),
            array_replace(
                $this->receita($propertyId, $safraId, 'Receita cancelada', 'Cooperativa Teste', 999),
                ['status' => 'cancelado'],
            ),
            $this->receita($propertyId, $otherSafraId, 'Receita de outra safra', 'Cooperativa Teste', 888),
        ]);

        $response = $this->withSession($this->sessionData($propertyId))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Cooperativa Teste')
            ->assertSee('R$ 350,00')
            ->assertSee('Sem comprador')
            ->assertSee('R$ 100,00');

        $response->assertViewHas('receitasPorComprador', function ($rows): bool {
            $totals = collect($rows)->mapWithKeys(
                fn ($row) => [(string) $row->nome => (float) $row->total],
            );

            return $totals->count() === 2
                && abs($totals->get('Cooperativa Teste', -1) - 350.0) < 0.001
                && abs($totals->get('Sem comprador', -1) - 100.0) < 0.001;
        });
    }

    public function test_dashboard_loads_when_the_active_crop_season_has_no_revenues(): void
    {
        $propertyId = $this->propertyId();
        $this->createActiveSafra($propertyId, 'Safra sem receitas dashboard');

        $this->withSession($this->sessionData($propertyId))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Sem receitas por comprador')
            ->assertSee('R$ 0,00');
    }

    public function test_dashboard_loads_when_there_is_no_active_crop_season(): void
    {
        $propertyId = $this->propertyId();
        $safraId = $this->createActiveSafra($propertyId, 'Safra encerrada dashboard');
        DB::table('safras')->where('id', $safraId)->update(['status' => 'encerrada']);

        $this->assertFalse(
            DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->whereIn('status', ['em_andamento', 'planejamento'])
                ->exists(),
        );

        $response = $this->withSession($this->sessionData($propertyId))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Safra encerrada dashboard');

        $response->assertViewHas(
            'safra',
            fn ($safra): bool => (int) ($safra->id ?? 0) === $safraId
                && (string) ($safra->status ?? '') === 'encerrada',
        );
    }

    public function test_dashboard_does_not_mix_unscoped_revenues_when_there_are_no_crop_seasons(): void
    {
        $propertyId = $this->propertyId();
        DB::table('receitas')->insert(
            $this->receita($propertyId, null, 'Receita sem safra', 'Comprador sem safra', 999),
        );

        $response = $this->withSession($this->sessionData($propertyId))->get('/dashboard');

        $response->assertOk()
            ->assertViewHas('safra', null)
            ->assertViewHas('totalReceitas', 0.0)
            ->assertViewHas('receitasPorComprador', fn ($rows): bool => collect($rows)->isEmpty());
    }

    private function createActiveSafra(int $propertyId, string $description): int
    {
        DB::table('safras')->where('propriedade_id', $propertyId)->update(['status' => 'encerrada']);
        DB::table('safras')->insert([
            'propriedade_id' => $propertyId,
            'safra_referencia' => 'primeira',
            'descricao' => $description,
            'data_inicio' => '2099-07-01',
            'data_fim' => '2100-06-30',
            'status' => 'em_andamento',
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function receita(int $propertyId, ?int $safraId, string $description, ?string $buyer, float $total): array
    {
        return [
            'propriedade_id' => $propertyId,
            'safra_id' => $safraId,
            'descricao' => $description,
            'comprador' => $buyer,
            'valor_total' => $total,
            'data_venda' => '2099-08-01',
            'status' => 'recebido',
            'status_aprovacao' => 'aprovada',
        ];
    }

    private function sessionData(int $propertyId): array
    {
        return [
            'usuario_id' => $this->userId($propertyId),
            'usuario_nome' => 'Teste Dashboard',
            'perfil' => 'gestor_propriedade',
            'propriedade_id' => $propertyId,
        ];
    }

    private function propertyId(): int
    {
        DB::table('propriedades')->insert([
            'nome' => 'Propriedade dashboard '.uniqid(),
            'plano' => 'premium',
            'ativo' => 1,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function userId(int $propertyId): int
    {
        DB::table('usuarios')->insert([
            'nome' => 'Usuário dashboard '.uniqid(),
            'email' => 'dashboard-session-'.uniqid().'@teste.local',
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

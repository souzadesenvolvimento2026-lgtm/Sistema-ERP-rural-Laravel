<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardProductionSafetyTest extends TestCase
{
    private int $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->usuarioId = (int) DB::table('usuarios')->insertGetId([
            'nome' => 'Usuario Dashboard CI',
            'email' => 'dashboard-ci-'.uniqid().'@farmfort.local',
            'senha' => password_hash('senha-dashboard', PASSWORD_DEFAULT),
            'perfil' => 'gestor_propriedade',
            'ativo' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_login_redirects_authenticated_property_manager_to_dashboard(): void
    {
        $propriedadeId = $this->criarPropriedade();
        DB::table('usuario_propriedades')->insert([
            'usuario_id' => $this->usuarioId,
            'propriedade_id' => $propriedadeId,
        ]);

        $email = (string) DB::table('usuarios')->where('id', $this->usuarioId)->value('email');

        $this->post('/login', ['email' => $email, 'senha' => 'senha-dashboard'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('usuario_id', $this->usuarioId);
    }

    public function test_dashboard_loads_for_an_authenticated_user(): void
    {
        $propriedadeId = $this->criarPropriedade();

        $this->withSession($this->sessao($propriedadeId))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard');
    }

    public function test_dashboard_lists_receipt_with_filled_buyer(): void
    {
        [$propriedadeId, $safraId] = $this->criarPropriedadeComSafra();
        $this->criarReceita($propriedadeId, $safraId, 'Comprador Cerrado', 125.50);

        $this->withSession($this->sessao($propriedadeId))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('receitasPorComprador', fn ($linhas) => $linhas->contains(fn ($linha) => $linha->nome === 'Comprador Cerrado' && (float) $linha->total === 125.50)
            );
    }

    public function test_dashboard_normalizes_null_buyer(): void
    {
        [$propriedadeId, $safraId] = $this->criarPropriedadeComSafra();
        $this->criarReceita($propriedadeId, $safraId, null, 40);

        $this->withSession($this->sessao($propriedadeId))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('receitasPorComprador', fn ($linhas) => $linhas->contains(fn ($linha) => $linha->nome === 'Sem comprador' && (float) $linha->total === 40.0)
            );
    }

    public function test_dashboard_normalizes_empty_buyer(): void
    {
        [$propriedadeId, $safraId] = $this->criarPropriedadeComSafra();
        $this->criarReceita($propriedadeId, $safraId, '   ', 35);

        $this->withSession($this->sessao($propriedadeId))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('receitasPorComprador', fn ($linhas) => $linhas->contains(fn ($linha) => $linha->nome === 'Sem comprador' && (float) $linha->total === 35.0)
            );
    }

    public function test_dashboard_groups_and_sums_receipts_by_buyer(): void
    {
        [$propriedadeId, $safraId] = $this->criarPropriedadeComSafra();
        $this->criarReceita($propriedadeId, $safraId, 'Cooperativa Teste', 100);
        $this->criarReceita($propriedadeId, $safraId, 'Cooperativa Teste', 75.25);

        $this->withSession($this->sessao($propriedadeId))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('receitasPorComprador', function ($linhas) {
                $cooperativa = $linhas->firstWhere('nome', 'Cooperativa Teste');

                return $cooperativa !== null && (float) $cooperativa->total === 175.25;
            });
    }

    public function test_dashboard_handles_database_without_receipts(): void
    {
        [$propriedadeId] = $this->criarPropriedadeComSafra();

        $this->withSession($this->sessao($propriedadeId))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('receitasPorComprador', fn ($linhas) => $linhas->isEmpty());
    }

    public function test_dashboard_exposes_active_harvest(): void
    {
        [$propriedadeId, $safraId] = $this->criarPropriedadeComSafra();

        $this->withSession($this->sessao($propriedadeId))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('safra', fn ($safra) => (int) $safra->id === $safraId);
    }

    public function test_dashboard_handles_property_without_harvest(): void
    {
        $propriedadeId = $this->criarPropriedade();

        $this->withSession($this->sessao($propriedadeId))
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('safra', null);
    }

    private function criarPropriedadeComSafra(): array
    {
        $propriedadeId = $this->criarPropriedade();
        $safraId = (int) DB::table('safras')->insertGetId([
            'propriedade_id' => $propriedadeId,
            'safra_referencia' => 'primeira',
            'descricao' => 'Safra CI '.uniqid(),
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
            'area_plantada' => 100,
            'status' => 'em_andamento',
        ]);

        return [$propriedadeId, $safraId];
    }

    private function criarPropriedade(): int
    {
        return (int) DB::table('propriedades')->insertGetId([
            'nome' => 'Fazenda Dashboard CI '.uniqid(),
            'municipio' => 'Rio Verde',
            'estado' => 'GO',
            'plano' => 'premium',
            'ativo' => 1,
            'cotacao_soja_auto' => 0,
        ]);
    }

    private function criarReceita(int $propriedadeId, int $safraId, ?string $comprador, float $valor): void
    {
        DB::table('receitas')->insert([
            'propriedade_id' => $propriedadeId,
            'safra_id' => $safraId,
            'descricao' => 'Receita de teste do dashboard',
            'comprador' => $comprador,
            'valor_total' => $valor,
            'data_venda' => '2026-07-10',
            'status' => 'recebido',
            'status_aprovacao' => 'aprovada',
            'usuario_id' => $this->usuarioId,
        ]);
    }

    private function sessao(int $propriedadeId): array
    {
        return [
            'usuario_id' => $this->usuarioId,
            'usuario_nome' => 'Usuario Dashboard CI',
            'perfil' => 'gestor_propriedade',
            'propriedade_id' => $propriedadeId,
        ];
    }
}

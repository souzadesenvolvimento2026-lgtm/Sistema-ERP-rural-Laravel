<?php

namespace Tests\Feature;

use App\Domain\Access\ProfileAccess;
use App\Services\AuthenticationService;
use App\Services\CotacaoSojaService;
use App\Services\NotaFiscalXmlService;
use App\Support\FarmContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** @var array<int, string> */
    private array $originalUserProfiles = [];

    /** @var array<string, array{usuario_id: int, propriedade_id: int}> */
    private array $temporaryPropertyLinks = [];

    protected function tearDown(): void
    {
        try {
            foreach ($this->temporaryPropertyLinks as $link) {
                DB::table('usuario_propriedades')
                    ->where('usuario_id', $link['usuario_id'])
                    ->where('propriedade_id', $link['propriedade_id'])
                    ->delete();
            }

            foreach ($this->originalUserProfiles as $userId => $profile) {
                DB::table('usuarios')->where('id', $userId)->update(['perfil' => $profile]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function loggedSession(
        ?int $propertyId = null,
        ?string $profile = null,
        ?int $userId = null,
    ): array {
        $user = $this->activeSessionUser($userId, $profile);
        $userId = (int) $user->id;
        $profile = (string) $user->perfil;
        $authentication = app(AuthenticationService::class);

        if ($propertyId !== null) {
            $this->authorizeSessionUserForProperty($userId, $profile, $propertyId);
        } else {
            $propertyId = $authentication->defaultPropertyId($userId, $profile);

            if ($propertyId === null && ! app(ProfileAccess::class)->isSystemAdministrator($profile)) {
                $propertyId = DB::table('propriedades')->where('ativo', 1)->orderBy('id')->value('id');
                $this->assertNotNull($propertyId, 'O fixture precisa de uma propriedade ativa para autenticar este perfil.');
                $propertyId = (int) $propertyId;
                $this->authorizeSessionUserForProperty($userId, $profile, $propertyId);
            }
        }

        $this->assertTrue(
            $authentication->canAccessProperty($userId, $profile, $propertyId),
            'O fixture de sessão deve representar um usuário ativo e autorizado à propriedade.',
        );

        return [
            'usuario_id' => $userId,
            'usuario_nome' => (string) $user->nome,
            'perfil' => $profile,
            'propriedade_id' => $propertyId,
        ];
    }

    private function activeSessionUser(?int $userId, ?string $profile): object
    {
        $query = DB::table('usuarios')->where('ativo', 1);

        if ($userId !== null) {
            $query->where('id', $userId);
        } elseif ($profile !== null) {
            $query->where('perfil', $profile);
        } else {
            $query->where('id', $this->userId());
        }

        $user = $query->orderBy('id')->first(['id', 'nome', 'perfil']);

        if (! $user && $profile !== null && $userId === null) {
            $userId = $this->propertyManagerUserId($propertyId);
            $user = DB::table('usuarios')->where('id', $userId)->first(['id', 'nome', 'perfil']);
            $this->assertNotNull($user, 'O fixture precisa de um usuário ativo.');

            $this->originalUserProfiles[$userId] ??= (string) $user->perfil;
            DB::table('usuarios')->where('id', $userId)->update(['perfil' => $profile]);
            $user->perfil = $profile;
        }

        $this->assertNotNull($user, 'O fixture precisa de um usuário ativo com o perfil solicitado.');
        $this->assertSame($profile ?? (string) $user->perfil, (string) $user->perfil);

        return $user;
    }

    private function authorizeSessionUserForProperty(int $userId, string $profile, int $propertyId): void
    {
        $authentication = app(AuthenticationService::class);

        if ($authentication->canAccessProperty($userId, $profile, $propertyId)) {
            return;
        }

        $this->assertDatabaseHas('propriedades', ['id' => $propertyId, 'ativo' => 1]);

        $link = ['usuario_id' => $userId, 'propriedade_id' => $propertyId];
        $key = $userId.':'.$propertyId;

        if (! DB::table('usuario_propriedades')->where($link)->exists()) {
            DB::table('usuario_propriedades')->insert($link);
            $this->temporaryPropertyLinks[$key] = $link;
        }

        $this->assertTrue($authentication->canAccessProperty($userId, $profile, $propertyId));
    }

    private function userId(): int
    {
        return (int) DB::table('usuarios as u')
            ->where('u.ativo', 1)
            ->where(function ($access) {
                $access->whereIn('u.perfil', ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'])
                    ->orWhereExists(function ($direct) {
                        $direct->selectRaw('1')
                            ->from('usuario_propriedades as up')
                            ->whereColumn('up.usuario_id', 'u.id');
                    })->orWhereExists(function ($group) {
                        $group->selectRaw('1')
                            ->from('usuario_grupos_fazendas as ugf')
                            ->join('grupos_fazendas as gf', 'gf.id', '=', 'ugf.grupo_id')
                            ->whereColumn('ugf.usuario_id', 'u.id')
                            ->where('gf.ativo', 1);
                    });
            })
            ->orderBy('u.id')
            ->value('u.id');
    }

    private function propertyManagerUserId(int $propertyId): int
    {
        $userId = (int) DB::table('usuarios as u')
            ->join('usuario_propriedades as up', 'up.usuario_id', '=', 'u.id')
            ->where('up.propriedade_id', $propertyId)
            ->where('u.perfil', 'gestor_propriedade')
            ->where('u.ativo', 1)
            ->orderBy('u.id')
            ->value('u.id');

        if ($userId > 0) {
            return $userId;
        }

        DB::table('usuarios')->insert([
            'nome' => 'Gestor propriedade teste',
            'email' => 'gestor-propriedade-'.uniqid().'@teste.local',
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

    private function simplePointShp(float $lng, float $lat): string
    {
        $record = pack('V', 1).pack('e', $lng).pack('e', $lat);
        $fileLengthWords = (int) ((100 + 8 + strlen($record)) / 2);

        $header = pack('N', 9994)
            .str_repeat(pack('N', 0), 5)
            .pack('N', $fileLengthWords)
            .pack('V', 1000)
            .pack('V', 1)
            .pack('e', $lng).pack('e', $lat).pack('e', $lng).pack('e', $lat)
            .str_repeat(pack('e', 0), 4);

        return $header.pack('N', 1).pack('N', (int) (strlen($record) / 2)).$record;
    }

    public function test_the_homepage_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_dashboard_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Dashboard')
            ->assertSee('/assets/img/farmfort-favicon.png', false);
    }

    public function test_legacy_php_pages_redirect_to_laravel_routes(): void
    {
        $this->get('/login.php')->assertRedirect('/login');
        $this->get('/index.php')->assertRedirect('/dashboard');

        $this->withSession($this->loggedSession())
            ->get('/pages/maquinas.php?mod=patrimonio')
            ->assertRedirect('/patrimonio');

        $this->withSession($this->loggedSession())
            ->get('/pages/pedidos_fiscais.php?mod=fiscal')
            ->assertRedirect('/compras/pedidos');

        $this->withSession($this->loggedSession())
            ->get('/pages/lancamentos_export.php?mod=financeiro&formato=pdf&filtro=despesas')
            ->assertRedirect('/financeiro/relatorio-lancamentos/exportar?formato=pdf&filtro=despesas');

        $this->withSession($this->loggedSession())
            ->get('/pages/livro_caixa.php?mod=financeiro&export=pdf&ano=2026')
            ->assertRedirect('/financeiro/livro-caixa/exportar?ano=2026&formato=pdf');

        $this->withSession($this->loggedSession())
            ->get('/logout.php')
            ->assertRedirect('/login');
    }

    public function test_main_menu_points_to_migrated_module_entries(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('/financeiro', false)
            ->assertSee('/fiscal', false)
            ->assertSee('/compras', false)
            ->assertSee('/patrimonio', false)
            ->assertSee('/produtos', false)
            ->assertDontSee('/modulos/financeiro', false)
            ->assertDontSee('/modulos/fiscal', false);
    }

    public function test_property_manager_sidebar_shows_users_menu_entry(): void
    {
        $this->withSession($this->loggedSession(profile: 'gestor_propriedade'))
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Dashboard')
            ->assertSee('Financeiro')
            ->assertSee('Fiscal')
            ->assertSee('Patrim')
            ->assertSee('Safras')
            ->assertSee('Talh')
            ->assertSee('Colheita')
            ->assertSee('Compras')
            ->assertSee('Estoque de produtos')
            ->assertSee('Estoque de produ')
            ->assertSee('Usu')
            ->assertSee('/usuarios', false);
    }

    public function test_system_unlock_requires_system_writer_profile(): void
    {
        $this->withSession($this->loggedSession(profile: 'visualizador'))
            ->post('/sistema/liberar-edicao', [
                'senha_confirmacao' => 'qualquer',
                'return_to' => '/dashboard',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');
    }

    public function test_system_unlock_stores_unlock_expiration_when_password_is_valid(): void
    {
        DB::beginTransaction();
        try {
            $userId = $this->userId();
            $propertyId = (int) DB::table('propriedades')->where('ativo', 1)->orderBy('id')->value('id');
            DB::table('usuarios')->where('id', $userId)->update([
                'senha' => password_hash('senha-teste', PASSWORD_DEFAULT),
                'perfil' => 'gerencia_sistema',
                'ativo' => 1,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gerencia_sistema', userId: $userId))
                ->post('/sistema/liberar-edicao', [
                    'senha_confirmacao' => 'senha-teste',
                    'return_to' => '/dashboard',
                ])
                ->assertRedirect('/dashboard')
                ->assertSessionHas('success')
                ->assertSessionHas('system_write_unlocked_until')
                ->assertSessionHas('system_write_unlocked_property_id', $propertyId);
        } finally {
            DB::rollBack();
        }
    }

    public function test_system_unlock_is_cleared_when_selected_property_changes(): void
    {
        DB::beginTransaction();

        try {
            $userId = $this->userId();
            DB::table('usuarios')->where('id', $userId)->update([
                'senha' => password_hash('senha-teste', PASSWORD_DEFAULT),
                'perfil' => 'gerencia_sistema',
                'ativo' => 1,
            ]);

            $firstPropertyId = (int) DB::table('propriedades')->where('ativo', 1)->orderBy('id')->value('id');
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda troca desbloqueio Laravel '.uniqid(),
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsável troca',
                'cnpj_cpf' => '12345678904',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $secondPropertyId = (int) DB::getPdo()->lastInsertId();

            $session = $this->loggedSession(
                propertyId: $firstPropertyId,
                profile: 'gerencia_sistema',
                userId: $userId,
            );

            $this->withSession($session)
                ->post('/sistema/liberar-edicao', [
                    'senha_confirmacao' => 'senha-teste',
                    'return_to' => '/dashboard',
                ])
                ->assertSessionHas('system_write_unlocked_property_id', $firstPropertyId);

            $this->withSession($session + [
                    'system_write_unlocked_until' => time() + 300,
                    'system_write_unlocked_property_id' => $firstPropertyId,
                ])
                ->post('/propriedades/selecionar', [
                    'propriedade_id' => $secondPropertyId,
                ])
                ->assertSessionMissing('system_write_unlocked_until')
                ->assertSessionMissing('system_write_unlocked_property_id');
        } finally {
            DB::rollBack();
        }
    }

    public function test_system_admin_property_update_requires_selected_property_unlock(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda bloqueio edicao Laravel',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel bloqueio',
                'cnpj_cpf' => '12345678905',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gerencia_sistema'))
                ->from('/propriedades/'.$propertyId.'/editar')
                ->put('/propriedades/'.$propertyId, [
                    'nome' => 'Fazenda bloqueio alterada',
                    'municipio' => 'Rio Verde',
                    'estado' => 'GO',
                    'area_total' => '100',
                    'responsavel' => 'Responsavel bloqueio',
                    'inscricao_estadual' => '',
                    'cnpj_cpf' => '12345678905',
                    'plano' => 'premium',
                    'pecuaria_ativa' => '0',
                    'latitude' => '',
                    'longitude' => '',
                    'regiao_cotacao' => '',
                ])
                ->assertRedirect('/propriedades/'.$propertyId.'/editar')
                ->assertSessionHasErrors();

            $this->assertDatabaseHas('propriedades', [
                'id' => $propertyId,
                'nome' => 'Fazenda bloqueio edicao Laravel',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_system_admin_property_unlock_is_scoped_to_selected_property(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda liberada Laravel',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel liberada',
                'cnpj_cpf' => '12345678906',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $selectedPropertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda nao liberada Laravel',
                'municipio' => 'Jatai',
                'estado' => 'GO',
                'area_total' => 80,
                'responsavel' => 'Responsavel nao liberada',
                'cnpj_cpf' => '12345678907',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $otherPropertyId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $selectedPropertyId, profile: 'gerencia_sistema') + [
                    'system_write_unlocked_until' => time() + 300,
                    'system_write_unlocked_property_id' => $selectedPropertyId,
                ])
                ->from('/propriedades/'.$otherPropertyId.'/editar')
                ->put('/propriedades/'.$otherPropertyId, [
                    'nome' => 'Fazenda nao liberada alterada',
                    'municipio' => 'Jatai',
                    'estado' => 'GO',
                    'area_total' => '80',
                    'responsavel' => 'Responsavel nao liberada',
                    'inscricao_estadual' => '',
                    'cnpj_cpf' => '12345678907',
                    'plano' => 'premium',
                    'pecuaria_ativa' => '0',
                    'latitude' => '',
                    'longitude' => '',
                    'regiao_cotacao' => '',
                ])
                ->assertRedirect('/propriedades/'.$otherPropertyId.'/editar')
                ->assertSessionHasErrors();

            $this->assertDatabaseHas('propriedades', [
                'id' => $otherPropertyId,
                'nome' => 'Fazenda nao liberada Laravel',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_support_admin_page_returns_a_successful_response_for_system_staff(): void
    {
        $this->withSession($this->loggedSession(profile: 'gerencia_sistema'))
            ->get('/suporte')
            ->assertStatus(200)
            ->assertSee('Chat de Duvidas')
            ->assertSee('Central de atendimento');
    }

    public function test_support_chat_can_create_and_answer_conversation(): void
    {
        DB::beginTransaction();
        $arquivoPath = null;

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $clienteSession = $this->loggedSession(propertyId: $propertyId, profile: 'visualizador');
            $clienteId = (int) $clienteSession['usuario_id'];

            $response = $this->withSession($clienteSession)
                ->post('/suporte/chat/mensagens', [
                    'mensagem' => 'Preciso de ajuda no suporte Laravel',
                    'anexos' => [
                        UploadedFile::fake()->create('print-suporte.pdf', 8, 'application/pdf'),
                    ],
                ])
                ->assertStatus(200)
                ->assertJsonPath('ok', true)
                ->assertJsonPath('messages.0.autor_tipo', 'cliente')
                ->assertJsonPath('messages.0.anexos.0.nome', 'print-suporte.pdf');

            $conversaId = (int) $response->json('conversa.id');
            $anexoId = (int) $response->json('messages.0.anexos.0.id');
            $this->assertGreaterThan(0, $conversaId);
            $this->assertGreaterThan(0, $anexoId);

            $this->assertDatabaseHas('suporte_conversas', [
                'id' => $conversaId,
                'usuario_id' => $clienteId,
                'propriedade_id' => $propertyId,
                'status' => 'aberta',
            ]);
            $arquivoPath = base_path('../'.DB::table('suporte_anexos')->where('id', $anexoId)->value('caminho_relativo'));
            $this->assertFileExists($arquivoPath);

            $adminSession = $this->loggedSession(propertyId: $propertyId, profile: 'gerencia_sistema');
            $adminId = (int) $adminSession['usuario_id'];

            $this->withSession($adminSession)
                ->get('/suporte/chat/anexos/'.$anexoId)
                ->assertStatus(200)
                ->assertDownload('print-suporte.pdf');

            $this->assertDatabaseHas('suporte_anexos', [
                'id' => $anexoId,
                'baixado_por' => $adminId,
                'caminho_relativo' => null,
            ]);

            $this->withSession($adminSession)
                ->postJson('/suporte/chat/'.$conversaId.'/responder', [
                    'mensagem' => 'Atendimento respondido pelo Laravel',
                ])
                ->assertStatus(200)
                ->assertJsonPath('ok', true)
                ->assertJsonPath('conversa.status', 'respondida')
                ->assertJsonPath('messages.1.autor_tipo', 'admin');

            $this->assertDatabaseHas('suporte_conversas', [
                'id' => $conversaId,
                'status' => 'respondida',
                'atendente_usuario_id' => $adminId,
            ]);
        } finally {
            if ($arquivoPath && file_exists($arquivoPath)) {
                @unlink($arquivoPath);
            }
            DB::rollBack();
        }
    }

    public function test_internal_chat_can_send_read_and_download_attachment(): void
    {
        DB::beginTransaction();
        $arquivoPath = null;

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->propertyManagerUserId($propertyId);
            $this->assertGreaterThan(0, $propertyId);

            DB::table('usuarios')->insert([
                'nome' => 'Chat Remetente Laravel',
                'email' => 'chat-remetente-'.uniqid().'@teste.local',
                'senha' => 'teste',
                'perfil' => 'colaborador',
                'ativo' => 1,
            ]);
            $remetenteId = (int) DB::getPdo()->lastInsertId();
            DB::table('usuario_propriedades')->insert(['usuario_id' => $remetenteId, 'propriedade_id' => $propertyId]);

            DB::table('usuarios')->insert([
                'nome' => 'Chat Destinatario Laravel',
                'email' => 'chat-destinatario-'.uniqid().'@teste.local',
                'senha' => 'teste',
                'perfil' => 'visualizador',
                'ativo' => 1,
            ]);
            $destinatarioId = (int) DB::getPdo()->lastInsertId();
            DB::table('usuario_propriedades')->insert(['usuario_id' => $destinatarioId, 'propriedade_id' => $propertyId]);

            $remetenteSession = $this->loggedSession(propertyId: $propertyId, userId: $remetenteId);
            $destinatarioSession = $this->loggedSession(propertyId: $propertyId, userId: $destinatarioId);

            $this->withSession($remetenteSession)
                ->getJson('/chat-interno/contatos')
                ->assertStatus(200)
                ->assertJsonPath('ok', true)
                ->assertJsonFragment(['nome' => 'Chat Destinatario Laravel']);

            $response = $this->withSession($remetenteSession)
                ->post('/chat-interno/'.$destinatarioId.'/mensagens', [
                    'mensagem' => 'Mensagem interna Laravel',
                    'anexos' => [
                        UploadedFile::fake()->create('chat-interno.pdf', 8, 'application/pdf'),
                    ],
                ])
                ->assertStatus(200)
                ->assertJsonPath('ok', true)
                ->assertJsonPath('messages.0.mine', true)
                ->assertJsonPath('messages.0.anexos.0.nome', 'chat-interno.pdf');

            $anexoId = (int) $response->json('messages.0.anexos.0.id');
            $this->assertGreaterThan(0, $anexoId);

            $arquivoPath = base_path('../'.DB::table('chat_anexos')->where('id', $anexoId)->value('caminho_relativo'));
            $this->assertFileExists($arquivoPath);

            $this->withSession($destinatarioSession)
                ->getJson('/chat-interno/'.$remetenteId.'/mensagens')
                ->assertStatus(200)
                ->assertJsonPath('ok', true)
                ->assertJsonPath('messages.0.mine', false);

            $this->assertNotNull(DB::table('chat_mensagens')->where('remetente_usuario_id', $remetenteId)->where('destinatario_usuario_id', $destinatarioId)->value('lida_em'));

            $this->withSession($destinatarioSession)
                ->get('/chat-interno/anexos/'.$anexoId)
                ->assertStatus(200)
                ->assertDownload('chat-interno.pdf');

            $this->assertDatabaseHas('chat_anexos', [
                'id' => $anexoId,
                'baixado_por' => $destinatarioId,
                'caminho_relativo' => null,
            ]);
        } finally {
            if ($arquivoPath && file_exists($arquivoPath)) {
                @unlink($arquivoPath);
            }
            DB::rollBack();
        }
    }

    public function test_internal_chat_does_not_cross_property_boundaries(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda chat origem Laravel',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel chat origem',
                'cnpj_cpf' => '44444444444',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propriedadeOrigemId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda chat destino Laravel',
                'municipio' => 'Jatai',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel chat destino',
                'cnpj_cpf' => '55555555555',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propriedadeDestinoId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuarios')->insert([
                'nome' => 'Chat Usuario Fazenda Origem',
                'email' => 'chat-origem-'.uniqid().'@teste.local',
                'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
                'perfil' => 'colaborador',
                'ativo' => 1,
            ]);
            $usuarioOrigemId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuarios')->insert([
                'nome' => 'Chat Usuario Outra Fazenda',
                'email' => 'chat-outra-fazenda-'.uniqid().'@teste.local',
                'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
                'perfil' => 'visualizador',
                'ativo' => 1,
            ]);
            $usuarioOutraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuario_propriedades')->insert([
                ['usuario_id' => $usuarioOrigemId, 'propriedade_id' => $propriedadeOrigemId],
                ['usuario_id' => $usuarioOutraPropriedadeId, 'propriedade_id' => $propriedadeDestinoId],
            ]);

            $sessaoOrigem = $this->loggedSession(propertyId: $propriedadeOrigemId, userId: $usuarioOrigemId);

            $this->withSession($sessaoOrigem)
                ->getJson('/chat-interno/contatos')
                ->assertStatus(200)
                ->assertJsonMissing(['nome' => 'Chat Usuario Outra Fazenda']);

            $this->withSession($sessaoOrigem)
                ->postJson('/chat-interno/'.$usuarioOutraPropriedadeId.'/mensagens', [
                    'mensagem' => 'Tentativa indevida entre propriedades',
                ])
                ->assertStatus(403);
        } finally {
            DB::rollBack();
        }
    }

    public function test_login_rate_limit_blocks_repeated_invalid_attempts(): void
    {
        DB::beginTransaction();

        $ip = '10.77.0.15';
        $email = 'login-rate-limit-'.uniqid().'@teste.local';
        $throttleKey = strtolower($email).'|'.$ip;
        RateLimiter::clear($throttleKey);

        try {
            $propertyId = (int) DB::table('propriedades')->where('ativo', 1)->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('usuarios')->insert([
                'nome' => 'Usuario Rate Limit Laravel',
                'email' => $email,
                'senha' => password_hash('senha-correta', PASSWORD_DEFAULT),
                'perfil' => 'gestor_propriedade',
                'ativo' => 1,
            ]);
            $usuarioId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuario_propriedades')->insert([
                'usuario_id' => $usuarioId,
                'propriedade_id' => $propertyId,
            ]);

            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->withServerVariables(['REMOTE_ADDR' => $ip])
                    ->from('/login')
                    ->post('/login', [
                        'email' => $email,
                        'senha' => 'senha-errada',
                    ])
                    ->assertRedirect('/login')
                    ->assertSessionHasErrors('email');
            }

            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->from('/login')
                ->post('/login', [
                    'email' => $email,
                    'senha' => 'senha-correta',
                ])
                ->assertRedirect('/login')
                ->assertSessionHasErrors('email');

            $this->assertTrue(RateLimiter::tooManyAttempts($throttleKey, 5));
        } finally {
            RateLimiter::clear($throttleKey);
            DB::rollBack();
        }
    }

    public function test_purchase_orders_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/compras/pedidos')
            ->assertStatus(200)
            ->assertSee('Pedidos de compras');
    }

    public function test_purchase_entry_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/compras')
            ->assertStatus(200)
            ->assertSee('Pedidos de compras')
            ->assertSee('Novo Pedido');
    }

    public function test_purchase_order_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/compras/pedidos/novo')
            ->assertStatus(200)
            ->assertSee('Novo Pedido')
            ->assertSee('Itens do pedido');
    }

    public function test_purchase_order_can_be_edited_until_approval(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->userId();
            $this->assertGreaterThan(0, $propertyId);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-EDIT',
                'supplier_name' => 'Fornecedor original',
                'supplier_cnpj' => '12345678000190',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 100,
                'status' => 'em_aberto',
                'notes' => 'Pedido aberto',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_order_items')->insert([
                [
                    'order_id' => $orderId,
                    'product_code' => 'NF-ITEM-001',
                    'description' => 'Produto conferido',
                    'unit' => 'Unidade',
                    'quantity' => 2,
                    'unit_value' => 50,
                    'total_value' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'order_id' => $orderId,
                    'product_code' => 'NF-ITEM-002',
                    'description' => 'Produto divergente',
                    'unit' => 'Unidade',
                    'quantity' => 1,
                    'unit_value' => 110,
                    'total_value' => 110,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('fiscal_order_items')->insert([
                'order_id' => $orderId,
                'product_code' => 'EDIT-001',
                'description' => 'Produto original',
                'unit' => 'Unidade',
                'quantity' => 1,
                'unit_value' => 100,
                'total_value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/compras/pedidos/'.$orderId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar Pedido')
                ->assertSee('Fornecedor original');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/compras/pedidos/'.$orderId, [
                    'order_number' => 'PED-LARAVEL-EDIT',
                    'issue_date' => '2026-07-10',
                    'supplier_name' => 'Fornecedor editado',
                    'supplier_cnpj' => '00999888000177',
                    'notes' => 'Pedido alterado',
                    'item_product_code' => ['EDIT-002'],
                    'item_description' => ['Produto editado'],
                    'item_categoria_id' => [''],
                    'item_patrimonio_id' => [''],
                    'item_patrimonio_uso' => ['estoque'],
                    'item_patrimonio_quantidade' => [''],
                    'item_unit' => ['Unidade'],
                    'item_quantity' => ['2'],
                    'item_unit_value' => ['75'],
                ])
                ->assertRedirect('/compras/pedidos/'.$orderId);

            $this->assertDatabaseHas('fiscal_orders', [
                'id' => $orderId,
                'supplier_name' => 'Fornecedor editado',
                'supplier_cnpj' => '00999888000177',
                'total_value' => '150.00',
            ]);
            $this->assertDatabaseHas('fiscal_order_items', [
                'order_id' => $orderId,
                'description' => 'Produto editado',
                'quantity' => '2.0000',
                'unit_value' => '75.00',
                'total_value' => '150.00',
            ]);
            $this->assertDatabaseMissing('fiscal_order_items', [
                'order_id' => $orderId,
                'description' => 'Produto original',
            ]);

            DB::table('fiscal_orders')->where('id', $orderId)->update(['status' => 'aprovado_baixado']);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/compras/pedidos/'.$orderId.'/editar')
                ->put('/compras/pedidos/'.$orderId, [
                    'order_number' => 'PED-LARAVEL-EDIT',
                    'issue_date' => '2026-07-10',
                    'supplier_name' => 'Fornecedor bloqueado',
                    'supplier_cnpj' => '00999888000177',
                    'item_description' => ['Produto bloqueado'],
                    'item_unit' => ['Unidade'],
                    'item_quantity' => ['1'],
                    'item_unit_value' => ['1'],
                ])
                ->assertRedirect('/compras/pedidos/'.$orderId.'/editar')
                ->assertSessionHasErrors();

            $this->assertDatabaseMissing('fiscal_orders', [
                'id' => $orderId,
                'supplier_name' => 'Fornecedor bloqueado',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_purchase_order_can_link_and_unlink_invoice(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-NF',
                'supplier_name' => 'Fornecedor NF Laravel',
                'supplier_cnpj' => '12345678000190',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 210,
                'status' => 'em_aberto',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_order_items')->insert([
                [
                    'order_id' => $orderId,
                    'product_code' => 'NF-ITEM-001',
                    'description' => 'Produto conferido',
                    'unit' => 'Unidade',
                    'quantity' => 2,
                    'unit_value' => 50,
                    'total_value' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'order_id' => $orderId,
                    'product_code' => 'NF-ITEM-002',
                    'description' => 'Produto divergente',
                    'unit' => 'Unidade',
                    'quantity' => 1,
                    'unit_value' => 110,
                    'total_value' => 110,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-NF-OUTRO',
                'supplier_name' => 'Fornecedor NF Laravel',
                'supplier_cnpj' => '12345678000190',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 210,
                'status' => 'em_aberto',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $otherOrderId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_invoices')->insert([
                'propriedade_id' => $propertyId,
                'user_id' => $this->userId(),
                'access_key' => 'NFE-LARAVEL-LINK-'.uniqid(),
                'invoice_number' => 'NF-LARAVEL-LINK',
                'series' => '1',
                'issue_date' => '2026-07-09',
                'issuer_cnpj' => '12345678000190',
                'issuer_name' => 'Fornecedor NF Laravel',
                'recipient_cnpj' => '98765432000110',
                'recipient_name' => 'Fazenda Teste',
                'total_value' => 210,
                'status' => 'aguardando_aprovacao',
                'xml_file_path' => 'storage/app/private/nf-link.xml',
                'created_by' => $this->userId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_invoice_items')->insert([
                [
                    'invoice_id' => $invoiceId,
                    'product_code' => 'NF-ITEM-001',
                    'description' => 'Produto conferido',
                    'unit' => 'Unidade',
                    'quantity' => 2,
                    'unit_value' => 50,
                    'total_value' => 100,
                ],
                [
                    'invoice_id' => $invoiceId,
                    'product_code' => 'NF-ITEM-002',
                    'description' => 'Produto divergente',
                    'unit' => 'Unidade',
                    'quantity' => 1,
                    'unit_value' => 100,
                    'total_value' => 100,
                ],
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/compras/pedidos/'.$orderId.'/notas', ['invoice_id' => $invoiceId])
                ->assertRedirect('/compras/pedidos/'.$orderId)
                ->assertSessionHas('success')
                ->assertSessionHas('fiscal_order_invoice_preview');

            $preview = session('fiscal_order_invoice_preview');
            $this->assertIsArray($preview);
            $this->assertSame($orderId, (int) $preview['order_id']);
            $this->assertSame($invoiceId, (int) $preview['invoice_id']);

            $this->assertDatabaseMissing('fiscal_order_invoices', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
            ]);

            $this->withSession([...$this->loggedSession(propertyId: $propertyId), 'fiscal_order_invoice_preview' => $preview])
                ->get('/compras/pedidos/'.$orderId)
                ->assertStatus(200)
                ->assertSee('Conferência da nota fiscal')
                ->assertSee('Valor unitario divergente');

            $this->withSession([...$this->loggedSession(propertyId: $propertyId), 'fiscal_order_invoice_preview' => $preview])
                ->post('/compras/pedidos/'.$orderId.'/notas/confirmar')
                ->assertRedirect('/compras/pedidos/'.$orderId)
                ->assertSessionHas('success');

            $this->assertDatabaseHas('fiscal_order_invoices', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'match_status' => 'divergente',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/compras/pedidos/'.$orderId)
                ->assertStatus(200)
                ->assertSee('Notas fiscais vinculadas')
                ->assertSee('NF-LARAVEL-LINK')
                ->assertSee('Itens divergentes')
                ->assertSee('Valor unitario divergente');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/compras/pedidos/'.$otherOrderId)
                ->post('/compras/pedidos/'.$otherOrderId.'/notas', ['invoice_id' => $invoiceId])
                ->assertRedirect('/compras/pedidos/'.$otherOrderId)
                ->assertSessionHasErrors();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->delete('/compras/pedidos/'.$orderId.'/notas/'.$invoiceId)
                ->assertRedirect('/compras/pedidos/'.$orderId)
                ->assertSessionHas('success');

            $this->assertDatabaseMissing('fiscal_order_invoices', [
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_purchase_order_can_import_invoice_xml_before_linking(): void
    {
        DB::beginTransaction();
        Storage::fake('local');

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-XML-LINK',
                'supplier_name' => 'Fornecedor XML Link',
                'supplier_cnpj' => '12345678000195',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 100,
                'status' => 'em_aberto',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = (int) DB::getPdo()->lastInsertId();
            DB::table('fiscal_order_items')->insert([
                'order_id' => $orderId,
                'product_code' => 'PXML001',
                'description' => 'Produto XML para pedido',
                'unit' => 'UN',
                'quantity' => 2,
                'unit_value' => 50,
                'total_value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $accessKey = '35260712345678000195550010000076541000076543';
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$accessKey}">
      <ide><serie>1</serie><nNF>7654</nNF><dhEmi>2026-07-09T08:00:00-03:00</dhEmi></ide>
      <emit><CNPJ>12345678000195</CNPJ><xNome>Fornecedor XML Link</xNome></emit>
      <dest><CNPJ>98765432000110</CNPJ><xNome>Fazenda Teste</xNome></dest>
      <det nItem="1"><prod><cProd>PXML001</cProd><xProd>Produto XML para pedido</xProd><uCom>UN</uCom><qCom>2.0000</qCom><vUnCom>50.0000</vUnCom><vProd>100.00</vProd></prod></det>
      <total><ICMSTot><vNF>100.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
</nfeProc>
XML;

            $file = UploadedFile::fake()->createWithContent('pedido-link.xml', $xml);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/compras/pedidos/'.$orderId.'/notas/importar', ['xml' => $file])
                ->assertRedirect('/compras/pedidos/'.$orderId)
                ->assertSessionHas('fiscal_order_invoice_preview');

            $invoice = DB::table('fiscal_invoices')->where('access_key', $accessKey)->first();
            $this->assertNotNull($invoice);
            $preview = session('fiscal_order_invoice_preview');
            $this->assertSame((int) $invoice->id, (int) $preview['invoice_id']);
            $this->assertSame(1, (int) $preview['comparison']['match_count']);

            $this->withSession([...$this->loggedSession(propertyId: $propertyId), 'fiscal_order_invoice_preview' => $preview])
                ->post('/compras/pedidos/'.$orderId.'/notas/confirmar')
                ->assertRedirect('/compras/pedidos/'.$orderId);

            $this->assertDatabaseHas('fiscal_order_invoices', [
                'order_id' => $orderId,
                'invoice_id' => $invoice->id,
                'match_status' => 'conferido',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_purchase_order_can_reuse_existing_invoice_xml_before_linking(): void
    {
        DB::beginTransaction();
        Storage::fake('local');

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-XML-EXISTENTE',
                'supplier_name' => 'Fornecedor XML Existente',
                'supplier_cnpj' => '12345678000195',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 100,
                'status' => 'em_aberto',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = (int) DB::getPdo()->lastInsertId();
            DB::table('fiscal_order_items')->insert([
                'order_id' => $orderId,
                'product_code' => 'PXML002',
                'description' => 'Produto XML existente',
                'unit' => 'UN',
                'quantity' => 1,
                'unit_value' => 100,
                'total_value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $accessKey = '35260712345678000195550010000076551000076550';
            DB::table('fiscal_invoices')->insert([
                'propriedade_id' => $propertyId,
                'user_id' => $this->userId(),
                'access_key' => $accessKey,
                'invoice_number' => '7655',
                'series' => '1',
                'issue_date' => '2026-07-09',
                'issuer_cnpj' => '12345678000195',
                'issuer_name' => 'Fornecedor XML Existente',
                'recipient_cnpj' => '98765432000110',
                'recipient_name' => 'Fazenda Teste',
                'total_value' => 100,
                'status' => 'aguardando_aprovacao',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceId = (int) DB::getPdo()->lastInsertId();
            DB::table('fiscal_invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'product_code' => 'PXML002',
                'description' => 'Produto XML existente',
                'unit' => 'UN',
                'quantity' => 1,
                'unit_value' => 100,
                'total_value' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$accessKey}">
      <ide><serie>1</serie><nNF>7655</nNF><dhEmi>2026-07-09T08:00:00-03:00</dhEmi></ide>
      <emit><CNPJ>12345678000195</CNPJ><xNome>Fornecedor XML Existente</xNome></emit>
      <dest><CNPJ>98765432000110</CNPJ><xNome>Fazenda Teste</xNome></dest>
      <det nItem="1"><prod><cProd>PXML002</cProd><xProd>Produto XML existente</xProd><uCom>UN</uCom><qCom>1.0000</qCom><vUnCom>100.0000</vUnCom><vProd>100.00</vProd></prod></det>
      <total><ICMSTot><vNF>100.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
</nfeProc>
XML;

            $file = UploadedFile::fake()->createWithContent('pedido-existente.xml', $xml);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/compras/pedidos/'.$orderId.'/notas/importar', ['xml' => $file])
                ->assertRedirect('/compras/pedidos/'.$orderId)
                ->assertSessionHas('fiscal_order_invoice_preview');

            $this->assertSame(1, DB::table('fiscal_invoices')->where('access_key', $accessKey)->count());
            $preview = session('fiscal_order_invoice_preview');
            $this->assertSame($invoiceId, (int) $preview['invoice_id']);
            $this->assertSame(1, (int) $preview['comparison']['match_count']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_purchase_order_approval_creates_financial_and_stock_entries(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            session(['propriedade_id' => $propertyId]);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-APPROVE',
                'supplier_name' => 'Fornecedor Laravel',
                'supplier_cnpj' => '12345678000190',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 150,
                'status' => 'em_aberto',
                'notes' => 'Teste automatizado de aprovacao',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_order_items')->insert([
                'order_id' => $orderId,
                'product_code' => 'APPROVE-001',
                'description' => 'Produto de aprovacao Laravel',
                'unit' => 'Unidade',
                'quantity' => 3,
                'unit_value' => 50,
                'total_value' => 150,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/compras/pedidos/'.$orderId.'/aprovar', [
                    'confirmar_aprovacao' => '1',
                    'confirmar_sem_nota' => '1',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('fiscal_orders', [
                'id' => $orderId,
                'status' => 'aprovado_baixado',
            ]);
            $this->assertDatabaseHas('despesas', [
                'propriedade_id' => $propertyId,
                'descricao' => 'Pedido fiscal PED-LARAVEL-APPROVE',
                'valor_total' => '150.00',
            ]);
            $this->assertDatabaseHas('produto_estoque_movimentos', [
                'propriedade_id' => $propertyId,
                'fiscal_order_id' => $orderId,
                'tipo' => 'entrada',
                'quantidade' => '3.0000',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_purchase_order_approval_requires_confirmation_without_invoice(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-SEM-NF',
                'supplier_name' => 'Fornecedor sem NF',
                'supplier_cnpj' => '12345678000190',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 80,
                'status' => 'em_aberto',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_order_items')->insert([
                'order_id' => $orderId,
                'product_code' => 'SEM-NF-001',
                'description' => 'Produto sem nota vinculada',
                'unit' => 'Unidade',
                'quantity' => 1,
                'unit_value' => 80,
                'total_value' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/compras/pedidos/'.$orderId)
                ->post('/compras/pedidos/'.$orderId.'/aprovar', ['confirmar_aprovacao' => '1'])
                ->assertRedirect('/compras/pedidos/'.$orderId)
                ->assertSessionHasErrors();

            $this->assertDatabaseHas('fiscal_orders', [
                'id' => $orderId,
                'status' => 'em_aberto',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_purchase_order_approval_requires_confirmation_with_invoice_divergence(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-DIVERGENCIA',
                'supplier_name' => 'Fornecedor Divergente',
                'supplier_cnpj' => '12345678000190',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 120,
                'status' => 'em_aberto',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_order_items')->insert([
                'order_id' => $orderId,
                'product_code' => 'DIV-001',
                'description' => 'Produto divergente',
                'unit' => 'Unidade',
                'quantity' => 1,
                'unit_value' => 120,
                'total_value' => 120,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('fiscal_invoices')->insert([
                'propriedade_id' => $propertyId,
                'user_id' => $this->userId(),
                'access_key' => 'NFE-LARAVEL-DIVERGENCIA-'.uniqid(),
                'invoice_number' => 'NF-LARAVEL-DIVERGENCIA',
                'series' => '1',
                'issue_date' => '2026-07-09',
                'issuer_cnpj' => '12345678000190',
                'issuer_name' => 'Fornecedor Divergente',
                'recipient_cnpj' => '98765432000110',
                'recipient_name' => 'Fazenda Teste',
                'total_value' => 100,
                'status' => 'aguardando_aprovacao',
                'created_by' => $this->userId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_order_invoices')->insert([
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'match_status' => 'divergente',
                'match_summary' => 'Teste automatizado com divergência.',
                'linked_by' => $this->userId(),
                'linked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/compras/pedidos/'.$orderId)
                ->post('/compras/pedidos/'.$orderId.'/aprovar', ['confirmar_aprovacao' => '1'])
                ->assertRedirect('/compras/pedidos/'.$orderId)
                ->assertSessionHasErrors();

            $this->assertDatabaseHas('fiscal_orders', [
                'id' => $orderId,
                'status' => 'em_aberto',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/compras/pedidos/'.$orderId.'/aprovar', [
                    'confirmar_aprovacao' => '1',
                    'confirmar_divergencias' => '1',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('fiscal_orders', [
                'id' => $orderId,
                'status' => 'aprovado_baixado',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_purchase_order_can_be_rejected(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('fiscal_orders')->insert([
                'propriedade_id' => $propertyId,
                'order_number' => 'PED-LARAVEL-REJEITAR',
                'supplier_name' => 'Fornecedor Rejeição',
                'supplier_cnpj' => '12345678000190',
                'order_type' => 'entrada',
                'issue_date' => '2026-07-09',
                'total_value' => 50,
                'status' => 'aguardando_aprovacao',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $orderId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/compras/pedidos/'.$orderId.'/rejeitar', [
                    'motivo_rejeicao' => 'Dados fiscais inconsistentes.',
                ])
                ->assertRedirect('/compras/pedidos?status=rejeitado');

            $this->assertDatabaseHas('fiscal_orders', [
                'id' => $orderId,
                'status' => 'rejeitado',
                'previous_status' => 'aguardando_aprovacao',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'rejeitar_pedido_fiscal',
                'tabela' => 'fiscal_orders',
                'registro_id' => $orderId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_patrimony_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/patrimonio')
            ->assertStatus(200)
            ->assertSee('Patrimônios')
            ->assertSee('Novo patrimônio')
            ->assertSee('Selecione um patrimônio para ver custos, abastecimentos e manutenções.');
    }

    public function test_patrimony_detail_page_returns_a_successful_response(): void
    {
        $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
        $patrimonioId = DB::table('maquinas')
            ->where('propriedade_id', $propertyId)
            ->where('ativo', 1)
            ->orderBy('id')
            ->value('id');
        if (! $patrimonioId) {
            $this->markTestSkipped('Sem patrimonio cadastrado para testar detalhe.');
        }

        $this->withSession($this->loggedSession(propertyId: $propertyId))
            ->get('/patrimonio?patrimonio='.$patrimonioId)
            ->assertStatus(200)
            ->assertSee('Preço do patrimônio')
            ->assertSee('Lançamentos');
    }

    public function test_patrimony_entry_can_be_stored(): void
    {
        $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
        $patrimonioId = DB::table('maquinas')
            ->where('propriedade_id', $propertyId)
            ->where('ativo', 1)
            ->orderBy('id')
            ->value('id');
        if (! $patrimonioId) {
            $this->markTestSkipped('Sem patrimonio cadastrado para testar lancamento.');
        }

        DB::beginTransaction();
        $arquivoNome = null;
        try {
            $descricao = 'Lancamento patrimonio teste '.uniqid();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/patrimonio/'.$patrimonioId.'/lancamentos', [
                    'tipo' => 'manutencao_preventiva',
                    'data_lancamento' => '2026-07-09',
                    'descricao' => $descricao,
                    'fornecedor' => 'Fornecedor Teste',
                    'quantidade' => '2',
                    'unidade' => 'h',
                    'valor_unitario' => '50,00',
                    'valor_total' => '',
                    'horimetro' => '123,4',
                    'comprovante' => UploadedFile::fake()->create('comprovante-patrimonio.pdf', 8, 'application/pdf'),
                    'observacoes' => 'Teste automatizado',
                ])
                ->assertRedirect('/patrimonio?patrimonio='.$patrimonioId);

            $this->assertDatabaseHas('maquina_lancamentos', [
                'maquina_id' => $patrimonioId,
                'descricao' => $descricao,
                'valor_total' => '100.00',
            ]);

            $arquivoNome = DB::table('maquina_lancamentos')
                ->where('maquina_id', $patrimonioId)
                ->where('descricao', $descricao)
                ->value('comprovante');

            $this->assertNotEmpty($arquivoNome);
            $this->assertFileExists(base_path('../uploads/comprovantes/'.$arquivoNome));

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/patrimonio?patrimonio='.$patrimonioId)
                ->assertStatus(200)
                ->assertSee($descricao);
        } finally {
            if ($arquivoNome) {
                @unlink(base_path('../uploads/comprovantes/'.$arquivoNome));
            }
            DB::rollBack();
        }
    }

    public function test_patrimony_can_be_edited_and_toggled(): void
    {
        DB::beginTransaction();
        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('maquinas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Patrimonio editar Laravel',
                'tipo' => 'trator',
                'marca_modelo' => 'Modelo teste',
                'identificacao' => 'PAT-001',
                'descricao_patrimonio' => 'Criado pelo teste',
                'ano' => 2020,
                'valor_aquisicao' => 100000,
                'data_aquisicao' => '2020-01-10',
                'fornecedor' => 'Fornecedor teste',
                'controla_horimetro' => 1,
                'controla_odometro' => 0,
                'horimetro_atual' => 10,
                'ativo' => 1,
            ]);
            $patrimonioId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/patrimonio/'.$patrimonioId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/patrimonio/'.$patrimonioId, [
                    'nome' => 'Patrimonio atualizado Laravel',
                    'tipo' => 'implemento',
                    'tipo_outro' => '',
                    'marca_modelo' => 'Modelo atualizado',
                    'identificacao' => 'PAT-002',
                    'descricao_patrimonio' => 'Atualizado pelo teste',
                    'ano' => '2024',
                    'valor_aquisicao' => '120.000,50',
                    'data_aquisicao' => '2024-02-20',
                    'fornecedor' => 'Fornecedor atualizado',
                    'controla_horimetro' => '0',
                    'controla_odometro' => '1',
                    'horimetro_atual' => '999,9',
                    'odometro_atual' => '12.345,6',
                ])
                ->assertRedirect('/patrimonio?patrimonio='.$patrimonioId);

            $this->assertDatabaseHas('maquinas', [
                'id' => $patrimonioId,
                'propriedade_id' => $propertyId,
                'nome' => 'Patrimonio atualizado Laravel',
                'tipo' => 'implemento',
                'valor_aquisicao' => '120000.50',
                'controla_horimetro' => 0,
                'controla_odometro' => 1,
                'horimetro_atual' => null,
                'odometro_atual' => '12345.60',
                'ativo' => 1,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'editar_patrimonio',
                'tabela' => 'maquinas',
                'registro_id' => $patrimonioId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/patrimonio/'.$patrimonioId.'/alternar-status')
                ->assertRedirect('/patrimonio');

            $this->assertDatabaseHas('maquinas', [
                'id' => $patrimonioId,
                'propriedade_id' => $propertyId,
                'ativo' => 0,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'apagar_patrimonio',
                'tabela' => 'maquinas',
                'registro_id' => $patrimonioId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_patrimony_value_can_be_updated_without_resyncing_invoice(): void
    {
        DB::beginTransaction();
        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('maquinas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Patrimonio valor Laravel',
                'tipo' => 'implemento',
                'valor_aquisicao' => 10000,
                'nota_fiscal_numero' => 'NF-VALOR-001',
                'ativo' => 1,
            ]);
            $patrimonioId = (int) DB::getPdo()->lastInsertId();
            $nfCountBefore = DB::table('nf_entradas')->where('propriedade_id', $propertyId)->count();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/patrimonio?patrimonio='.$patrimonioId)
                ->assertStatus(200)
                ->assertSee('Salvar preço');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/patrimonio/'.$patrimonioId.'/valor', [
                    'valor_aquisicao' => '45.500,75',
                ])
                ->assertRedirect('/patrimonio?patrimonio='.$patrimonioId);

            $this->assertDatabaseHas('maquinas', [
                'id' => $patrimonioId,
                'propriedade_id' => $propertyId,
                'valor_aquisicao' => '45500.75',
                'nota_fiscal_numero' => 'NF-VALOR-001',
            ]);
            $this->assertSame($nfCountBefore, DB::table('nf_entradas')->where('propriedade_id', $propertyId)->count());
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'atualizar_valor_patrimonio',
                'tabela' => 'maquinas',
                'registro_id' => $patrimonioId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_patrimony_create_stores_initial_meters(): void
    {
        DB::beginTransaction();
        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $nome = 'Patrimonio medidores Laravel '.uniqid();

            $response = $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/patrimonio', [
                    'nome' => $nome,
                    'tipo' => 'trator',
                    'tipo_outro' => '',
                    'marca_modelo' => 'Modelo medidor',
                    'identificacao' => 'MED-001',
                    'descricao_patrimonio' => 'Teste de medidores iniciais',
                    'ano' => '2025',
                    'valor_aquisicao' => '300.000,00',
                    'data_aquisicao' => '2025-01-15',
                    'fornecedor' => 'Fornecedor medidor',
                    'controla_horimetro' => '1',
                    'controla_odometro' => '1',
                    'horimetro_atual' => '123,4',
                    'odometro_atual' => '5.678,9',
                ]);

            $response->assertRedirect();
            $this->assertStringContainsString('/patrimonio?patrimonio=', (string) $response->headers->get('Location'));

            $this->assertDatabaseHas('maquinas', [
                'propriedade_id' => $propertyId,
                'nome' => $nome,
                'controla_horimetro' => 1,
                'controla_odometro' => 1,
                'horimetro_atual' => '123.40',
                'odometro_atual' => '5678.90',
                'ativo' => 1,
            ]);
            $patrimonioId = (int) DB::table('maquinas')
                ->where('propriedade_id', $propertyId)
                ->where('nome', $nome)
                ->value('id');
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'novo_patrimonio',
                'tabela' => 'maquinas',
                'registro_id' => $patrimonioId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_patrimony_purchase_syncs_fiscal_entry_and_document(): void
    {
        DB::beginTransaction();
        $arquivoNome = null;

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            $nome = 'Patrimonio NF Laravel '.uniqid();

            $response = $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/patrimonio', [
                    'nome' => $nome,
                    'tipo' => 'implemento',
                    'tipo_outro' => '',
                    'marca_modelo' => 'Modelo NF',
                    'identificacao' => 'NF-001',
                    'descricao_patrimonio' => 'Patrimonio com nota fiscal',
                    'ano' => '2026',
                    'valor_aquisicao' => '85.000,00',
                    'data_aquisicao' => '2026-07-09',
                    'fornecedor' => 'Fornecedor NF Laravel',
                    'fornecedor_doc' => '12.345.678/0001-90',
                    'nota_fiscal_numero' => 'NF-PAT-001',
                    'nota_fiscal_serie' => '1',
                    'nota_fiscal_chave' => '12345678901234567890123456789012345678901234',
                    'nota_fiscal_arquivo' => UploadedFile::fake()->create('nf-patrimonio.pdf', 8, 'application/pdf'),
                    'controla_horimetro' => '0',
                    'controla_odometro' => '0',
                ]);

            $response->assertRedirect();
            $this->assertStringContainsString('/patrimonio?patrimonio=', (string) $response->headers->get('Location'));

            $patrimonio = DB::table('maquinas')->where('propriedade_id', $propertyId)->where('nome', $nome)->first();
            $this->assertNotNull($patrimonio);
            $this->assertDatabaseHas('nf_entradas', [
                'propriedade_id' => $propertyId,
                'numero' => 'NF-PAT-001',
                'fornecedor' => 'Fornecedor NF Laravel',
                'valor_financeiro_final' => '85000.00',
            ]);
            $this->assertDatabaseHas('documentos', [
                'propriedade_id' => $propertyId,
                'tipo' => 'nota_fiscal',
                'numero' => 'NF-PAT-001',
                'status' => 'conferido',
            ]);

            $this->assertNotEmpty($patrimonio->nf_entrada_id);
            $this->assertNotEmpty($patrimonio->documento_id);
            $this->assertNotEmpty($patrimonio->nota_fiscal_arquivo);
            $arquivoNome = $patrimonio->nota_fiscal_arquivo;
            $this->assertFileExists(base_path('../uploads/comprovantes/'.$arquivoNome));

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/patrimonio?patrimonio='.$patrimonio->id)
                ->assertStatus(200)
                ->assertSee('Abrir no fiscal')
                ->assertSee('Abrir arquivo');
        } finally {
            if ($arquivoNome) {
                @unlink(base_path('../uploads/comprovantes/'.$arquivoNome));
            }
            DB::rollBack();
        }
    }

    public function test_finance_module_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/modulos/financeiro')
            ->assertStatus(200)
            ->assertSee('Financeiro');
    }

    public function test_financial_dashboard_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro')
            ->assertStatus(200)
            ->assertSee('Painel Financeiro')
            ->assertSee('Agenda financeira')
            ->assertSee('Saldos por conta');
    }

    public function test_finance_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/lancamentos/novo')
            ->assertStatus(200)
            ->assertSee('Novo lançamento');
    }

    public function test_finance_revenue_launch_uses_registered_buyer(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/receitas/compradores', [
                    'nome' => 'Comprador Lancamento Laravel',
                    'documento' => '123',
                ])
                ->assertRedirect('/financeiro/receitas');

            $compradorId = (int) DB::table('compradores')
                ->where('propriedade_id', $propertyId)
                ->where('nome', 'Comprador Lancamento Laravel')
                ->value('id');
            $this->assertGreaterThan(0, $compradorId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/lancamentos/novo?tipo=receita')
                ->assertStatus(200)
                ->assertSee('Comprador Lancamento Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/lancamentos', [
                    'tipo' => 'receita',
                    'descricao' => 'Receita via comprador cadastrado',
                    'comprador_id' => $compradorId,
                    'pessoa' => 'Texto ignorado',
                    'quantidade' => '10,50',
                    'unidade' => 'sc',
                    'preco_unitario' => '119,12',
                    'valor_total' => '',
                    'data_lancamento' => '2026-07-09',
                    'baixado' => '0',
                ])
                ->assertRedirect('/modulos/financeiro');

            $this->assertDatabaseHas('receitas', [
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita via comprador cadastrado',
                'comprador' => 'Comprador Lancamento Laravel',
                'quantidade' => '10.50',
                'unidade' => 'sc',
                'preco_unitario' => '119.12',
                'valor_total' => '1250.76',
                'status' => 'pendente',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenue_can_be_edited(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria receita edicao Laravel',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'circle',
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('compradores')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Comprador Edicao Laravel',
                'documento' => '456',
                'ativo' => 1,
            ]);
            $compradorId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita antes edicao Laravel',
                'comprador' => 'Comprador antigo',
                'valor_total' => 1000,
                'data_venda' => '2026-07-01',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);
            $receitaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/receitas/'.$receitaId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar receita')
                ->assertSee('Receita antes edicao Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/financeiro/receitas/'.$receitaId, [
                    'tipo' => 'receita',
                    'descricao' => 'Receita editada Laravel',
                    'comprador_id' => $compradorId,
                    'pessoa' => 'Texto ignorado edicao',
                    'categoria_id' => $categoriaId,
                    'quantidade' => '20',
                    'unidade' => 'sc',
                    'preco_unitario' => '120,00',
                    'valor_total' => '',
                    'data_lancamento' => '2026-07-15',
                    'baixado' => '0',
                    'observacoes' => 'Observacao editada Laravel',
                ])
                ->assertRedirect('/financeiro/receitas');

            $this->assertDatabaseHas('receitas', [
                'id' => $receitaId,
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita editada Laravel',
                'comprador' => 'Comprador Edicao Laravel',
                'categoria_id' => $categoriaId,
                'quantidade' => '20.00',
                'unidade' => 'sc',
                'preco_unitario' => '120.00',
                'valor_total' => '2400.00',
                'data_venda' => '2026-07-15',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $this->userId(),
                'acao' => 'editar_receita',
                'tabela' => 'receitas',
                'registro_id' => $receitaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenue_can_be_duplicated(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('compradores')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Comprador Duplicar Laravel',
                'documento' => '789',
                'ativo' => 1,
            ]);
            $compradorId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita original duplicar Laravel',
                'comprador' => 'Comprador Duplicar Laravel',
                'quantidade' => 12,
                'unidade' => 'sc',
                'preco_unitario' => 100,
                'valor_total' => 1200,
                'data_venda' => '2026-07-01',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);
            $receitaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/receitas/'.$receitaId.'/duplicar')
                ->assertStatus(200)
                ->assertSee('Duplicar receita')
                ->assertSee('Receita original duplicar Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/lancamentos', [
                    'tipo' => 'receita',
                    'descricao' => 'Receita duplicada Laravel',
                    'comprador_id' => $compradorId,
                    'quantidade' => '12',
                    'unidade' => 'sc',
                    'preco_unitario' => '100,00',
                    'valor_total' => '',
                    'data_lancamento' => '2026-07-20',
                    'baixado' => '0',
                ])
                ->assertRedirect('/modulos/financeiro');

            $this->assertDatabaseHas('receitas', [
                'id' => $receitaId,
                'descricao' => 'Receita original duplicar Laravel',
                'valor_total' => '1200.00',
            ]);

            $this->assertDatabaseHas('receitas', [
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita duplicada Laravel',
                'comprador' => 'Comprador Duplicar Laravel',
                'quantidade' => '12.00',
                'unidade' => 'sc',
                'preco_unitario' => '100.00',
                'valor_total' => '1200.00',
                'data_venda' => '2026-07-20',
                'status' => 'pendente',
            ]);

            $this->assertSame(2, DB::table('receitas')
                ->where('propriedade_id', $propertyId)
                ->whereIn('descricao', ['Receita original duplicar Laravel', 'Receita duplicada Laravel'])
                ->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expense_launch_creates_monthly_installments(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria despesa vinculada Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'categoria_pai_id' => $categoriaId,
                'nome' => 'Subcategoria despesa vinculada Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $subcategoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra despesa vinculada Laravel',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2027-06-30',
                'area_plantada' => 10,
                'producao_estimada' => 20,
                'preco_estimado' => 100,
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao despesa vinculada Laravel',
                'area_bruta' => 10,
                'area_excluida_ha' => 0,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            DB::table('produtores')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Produtor despesa vinculada Laravel',
                'documento' => '123',
                'participacao_percentual' => 10,
                'ativo' => 1,
            ]);
            $produtorId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/lancamentos', [
                    'tipo' => 'despesa',
                    'descricao' => 'Despesa parcelada Laravel',
                    'pessoa' => 'Fornecedor parcelado',
                    'categoria_id' => $categoriaId,
                    'subcategoria_id' => $subcategoriaId,
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoId,
                    'produtor_id' => $produtorId,
                    'valor_total' => '900,00',
                    'data_lancamento' => '2026-07-09',
                    'data_vencimento' => '2026-07-10',
                    'numero_parcelas' => 3,
                    'forma_pagamento' => 'boleto',
                    'baixado' => '0',
                ])
                ->assertRedirect('/modulos/financeiro');

            $parcelas = DB::table('despesas')
                ->where('propriedade_id', $propertyId)
                ->where('descricao', 'Despesa parcelada Laravel')
                ->orderBy('parcela_atual')
                ->get(['valor_total', 'data_lancamento', 'data_vencimento', 'numero_parcelas', 'parcela_atual', 'status_pagamento', 'safra_id', 'talhao_id', 'categoria_id', 'subcategoria_id', 'produtor_id']);

            $this->assertCount(3, $parcelas);
            $this->assertSame(['2026-07-10', '2026-08-10', '2026-09-10'], $parcelas->pluck('data_vencimento')->all());
            $this->assertSame([1, 2, 3], $parcelas->pluck('parcela_atual')->map(fn ($value) => (int) $value)->all());
            $this->assertTrue($parcelas->every(fn ($parcela) => (int) $parcela->numero_parcelas === 3));
            $this->assertTrue($parcelas->every(fn ($parcela) => (float) $parcela->valor_total === 300.0));
            $this->assertTrue($parcelas->every(fn ($parcela) => $parcela->status_pagamento === 'pendente'));
            $this->assertTrue($parcelas->every(fn ($parcela) => (int) $parcela->safra_id === $safraId));
            $this->assertTrue($parcelas->every(fn ($parcela) => (int) $parcela->talhao_id === $talhaoId));
            $this->assertTrue($parcelas->every(fn ($parcela) => (int) $parcela->categoria_id === $categoriaId));
            $this->assertTrue($parcelas->every(fn ($parcela) => (int) $parcela->subcategoria_id === $subcategoriaId));
            $this->assertTrue($parcelas->every(fn ($parcela) => (int) $parcela->produtor_id === $produtorId));
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expense_can_be_edited(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria despesa edicao Laravel',
                'tipo' => 'outros',
                'cor' => '#ef4444',
                'icone' => 'circle',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa antes edicao Laravel',
                'fornecedor' => 'Fornecedor antigo',
                'valor_total' => 1000,
                'data_lancamento' => '2026-07-01',
                'data_vencimento' => '2026-07-10',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/despesas/'.$despesaId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar despesa')
                ->assertSee('Despesa antes edicao Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/financeiro/despesas/'.$despesaId, [
                    'tipo' => 'despesa',
                    'descricao' => 'Despesa editada Laravel',
                    'pessoa' => 'Fornecedor editado',
                    'categoria_id' => $categoriaId,
                    'quantidade' => '3',
                    'unidade' => 'un',
                    'preco_unitario' => '150,00',
                    'valor_total' => '',
                    'data_lancamento' => '2026-07-15',
                    'data_vencimento' => '2026-07-25',
                    'forma_pagamento' => 'boleto',
                    'baixado' => '0',
                    'observacoes' => 'Observacao despesa editada Laravel',
                ])
                ->assertRedirect('/financeiro/despesas');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'propriedade_id' => $propertyId,
                'descricao' => 'Despesa editada Laravel',
                'fornecedor' => 'Fornecedor editado',
                'categoria_id' => $categoriaId,
                'quantidade' => '3.00',
                'unidade' => 'un',
                'valor_unitario' => '150.00',
                'valor_total' => '450.00',
                'data_lancamento' => '2026-07-15',
                'data_vencimento' => '2026-07-25',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'forma_pagamento' => 'boleto',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $this->userId(),
                'acao' => 'editar_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expense_can_be_duplicated(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria despesa duplicar Laravel',
                'tipo' => 'outros',
                'cor' => '#ef4444',
                'icone' => 'circle',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa original duplicar Laravel',
                'fornecedor' => 'Fornecedor duplicar',
                'quantidade' => 4,
                'unidade' => 'un',
                'valor_unitario' => 75,
                'valor_total' => 300,
                'data_lancamento' => '2026-07-01',
                'data_vencimento' => '2026-07-10',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/despesas/'.$despesaId.'/duplicar')
                ->assertStatus(200)
                ->assertSee('Duplicar despesa')
                ->assertSee('Despesa original duplicar Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/lancamentos', [
                    'tipo' => 'despesa',
                    'descricao' => 'Despesa duplicada Laravel',
                    'pessoa' => 'Fornecedor duplicar',
                    'categoria_id' => $categoriaId,
                    'quantidade' => '4',
                    'unidade' => 'un',
                    'preco_unitario' => '75,00',
                    'valor_total' => '300,00',
                    'data_lancamento' => '2026-07-20',
                    'data_vencimento' => '2026-07-30',
                    'forma_pagamento' => 'pix',
                    'baixado' => '0',
                ])
                ->assertRedirect('/modulos/financeiro');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'descricao' => 'Despesa original duplicar Laravel',
                'valor_total' => '300.00',
            ]);

            $this->assertDatabaseHas('despesas', [
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa duplicada Laravel',
                'fornecedor' => 'Fornecedor duplicar',
                'quantidade' => '4.00',
                'unidade' => 'un',
                'valor_total' => '300.00',
                'data_lancamento' => '2026-07-20',
                'data_vencimento' => '2026-07-30',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);

            $this->assertSame(2, DB::table('despesas')
                ->where('propriedade_id', $propertyId)
                ->whereIn('descricao', ['Despesa original duplicar Laravel', 'Despesa duplicada Laravel'])
                ->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenues_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/receitas')
            ->assertStatus(200)
            ->assertSee('Receitas financeiras')
            ->assertSee('Filtros');
    }

    public function test_finance_revenue_can_be_approved_and_received(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            session(['propriedade_id' => $propertyId]);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita Laravel aprovacao',
                'comprador' => 'Comprador teste',
                'valor_total' => 300,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'pendente',
                'usuario_id' => $this->userId(),
            ]);
            $receitaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/receitas/'.$receitaId.'/aprovar')
                ->assertRedirect('/financeiro/receitas');

            $this->assertDatabaseHas('receitas', [
                'id' => $receitaId,
                'status_aprovacao' => 'aprovada',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $this->userId(),
                'acao' => 'aprovar_receita',
                'tabela' => 'receitas',
                'registro_id' => $receitaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/receitas/'.$receitaId.'/receber', [
                    'data_recebimento' => '2026-07-10',
                ])
                ->assertRedirect('/financeiro/receitas');

            $this->assertDatabaseHas('receitas', [
                'id' => $receitaId,
                'status' => 'recebido',
                'data_recebimento' => '2026-07-10',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $this->userId(),
                'acao' => 'receber_receita',
                'tabela' => 'receitas',
                'registro_id' => $receitaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenues_can_be_approved_in_batch(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $ids = [];
            foreach (['Receita lote A', 'Receita lote B'] as $descricao) {
                DB::table('receitas')->insert([
                    'propriedade_id' => $propertyId,
                    'descricao' => $descricao,
                    'comprador' => 'Comprador lote',
                    'valor_total' => 300,
                    'data_venda' => '2026-07-09',
                    'status' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'usuario_id' => $this->userId(),
                ]);
                $ids[] = (int) DB::getPdo()->lastInsertId();
            }

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita lote ja aprovada',
                'comprador' => 'Comprador lote',
                'valor_total' => 300,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);
            $jaAprovadaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/receitas/aprovar-lote', [
                    'receitas' => [...$ids, $jaAprovadaId],
                ])
                ->assertRedirect('/financeiro/receitas?aprovacao=pendente')
                ->assertSessionHas('success');

            foreach ($ids as $id) {
                $this->assertDatabaseHas('receitas', [
                    'id' => $id,
                    'status_aprovacao' => 'aprovada',
                ]);
            }

            $this->assertDatabaseHas('receitas', [
                'id' => $jaAprovadaId,
                'status_aprovacao' => 'aprovada',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenue_can_be_rejected_with_reason(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita Laravel reprovacao',
                'comprador' => 'Comprador teste',
                'valor_total' => 450,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'pendente',
                'usuario_id' => $this->userId(),
            ]);
            $receitaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/receitas/'.$receitaId.'/reprovar', [
                    'motivo_reprovacao' => 'Valor divergente',
                ])
                ->assertRedirect('/financeiro/receitas');

            $this->assertDatabaseHas('receitas', [
                'id' => $receitaId,
                'status' => 'pendente',
                'status_aprovacao' => 'reprovada',
                'motivo_reprovacao' => 'Valor divergente',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/receitas?aprovacao=reprovada')
                ->assertStatus(200)
                ->assertSee('Receita Laravel reprovacao')
                ->assertSee('Valor divergente');
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenue_can_be_canceled_and_hidden_from_list(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita Laravel cancelamento',
                'comprador' => 'Comprador teste',
                'valor_total' => 300,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);
            $receitaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/receitas/'.$receitaId.'/cancelar')
                ->assertRedirect('/financeiro/receitas');

            $this->assertDatabaseHas('receitas', [
                'id' => $receitaId,
                'status' => 'cancelado',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $this->userId(),
                'acao' => 'cancelar_receita',
                'tabela' => 'receitas',
                'registro_id' => $receitaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/receitas')
                ->assertStatus(200)
                ->assertDontSee('Receita Laravel cancelamento');
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenue_delete_request_needs_manager_approval(): void
    {
        DB::beginTransaction();

        try {
            DB::table('usuarios')->insert([
                'nome' => 'Colaborador receita Laravel',
                'email' => 'colaborador-receita-'.uniqid().'@teste.local',
                'senha' => password_hash('teste', PASSWORD_BCRYPT),
                'perfil' => 'colaborador',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $colaboradorId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuarios')->insert([
                'nome' => 'Gestor receita Laravel',
                'email' => 'gestor-receita-'.uniqid().'@teste.local',
                'senha' => password_hash('teste', PASSWORD_BCRYPT),
                'perfil' => 'gestao',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $gestorId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda receita aprovacao exclusao',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Teste',
                'plano' => 'premium',
                'ativo' => 1,
                'aprovador_usuario_id' => $gestorId,
                'criado_em' => now(),
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuario_propriedades')->insert([
                ['usuario_id' => $colaboradorId, 'propriedade_id' => $propertyId],
                ['usuario_id' => $gestorId, 'propriedade_id' => $propertyId],
            ]);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita exclusao solicitada Laravel',
                'comprador' => 'Comprador teste',
                'valor_total' => 300,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $colaboradorId,
            ]);
            $receitaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $colaboradorId))
                ->post('/financeiro/receitas/'.$receitaId.'/cancelar')
                ->assertRedirect('/financeiro/receitas');

            $receita = DB::table('receitas')->where('id', $receitaId)->first();
            $this->assertSame('pendente', $receita->status);
            $this->assertSame('pendente', $receita->status_aprovacao);
            $this->assertStringContainsString('[SOLICITACAO_EXCLUSAO_RECEITA]', (string) $receita->observacoes);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $colaboradorId,
                'acao' => 'solicitar_exclusao_receita',
                'tabela' => 'receitas',
                'registro_id' => $receitaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $gestorId))
                ->post('/financeiro/receitas/'.$receitaId.'/aprovar')
                ->assertRedirect('/financeiro/receitas');

            $this->assertDatabaseHas('receitas', [
                'id' => $receitaId,
                'status' => 'cancelado',
                'status_aprovacao' => 'aprovada',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $gestorId,
                'acao' => 'aprovar_exclusao_receita',
                'tabela' => 'receitas',
                'registro_id' => $receitaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenue_delete_request_can_be_rejected_by_manager(): void
    {
        DB::beginTransaction();

        try {
            DB::table('usuarios')->insert([
                'nome' => 'Gestor reprova receita Laravel',
                'email' => 'gestor-reprova-receita-'.uniqid().'@teste.local',
                'senha' => password_hash('teste', PASSWORD_BCRYPT),
                'perfil' => 'gestao',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $gestorId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda receita reprova exclusao',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Teste',
                'plano' => 'premium',
                'ativo' => 1,
                'aprovador_usuario_id' => $gestorId,
                'criado_em' => now(),
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuario_propriedades')->insert([
                'usuario_id' => $gestorId,
                'propriedade_id' => $propertyId,
            ]);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita exclusao reprovada Laravel',
                'comprador' => 'Comprador teste',
                'valor_total' => 300,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'pendente',
                'observacoes' => "Observacao anterior\n[SOLICITACAO_EXCLUSAO_RECEITA] Solicitado por Colaborador em 09/07/2026 10:00",
                'usuario_id' => $gestorId,
            ]);
            $receitaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $gestorId))
                ->post('/financeiro/receitas/'.$receitaId.'/reprovar', [
                    'motivo_reprovacao' => 'Manter receita',
                ])
                ->assertRedirect('/financeiro/receitas');

            $receita = DB::table('receitas')->where('id', $receitaId)->first();
            $this->assertSame('pendente', $receita->status);
            $this->assertSame('aprovada', $receita->status_aprovacao);
            $this->assertSame('Observacao anterior', trim((string) $receita->observacoes));
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $gestorId,
                'acao' => 'reprovar_exclusao_receita',
                'tabela' => 'receitas',
                'registro_id' => $receitaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_revenue_buyer_can_be_saved_and_imported_from_existing_revenue(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/receitas/compradores', [
                    'nome' => '  cooperativa sicoob ltda  ',
                    'documento' => '12.345.678/0001-99',
                ])
                ->assertRedirect('/financeiro/receitas');

            $this->assertDatabaseHas('compradores', [
                'propriedade_id' => $propertyId,
                'nome' => 'Cooperativa SICOOB LTDA',
                'documento' => '12.345.678/0001-99',
                'ativo' => 1,
            ]);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita com comprador importado',
                'comprador' => 'cliente rural me',
                'valor_total' => 300,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/receitas')
                ->assertStatus(200)
                ->assertSee('Cooperativa SICOOB LTDA')
                ->assertSee('Cliente Rural ME');

            $this->assertDatabaseHas('compradores', [
                'propriedade_id' => $propertyId,
                'nome' => 'Cliente Rural ME',
                'ativo' => 1,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expenses_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/despesas')
            ->assertStatus(200)
            ->assertSee('Despesas financeiras')
            ->assertSee('Filtros');
    }

    public function test_finance_expense_can_be_approved_and_paid(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            session(['propriedade_id' => $propertyId]);
            $categoriaId = (int) DB::table('categorias')->where('ativo', 1)->orderBy('id')->value('id');
            if (! $categoriaId) {
                $this->markTestSkipped('Sem categoria ativa para criar despesa de teste.');
            }

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa Laravel aprovacao',
                'fornecedor' => 'Fornecedor teste',
                'valor_total' => 450,
                'data_lancamento' => '2026-07-09',
                'data_vencimento' => '2026-07-11',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'pendente',
                'usuario_id' => $this->userId(),
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/despesas/'.$despesaId.'/aprovar')
                ->assertRedirect('/financeiro/despesas');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'status_aprovacao' => 'aprovada',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'aprovar_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/despesas/'.$despesaId.'/pagar', [
                    'data_pagamento' => '2026-07-12',
                ])
                ->assertRedirect('/financeiro/despesas');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'status_pagamento' => 'pago',
                'data_pagamento' => '2026-07-12',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'pagar_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expenses_can_be_approved_in_batch(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            $categoriaId = (int) DB::table('categorias')->where('ativo', 1)->orderBy('id')->value('id');
            if (! $categoriaId) {
                $this->markTestSkipped('Sem categoria ativa para criar despesas de teste.');
            }

            $ids = [];
            foreach (['Despesa lote A', 'Despesa lote B'] as $descricao) {
                DB::table('despesas')->insert([
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => $descricao,
                    'fornecedor' => 'Fornecedor lote',
                    'valor_total' => 300,
                    'data_lancamento' => '2026-07-09',
                    'data_vencimento' => '2026-07-20',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'usuario_id' => $this->userId(),
                ]);
                $ids[] = (int) DB::getPdo()->lastInsertId();
            }

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa lote ja aprovada',
                'fornecedor' => 'Fornecedor lote',
                'valor_total' => 300,
                'data_lancamento' => '2026-07-09',
                'data_vencimento' => '2026-07-20',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);
            $jaAprovadaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/despesas/aprovar-lote', [
                    'despesas' => [...$ids, $jaAprovadaId],
                ])
                ->assertRedirect('/financeiro/despesas?aprovacao=pendente')
                ->assertSessionHas('success');

            foreach ($ids as $id) {
                $this->assertDatabaseHas('despesas', [
                    'id' => $id,
                    'status_aprovacao' => 'aprovada',
                ]);

                $this->assertDatabaseHas('logs_auditoria', [
                    'acao' => 'aprovar_despesa',
                    'tabela' => 'despesas',
                    'registro_id' => $id,
                    'propriedade_id' => $propertyId,
                ]);
            }

            $this->assertDatabaseHas('despesas', [
                'id' => $jaAprovadaId,
                'status_aprovacao' => 'aprovada',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expense_can_be_rejected_with_reason(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            $categoriaId = (int) DB::table('categorias')->where('ativo', 1)->orderBy('id')->value('id');
            if (! $categoriaId) {
                $this->markTestSkipped('Sem categoria ativa para criar despesa de teste.');
            }

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa Laravel reprovacao',
                'fornecedor' => 'Fornecedor teste',
                'valor_total' => 520,
                'data_lancamento' => '2026-07-09',
                'data_vencimento' => '2026-07-11',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'pendente',
                'usuario_id' => $this->userId(),
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/despesas/'.$despesaId.'/reprovar', [
                    'motivo_reprovacao' => 'Documento incompleto',
                ])
                ->assertRedirect('/financeiro/despesas');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'reprovada',
                'motivo_reprovacao' => 'Documento incompleto',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'reprovar_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/despesas?aprovacao=reprovada')
                ->assertStatus(200)
                ->assertSee('Despesa Laravel reprovacao')
                ->assertSee('Documento incompleto');
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expense_can_be_canceled_and_hidden_from_list(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            $categoriaId = (int) DB::table('categorias')->where('ativo', 1)->orderBy('id')->value('id');
            if (! $categoriaId) {
                $this->markTestSkipped('Sem categoria ativa para criar despesa de teste.');
            }

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa Laravel cancelamento',
                'fornecedor' => 'Fornecedor teste',
                'valor_total' => 520,
                'data_lancamento' => '2026-07-09',
                'data_vencimento' => '2026-07-11',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/despesas/'.$despesaId.'/cancelar')
                ->assertRedirect('/financeiro/despesas');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'status_pagamento' => 'cancelado',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'cancelar_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/despesas')
                ->assertStatus(200)
                ->assertDontSee('Despesa Laravel cancelamento');
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expense_delete_request_needs_manager_approval(): void
    {
        DB::beginTransaction();

        try {
            DB::table('usuarios')->insert([
                'nome' => 'Colaborador despesa Laravel',
                'email' => 'colaborador-despesa-'.uniqid().'@teste.local',
                'senha' => password_hash('teste', PASSWORD_BCRYPT),
                'perfil' => 'colaborador',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $colaboradorId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuarios')->insert([
                'nome' => 'Gestor despesa Laravel',
                'email' => 'gestor-despesa-'.uniqid().'@teste.local',
                'senha' => password_hash('teste', PASSWORD_BCRYPT),
                'perfil' => 'gestao',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $gestorId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda despesa aprovacao exclusao',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Teste',
                'plano' => 'premium',
                'ativo' => 1,
                'aprovador_usuario_id' => $gestorId,
                'criado_em' => now(),
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuario_propriedades')->insert([
                ['usuario_id' => $colaboradorId, 'propriedade_id' => $propertyId],
                ['usuario_id' => $gestorId, 'propriedade_id' => $propertyId],
            ]);

            $categoriaId = (int) DB::table('categorias')->where('ativo', 1)->orderBy('id')->value('id');
            if (! $categoriaId) {
                $this->markTestSkipped('Sem categoria ativa para criar despesa de teste.');
            }

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa exclusao solicitada Laravel',
                'fornecedor' => 'Fornecedor teste',
                'valor_total' => 300,
                'data_lancamento' => '2026-07-09',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $colaboradorId,
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $colaboradorId))
                ->post('/financeiro/despesas/'.$despesaId.'/cancelar')
                ->assertRedirect('/financeiro/despesas');

            $despesa = DB::table('despesas')->where('id', $despesaId)->first();
            $this->assertSame('pendente', $despesa->status_pagamento);
            $this->assertSame('pendente', $despesa->status_aprovacao);
            $this->assertStringContainsString('[SOLICITACAO_EXCLUSAO_DESPESA]', (string) $despesa->observacoes);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $colaboradorId,
                'acao' => 'solicitar_exclusao_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $gestorId))
                ->post('/financeiro/despesas/'.$despesaId.'/aprovar')
                ->assertRedirect('/financeiro/despesas');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'status_pagamento' => 'cancelado',
                'status_aprovacao' => 'aprovada',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $gestorId,
                'acao' => 'aprovar_exclusao_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_finance_expense_delete_request_can_be_rejected_by_manager(): void
    {
        DB::beginTransaction();

        try {
            DB::table('usuarios')->insert([
                'nome' => 'Gestor reprova despesa Laravel',
                'email' => 'gestor-reprova-despesa-'.uniqid().'@teste.local',
                'senha' => password_hash('teste', PASSWORD_BCRYPT),
                'perfil' => 'gestao',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $gestorId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda despesa reprova exclusao',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Teste',
                'plano' => 'premium',
                'ativo' => 1,
                'aprovador_usuario_id' => $gestorId,
                'criado_em' => now(),
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuario_propriedades')->insert([
                'usuario_id' => $gestorId,
                'propriedade_id' => $propertyId,
            ]);

            $categoriaId = (int) DB::table('categorias')->where('ativo', 1)->orderBy('id')->value('id');
            if (! $categoriaId) {
                $this->markTestSkipped('Sem categoria ativa para criar despesa de teste.');
            }

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa exclusao reprovada Laravel',
                'fornecedor' => 'Fornecedor teste',
                'valor_total' => 300,
                'data_lancamento' => '2026-07-09',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'pendente',
                'observacoes' => "Observacao anterior\n[SOLICITACAO_EXCLUSAO_DESPESA] Solicitado por Colaborador em 09/07/2026 10:00",
                'usuario_id' => $gestorId,
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $gestorId))
                ->post('/financeiro/despesas/'.$despesaId.'/reprovar', [
                    'motivo_reprovacao' => 'Manter despesa',
                ])
                ->assertRedirect('/financeiro/despesas');

            $despesa = DB::table('despesas')->where('id', $despesaId)->first();
            $this->assertSame('pendente', $despesa->status_pagamento);
            $this->assertSame('aprovada', $despesa->status_aprovacao);
            $this->assertSame('Observacao anterior', trim((string) $despesa->observacoes));
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $gestorId,
                'acao' => 'reprovar_exclusao_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_account_pages_return_successful_responses(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/contas')
            ->assertStatus(200)
            ->assertSee('Contas');

        $this->withSession($this->loggedSession())
            ->get('/financeiro/movimentacoes')
            ->assertStatus(200)
            ->assertSee('Movimenta');
    }

    public function test_bank_transfer_registers_audit_trail(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->userId();
            $this->assertGreaterThan(0, $propertyId);
            $this->assertGreaterThan(0, $userId);

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta origem auditoria Laravel',
                'tipo' => 'conta_corrente',
                'saldo_inicial' => 1000,
                'ativo' => 1,
            ]);
            $origemId = (int) DB::getPdo()->lastInsertId();

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta destino auditoria Laravel',
                'tipo' => 'conta_corrente',
                'saldo_inicial' => 100,
                'ativo' => 1,
            ]);
            $destinoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $userId))
                ->post('/financeiro/contas/transferencias', [
                    'origem' => $origemId,
                    'destino' => $destinoId,
                    'valor' => '250,75',
                    'data_transferencia' => '2026-07-09',
                    'descricao' => 'Transferencia teste auditoria',
                ])
                ->assertRedirect('/financeiro/contas');

            $transferenciaId = (int) DB::table('transferencias')
                ->where('propriedade_id', $propertyId)
                ->where('conta_origem_id', $origemId)
                ->where('conta_destino_id', $destinoId)
                ->value('id');
            $this->assertGreaterThan(0, $transferenciaId);

            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $userId,
                'acao' => 'registrar_transferencia_contas',
                'tabela' => 'transferencias',
                'registro_id' => $transferenciaId,
                'propriedade_id' => $propertyId,
            ]);

            $response = $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/contas')
                ->assertStatus(200);

            $response->assertSee('R$ 749,25');
            $response->assertSee('R$ 350,75');
        } finally {
            DB::rollBack();
        }
    }

    public function test_cash_book_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/livro-caixa')
            ->assertStatus(200)
            ->assertSee('Livro Caixa')
            ->assertSee('Resumo mensal')
            ->assertSee('Movimentos')
            ->assertSee('PDF');
    }

    public function test_cash_book_export_returns_csv_and_excel(): void
    {
        $csvResponse = $this->withSession($this->loggedSession())
            ->get('/financeiro/livro-caixa/exportar?formato=csv');

        $csvResponse->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString('FarmFort - Livro Caixa', $csv);
        $this->assertStringContainsString('Data;Tipo;Histórico;Pessoa;Documento;Categoria;Safra;Conta;Entrada;Saída;Comprovante;Status', $csv);

        $excelResponse = $this->withSession($this->loggedSession())
            ->get('/financeiro/livro-caixa/exportar?formato=xls');

        $excelResponse->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $html = $excelResponse->streamedContent();
        $this->assertStringContainsString('<h2>FarmFort - Livro Caixa</h2>', $html);
        $this->assertStringContainsString('<th>Histórico</th>', $html);

        $pdfResponse = $this->withSession($this->loggedSession())
            ->get('/financeiro/livro-caixa/exportar?formato=pdf');

        $pdfResponse->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $pdfResponse->getContent());
        $this->assertStringContainsString('FarmFort - Livro Caixa', $pdfResponse->getContent());
        $this->assertStringContainsString('%%EOF', $pdfResponse->getContent());
    }

    public function test_expense_analysis_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/analise-despesas')
            ->assertStatus(200)
            ->assertSee('Categorias e subcategorias')
            ->assertSee('Distribuição por grupo gerencial')
            ->assertSee('Detalhamento');
    }

    public function test_financial_entries_report_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/relatorio-lancamentos')
            ->assertStatus(200)
            ->assertSee('Relatorio de Lancamentos')
            ->assertSee('Filtros')
            ->assertSee('Lancamentos');
    }

    public function test_financial_entries_report_export_returns_csv(): void
    {
        $response = $this->withSession($this->loggedSession())
            ->get('/financeiro/relatorio-lancamentos/exportar?todos=1&formato=csv');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('FarmFort - Relatorio de Lancamentos', $csv);
        $this->assertStringContainsString('Data;Tipo;Descricao;Pessoa;Categoria;Safra;Conta;Valor;Vencimento/Recebimento;Status', $csv);
    }

    public function test_financial_entries_report_export_returns_excel(): void
    {
        $response = $this->withSession($this->loggedSession())
            ->get('/financeiro/relatorio-lancamentos/exportar?todos=1&formato=excel');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $html = $response->streamedContent();
        $this->assertStringContainsString('<h2>FarmFort - Relatorio de Lancamentos</h2>', $html);
        $this->assertStringContainsString('<th>Data</th>', $html);
    }

    public function test_financial_entries_report_export_returns_pdf(): void
    {
        $response = $this->withSession($this->loggedSession())
            ->get('/financeiro/relatorio-lancamentos/exportar?todos=1&formato=pdf');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertStringContainsString('%%EOF', $response->getContent());
    }

    public function test_financial_entries_report_pdf_exports_all_pages(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $categoryId = (int) DB::table('categorias')->insertGetId([
                'nome' => 'Categoria PDF paginado Laravel',
                'tipo' => 'outros',
                'cor' => '#2563eb',
                'icone' => 'circle',
                'ativo' => 1,
            ]);

            for ($item = 1; $item <= 35; $item++) {
                DB::table('despesas')->insert([
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoryId,
                    'descricao' => 'Despesa PDF paginado Laravel '.$item,
                    'fornecedor' => 'Fornecedor PDF',
                    'valor_total' => 10 + $item,
                    'data_lancamento' => '2026-07-'.str_pad((string) (($item % 28) + 1), 2, '0', STR_PAD_LEFT),
                    'data_vencimento' => '2026-08-01',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ]);
            }

            $response = $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/relatorio-lancamentos/exportar?todos=1&filtro=despesas&formato=pdf');

            $response->assertStatus(200)
                ->assertHeader('content-type', 'application/pdf');

            $pdf = $response->getContent();
            $this->assertStringStartsWith('%PDF-1.4', $pdf);
            $this->assertMatchesRegularExpression('/\/Count\s+(?:[2-9]|\d{2,})/', $pdf);
            $this->assertStringContainsString('Pagina 2 de', $pdf);
            $this->assertStringNotContainsString('Exibindo 28 de', $pdf);
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_account_store_creates_record(): void
    {
        DB::beginTransaction();

        try {
            $this->withSession($this->loggedSession())
                ->post('/financeiro/contas', [
                    'nome' => 'Conta teste Laravel',
                    'tipo' => 'conta_corrente',
                    'banco' => 'Banco Teste',
                    'agencia' => '0001',
                    'numero_conta' => '12345',
                    'saldo_inicial' => '1.234,56',
                ])
                ->assertRedirect('/financeiro/contas');

            $this->assertDatabaseHas('contas', [
                'nome' => 'Conta teste Laravel',
                'banco' => 'Banco Teste',
                'saldo_inicial' => '1234.56',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_account_can_be_edited_and_toggled(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta para editar Laravel',
                'tipo' => 'conta_corrente',
                'banco' => 'Banco Antigo',
                'agencia' => '0001',
                'numero_conta' => '123',
                'saldo_inicial' => 10,
                'ativo' => 1,
            ]);
            $contaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/contas/'.$contaId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar conta bancária')
                ->assertSee('Conta para editar Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/financeiro/contas/'.$contaId, [
                    'nome' => 'Conta atualizada Laravel',
                    'tipo' => 'caixa_interno',
                    'banco' => 'Banco Novo',
                    'agencia' => '0002',
                    'numero_conta' => '456',
                    'saldo_inicial' => '2.345,67',
                ])
                ->assertRedirect('/financeiro/contas');

            $this->assertDatabaseHas('contas', [
                'id' => $contaId,
                'nome' => 'Conta atualizada Laravel',
                'tipo' => 'caixa_interno',
                'banco' => 'Banco Novo',
                'saldo_inicial' => '2345.67',
                'ativo' => 1,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/contas/'.$contaId.'/alternar-status')
                ->assertRedirect('/financeiro/contas');

            $this->assertDatabaseHas('contas', [
                'id' => $contaId,
                'ativo' => 0,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_account_transfer_registers_and_updates_current_balance(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/contas')
            ->assertStatus(200);

        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta origem transferencia Laravel',
                'tipo' => 'conta_corrente',
                'banco' => 'Banco Origem',
                'agencia' => '0001',
                'numero_conta' => '111',
                'saldo_inicial' => 1000,
                'ativo' => 1,
            ]);
            $origemId = (int) DB::getPdo()->lastInsertId();

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta destino transferencia Laravel',
                'tipo' => 'conta_corrente',
                'banco' => 'Banco Destino',
                'agencia' => '0002',
                'numero_conta' => '222',
                'saldo_inicial' => 100,
                'ativo' => 1,
            ]);
            $destinoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/contas/transferencias', [
                    'origem' => $origemId,
                    'destino' => $destinoId,
                    'valor' => '250,50',
                    'data_transferencia' => '2026-07-09',
                    'descricao' => 'Aporte interno Laravel',
                ])
                ->assertRedirect('/financeiro/contas');

            $this->assertDatabaseHas('transferencias', [
                'propriedade_id' => $propertyId,
                'conta_origem_id' => $origemId,
                'conta_destino_id' => $destinoId,
                'valor' => '250.50',
                'data_transferencia' => '2026-07-09',
                'descricao' => 'Aporte interno Laravel',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/contas')
                ->assertStatus(200)
                ->assertSee('Conta origem transferencia Laravel')
                ->assertSee('Conta destino transferencia Laravel')
                ->assertSee('R$ 749,50')
                ->assertSee('R$ 350,50')
                ->assertSee('Aporte interno Laravel');
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_transfer_can_be_edited_considering_previous_transfer_value(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->userId();
            $this->assertGreaterThan(0, $propertyId);
            $this->assertGreaterThan(0, $userId);

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta origem edicao transferencia Laravel',
                'tipo' => 'conta_corrente',
                'banco' => 'Banco Origem',
                'saldo_inicial' => 100,
                'ativo' => 1,
            ]);
            $origemId = (int) DB::getPdo()->lastInsertId();

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta destino edicao transferencia Laravel',
                'tipo' => 'conta_corrente',
                'banco' => 'Banco Destino',
                'saldo_inicial' => 0,
                'ativo' => 1,
            ]);
            $destinoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $userId))
                ->post('/financeiro/contas/transferencias', [
                    'origem' => $origemId,
                    'destino' => $destinoId,
                    'valor' => '80,00',
                    'data_transferencia' => '2026-07-09',
                    'descricao' => 'Transferencia original Laravel',
                ])
                ->assertRedirect('/financeiro/contas');

            $transferenciaId = (int) DB::table('transferencias')
                ->where('propriedade_id', $propertyId)
                ->where('conta_origem_id', $origemId)
                ->where('conta_destino_id', $destinoId)
                ->value('id');

            $this->assertGreaterThan(0, $transferenciaId);

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $userId))
                ->put('/financeiro/contas/transferencias/'.$transferenciaId, [
                    'origem' => $origemId,
                    'destino' => $destinoId,
                    'valor' => '90,00',
                    'data_transferencia' => '2026-07-10',
                    'descricao' => 'Transferencia editada Laravel',
                ])
                ->assertRedirect('/financeiro/contas');

            $this->assertDatabaseHas('transferencias', [
                'id' => $transferenciaId,
                'valor' => '90.00',
                'data_transferencia' => '2026-07-10',
                'descricao' => 'Transferencia editada Laravel',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $userId,
                'acao' => 'editar_transferencia_contas',
                'tabela' => 'transferencias',
                'registro_id' => $transferenciaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/contas')
                ->assertStatus(200)
                ->assertSee('R$ 10,00')
                ->assertSee('R$ 90,00')
                ->assertSee('Transferencia editada Laravel');
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_movement_can_be_created_reconciled_and_ignored(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta movimento Laravel',
                'tipo' => 'conta_corrente',
                'banco' => 'Banco Movimento',
                'ativo' => 1,
            ]);
            $contaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/movimentacoes', [
                    'conta_id' => $contaId,
                    'data_movimento' => '2026-07-09',
                    'tipo' => 'entrada',
                    'descricao' => 'Movimentacao bancaria teste',
                    'valor' => '1.234,56',
                    'origem' => 'manual',
                ])
                ->assertRedirect('/financeiro/movimentacoes');

            $movimentacaoId = (int) DB::table('movimentacoes_bancarias')
                ->where('descricao', 'Movimentacao bancaria teste')
                ->value('id');
            $this->assertGreaterThan(0, $movimentacaoId);

            $this->assertDatabaseHas('movimentacoes_bancarias', [
                'id' => $movimentacaoId,
                'propriedade_id' => $propertyId,
                'conta_id' => $contaId,
                'valor' => '1234.56',
                'status' => 'pendente',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/movimentacoes/'.$movimentacaoId.'/conciliar')
                ->assertRedirect('/financeiro/movimentacoes');

            $this->assertDatabaseHas('movimentacoes_bancarias', [
                'id' => $movimentacaoId,
                'status' => 'conciliado',
            ]);

            DB::table('movimentacoes_bancarias')->insert([
                'propriedade_id' => $propertyId,
                'conta_id' => $contaId,
                'data_movimento' => '2026-07-10',
                'tipo' => 'saida',
                'descricao' => 'Movimentacao bancaria ignorada teste',
                'valor' => 100,
                'origem' => 'manual',
                'status' => 'pendente',
                'usuario_id' => $this->userId(),
            ]);
            $ignoredMovementId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/movimentacoes/'.$ignoredMovementId.'/ignorar')
                ->assertRedirect('/financeiro/movimentacoes');

            $this->assertDatabaseHas('movimentacoes_bancarias', [
                'id' => $ignoredMovementId,
                'status' => 'ignorado',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_movement_status_requires_current_property_and_totals_ignore_ignored_rows(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda movimentos Laravel',
                'ativo' => 1,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda movimentos externa Laravel',
                'ativo' => 1,
            ]);
            $externalPropertyId = (int) DB::getPdo()->lastInsertId();
            $userId = $this->userId();

            DB::table('usuario_propriedades')->insert([
                'usuario_id' => $userId,
                'propriedade_id' => $propertyId,
            ]);

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta movimentos totais Laravel',
                'tipo' => 'conta_corrente',
                'saldo_inicial' => 0,
                'ativo' => 1,
            ]);
            $contaId = (int) DB::getPdo()->lastInsertId();

            DB::table('contas')->insert([
                'propriedade_id' => $externalPropertyId,
                'nome' => 'Conta movimentos externa Laravel',
                'tipo' => 'conta_corrente',
                'saldo_inicial' => 0,
                'ativo' => 1,
            ]);
            $externalContaId = (int) DB::getPdo()->lastInsertId();

            DB::table('movimentacoes_bancarias')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'conta_id' => $contaId,
                    'data_movimento' => '2026-07-09',
                    'tipo' => 'entrada',
                    'descricao' => 'Entrada valida movimentos Laravel',
                    'valor' => 300,
                    'origem' => 'manual',
                    'status' => 'pendente',
                    'usuario_id' => $userId,
                ],
                [
                    'propriedade_id' => $propertyId,
                    'conta_id' => $contaId,
                    'data_movimento' => '2026-07-09',
                    'tipo' => 'saida',
                    'descricao' => 'Saida valida movimentos Laravel',
                    'valor' => 80,
                    'origem' => 'manual',
                    'status' => 'conciliado',
                    'usuario_id' => $userId,
                ],
                [
                    'propriedade_id' => $propertyId,
                    'conta_id' => $contaId,
                    'data_movimento' => '2026-07-09',
                    'tipo' => 'entrada',
                    'descricao' => 'Entrada ignorada movimentos Laravel',
                    'valor' => 999,
                    'origem' => 'manual',
                    'status' => 'ignorado',
                    'usuario_id' => $userId,
                ],
            ]);

            DB::table('movimentacoes_bancarias')->insert([
                'propriedade_id' => $externalPropertyId,
                'conta_id' => $externalContaId,
                'data_movimento' => '2026-07-09',
                'tipo' => 'entrada',
                'descricao' => 'Movimento externo preservado Laravel',
                'valor' => 500,
                'origem' => 'manual',
                'status' => 'pendente',
                'usuario_id' => $userId,
            ]);
            $externalMovementId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/movimentacoes/'.$externalMovementId.'/conciliar')
                ->assertNotFound();

            $this->assertDatabaseHas('movimentacoes_bancarias', [
                'id' => $externalMovementId,
                'propriedade_id' => $externalPropertyId,
                'status' => 'pendente',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/movimentacoes')
                ->assertStatus(200)
                ->assertSee('R$ 300,00')
                ->assertSee('R$ 80,00')
                ->assertSee('R$ 220,00')
                ->assertSee('Entrada ignorada movimentos Laravel');
        } finally {
            DB::rollBack();
        }
    }

    public function test_financial_agenda_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/agenda')
            ->assertStatus(200)
            ->assertSee('Agenda');
    }

    public function test_financial_agenda_can_filter_pending_bills(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria agenda boletos Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Boleto agenda Laravel',
                'fornecedor' => 'Fornecedor boleto',
                'valor_total' => 100,
                'data_lancamento' => now()->toDateString(),
                'data_vencimento' => now()->addDays(3)->toDateString(),
                'forma_pagamento' => 'boleto',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Pix agenda Laravel',
                'fornecedor' => 'Fornecedor pix',
                'valor_total' => 100,
                'data_lancamento' => now()->toDateString(),
                'data_vencimento' => now()->addDays(3)->toDateString(),
                'forma_pagamento' => 'pix',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita agenda filtro Laravel',
                'comprador' => 'Comprador filtro',
                'valor_total' => 100,
                'data_venda' => now()->toDateString(),
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/agenda?fp=boleto')
                ->assertStatus(200)
                ->assertSee('Filtrando boletos pendentes')
                ->assertSee('Boleto agenda Laravel')
                ->assertDontSee('Pix agenda Laravel')
                ->assertDontSee('Receita agenda filtro Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/agenda?alerta=boletos_vencendo')
                ->assertStatus(200)
                ->assertSee('Filtrando boletos que geraram o alerta')
                ->assertSee('Boleto agenda Laravel')
                ->assertSee('Pix agenda Laravel')
                ->assertDontSee('Receita agenda filtro Laravel');
        } finally {
            DB::rollBack();
        }
    }

    public function test_categories_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/categorias')
            ->assertStatus(200)
            ->assertSee('Categorias');
    }

    public function test_category_store_creates_record(): void
    {
        DB::beginTransaction();

        try {
            $this->withSession($this->loggedSession())
                ->post('/financeiro/categorias', [
                    'nome' => 'Categoria teste Laravel',
                    'tipo' => 'outros',
                    'cor' => '#6c757d',
                    'icone' => 'bi-tag',
                    'ativo' => 1,
                ])
                ->assertRedirect('/financeiro/categorias');

            $this->assertDatabaseHas('categorias', [
                'nome' => 'Categoria teste Laravel',
                'tipo' => 'outros',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_category_in_use_is_deactivated_instead_of_deleted(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria historico Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa vinculada a categoria',
                'valor_total' => 10,
                'data_lancamento' => '2026-07-09',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->delete('/financeiro/categorias/'.$categoriaId)
                ->assertRedirect('/financeiro/categorias');

            $this->assertDatabaseHas('categorias', [
                'id' => $categoriaId,
                'ativo' => 0,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_category_used_in_current_crop_season_is_not_deactivated(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria safra atual Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra categoria atual Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa categoria safra atual Laravel',
                'valor_total' => 10,
                'data_lancamento' => '2026-07-09',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);

            $this->withSession([...$this->loggedSession(propertyId: $propertyId), 'safra_id' => $safraId])
                ->delete('/financeiro/categorias/'.$categoriaId)
                ->assertRedirect('/financeiro/categorias')
                ->assertSessionHas('error');

            $this->assertDatabaseHas('categorias', [
                'id' => $categoriaId,
                'ativo' => 1,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_category_with_children_is_not_deleted_or_deactivated(): void
    {
        DB::beginTransaction();

        try {
            DB::table('categorias')->insert([
                'nome' => 'Categoria principal Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'categoria_pai_id' => $categoriaId,
                'nome' => 'Subcategoria Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);

            $this->withSession($this->loggedSession())
                ->delete('/financeiro/categorias/'.$categoriaId)
                ->assertRedirect('/financeiro/categorias')
                ->assertSessionHas('error');

            $this->assertDatabaseHas('categorias', [
                'id' => $categoriaId,
                'ativo' => 1,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_financial_agenda_can_mark_expense_as_paid(): void
    {
        $despesaId = DB::table('despesas')
            ->where('status_aprovacao', 'aprovada')
            ->where('status_pagamento', 'pendente')
            ->orderByDesc('id')
            ->value('id');

        if (! $despesaId) {
            $this->markTestSkipped('Sem despesa aprovada pendente para baixar pela agenda.');
        }

        DB::beginTransaction();

        try {
            $this->withSession($this->loggedSession())
                ->post('/financeiro/agenda/pagar', [
                    'id' => $despesaId,
                    'data_pagamento' => now()->format('Y-m-d'),
                ])
                ->assertRedirect('/financeiro/agenda');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'status_pagamento' => 'pago',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_financial_agenda_keeps_existing_account_when_none_is_selected(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria agenda Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('contas')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Conta agenda Laravel',
                'tipo' => 'conta_corrente',
                'banco' => 'Banco Agenda',
                'ativo' => 1,
            ]);
            $contaId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'conta_id' => $contaId,
                'descricao' => 'Despesa agenda manter conta',
                'valor_total' => 100,
                'data_lancamento' => '2026-07-09',
                'data_vencimento' => '2026-07-10',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/financeiro/agenda/pagar', [
                    'id' => $despesaId,
                    'data_pagamento' => '2026-07-11',
                    'conta_id' => '',
                ])
                ->assertRedirect('/financeiro/agenda');

            $this->assertDatabaseHas('despesas', [
                'id' => $despesaId,
                'status_pagamento' => 'pago',
                'data_pagamento' => '2026-07-11',
                'conta_id' => $contaId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_financial_agenda_writes_audit_for_payment_and_receipt(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->userId();
            $this->assertGreaterThan(0, $propertyId);
            $this->assertGreaterThan(0, $userId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria agenda auditoria Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa agenda auditoria Laravel',
                'valor_total' => 120,
                'data_lancamento' => '2026-07-09',
                'data_vencimento' => '2026-07-10',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);
            $despesaId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita agenda auditoria Laravel',
                'comprador' => 'Comprador agenda auditoria',
                'valor_total' => 220,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);
            $receitaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $userId))
                ->post('/financeiro/agenda/pagar', [
                    'id' => $despesaId,
                    'data_pagamento' => '2026-07-11',
                ])
                ->assertRedirect('/financeiro/agenda');

            $this->withSession($this->loggedSession(propertyId: $propertyId, userId: $userId))
                ->post('/financeiro/agenda/receber', [
                    'id' => $receitaId,
                    'data_recebimento' => '2026-07-12',
                ])
                ->assertRedirect('/financeiro/agenda');

            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $userId,
                'acao' => 'agenda_pagar_despesa',
                'tabela' => 'despesas',
                'registro_id' => $despesaId,
                'propriedade_id' => $propertyId,
                'detalhes' => 'Pagamento confirmado pela agenda',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $userId,
                'acao' => 'agenda_receber_receita',
                'tabela' => 'receitas',
                'registro_id' => $receitaId,
                'propriedade_id' => $propertyId,
                'detalhes' => 'Recebimento confirmado pela agenda',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_product_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/produtos/novo')
            ->assertStatus(200)
            ->assertSee('Novo produto');
    }

    public function test_products_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/produtos')
            ->assertStatus(200)
            ->assertSee('Estoque de Produtos')
            ->assertSee('Produtos');
    }

    public function test_product_can_be_edited_and_toggled(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/produtos', [
                    'descricao_generica' => 'Produto estoque criado Laravel',
                    'codigo_interno' => 'EST-LARAVEL-CREATE',
                    'unidade_medida' => 'kg',
                ])
                ->assertRedirect('/produtos');

            $produtoCriadoId = (int) DB::table('produtos')
                ->where('propriedade_id', $propertyId)
                ->where('codigo_interno', 'EST-LARAVEL-CREATE')
                ->value('id');
            $this->assertGreaterThan(0, $produtoCriadoId);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'criar_produto_estoque',
                'tabela' => 'produtos',
                'registro_id' => $produtoCriadoId,
                'propriedade_id' => $propertyId,
            ]);

            DB::table('produtos')->insert([
                'propriedade_id' => $propertyId,
                'descricao_generica' => 'Produto estoque Laravel',
                'codigo_interno' => 'EST-LARAVEL-001',
                'unidade_medida' => 'kg',
                'ativo' => 1,
            ]);
            $produtoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/produtos/'.$produtoId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar produto')
                ->assertSee('Produto estoque Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/produtos/'.$produtoId, [
                    'descricao_generica' => 'Produto estoque Laravel atualizado',
                    'codigo_interno' => 'EST-LARAVEL-002',
                    'codigo_fornecedor' => 'FOR-002',
                    'unidade_medida' => 'l',
                    'marca' => 'Marca teste',
                    'ncm' => '12345678',
                    'cest' => '1234567',
                    'cfop_entrada' => '1102',
                    'cst_icms' => '00',
                    'csosn' => '102',
                    'cst_pis' => '01',
                    'cst_cofins' => '01',
                    'aliquota_icms' => '17,50',
                    'aliquota_pis' => '1,65',
                    'aliquota_cofins' => '7,60',
                    'aliquota_ipi' => '3,25',
                    'origem_mercadoria' => '0',
                    'tipo_item' => '00',
                    'codigo_anp' => '620505001',
                    'descricao_interna' => 'Descricao interna fiscal Laravel',
                    'informacoes_fiscais' => 'Informacao fiscal Laravel',
                    'observacoes_fiscais' => 'Observacao fiscal Laravel',
                ])
                ->assertRedirect('/produtos');

            $this->assertDatabaseHas('produtos', [
                'id' => $produtoId,
                'descricao_generica' => 'Produto estoque Laravel atualizado',
                'codigo_interno' => 'EST-LARAVEL-002',
                'unidade_medida' => 'l',
                'ncm' => '12345678',
                'cest' => '1234567',
                'cfop_entrada' => '1102',
                'cst_icms' => '00',
                'csosn' => '102',
                'cst_pis' => '01',
                'cst_cofins' => '01',
                'aliquota_icms' => '17.5000',
                'aliquota_pis' => '1.6500',
                'aliquota_cofins' => '7.6000',
                'aliquota_ipi' => '3.2500',
                'origem_mercadoria' => '0',
                'tipo_item' => '00',
                'codigo_anp' => '620505001',
                'descricao_interna' => 'Descricao interna fiscal Laravel',
                'informacoes_fiscais' => 'Informacao fiscal Laravel',
                'observacoes_fiscais' => 'Observacao fiscal Laravel',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'editar_produto_estoque',
                'tabela' => 'produtos',
                'registro_id' => $produtoId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/produtos/'.$produtoId.'/alternar-status')
                ->assertRedirect('/produtos');

            $this->assertDatabaseHas('produtos', [
                'id' => $produtoId,
                'ativo' => 0,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'alterar_status_produto_estoque',
                'tabela' => 'produtos',
                'registro_id' => $produtoId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_asset_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/patrimonio/novo')
            ->assertStatus(200)
            ->assertSee('Novo patrimônio');
    }

    public function test_crop_season_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/safras/novo')
            ->assertStatus(200)
            ->assertSee('Nova safra');
    }

    public function test_crop_seasons_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/safras')
            ->assertStatus(200)
            ->assertSee('Safras cadastradas')
            ->assertSee('Filtros');
    }

    public function test_crop_season_can_be_edited_archived_and_unarchived(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $talhaoId = (int) DB::table('talhoes')
                ->where('propriedade_id', $propertyId)
                ->where('ativo', 1)
                ->orderBy('id')
                ->value('id');

            $this->assertGreaterThan(0, $propertyId);
            $this->assertGreaterThan(0, $talhaoId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/safras', [
                    'descricao' => 'Safra criada auditoria Laravel',
                    'safra_referencia' => 'primeira',
                    'data_inicio' => '2026-07-01',
                    'status' => 'planejamento',
                ])
                ->assertRedirect('/safras');

            $safraCriadaId = (int) DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->where('descricao', 'Safra criada auditoria Laravel')
                ->value('id');
            $this->assertGreaterThan(0, $safraCriadaId);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'salvar_safra',
                'tabela' => 'safras',
                'registro_id' => $safraCriadaId,
                'propriedade_id' => $propertyId,
            ]);

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra editar Laravel',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2027-06-30',
                'area_plantada' => 10,
                'producao_estimada' => 20,
                'preco_estimado' => 100,
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/safras/'.$safraId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar safra')
                ->assertSee('Safra editar Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/safras/'.$safraId, [
                    'descricao' => 'Safra atualizada Laravel',
                    'safra_referencia' => 'segunda',
                    'data_inicio' => '2026-08-01',
                    'data_fim' => '2027-05-31',
                    'area_plantada' => '15,50',
                    'producao_estimada' => '45,25',
                    'preco_estimado' => '120,30',
                    'status' => 'em_andamento',
                    'observacoes' => 'Atualizada pelo teste',
                    'talhoes' => [$talhaoId],
                ])
                ->assertRedirect('/safras?status=todas');

            $this->assertDatabaseHas('safras', [
                'id' => $safraId,
                'descricao' => 'Safra atualizada Laravel',
                'safra_referencia' => 'segunda',
                'status' => 'em_andamento',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'salvar_safra',
                'tabela' => 'safras',
                'registro_id' => $safraId,
                'propriedade_id' => $propertyId,
            ]);

            $this->assertDatabaseHas('safra_talhoes', [
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/safras/'.$safraId.'/status', ['status' => 'encerrada'])
                ->assertRedirect('/safras?status=todas');

            $this->assertDatabaseHas('safras', [
                'id' => $safraId,
                'status' => 'encerrada',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'arquivar_safra',
                'tabela' => 'safras',
                'registro_id' => $safraId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/safras/'.$safraId.'/status', ['status' => 'planejamento'])
                ->assertRedirect('/safras?status=todas');

            $this->assertDatabaseHas('safras', [
                'id' => $safraId,
                'status' => 'planejamento',
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'desarquivar_safra',
                'tabela' => 'safras',
                'registro_id' => $safraId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_crop_season_rejects_field_already_used_in_open_running_season(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda conflito safra Laravel',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Teste',
                'plano' => 'premium',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao conflito safra Laravel',
                'area' => 50,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra em andamento conflito Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'em_andamento',
            ]);
            $safraEmAndamentoId = (int) DB::getPdo()->lastInsertId();

            DB::table('safra_talhoes')->insert([
                'safra_id' => $safraEmAndamentoId,
                'talhao_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/safras', [
                    'descricao' => 'Safra bloqueada conflito Laravel',
                    'safra_referencia' => 'segunda',
                    'data_inicio' => '2026-08-01',
                    'status' => 'planejamento',
                    'talhoes' => [$talhaoId],
                ])
                ->assertStatus(422);

            $this->assertDatabaseMissing('safras', [
                'propriedade_id' => $propertyId,
                'descricao' => 'Safra bloqueada conflito Laravel',
            ]);

            DB::table('safra_talhoes')
                ->where('safra_id', $safraEmAndamentoId)
                ->where('talhao_id', $talhaoId)
                ->update(['colheita_finalizada_em' => now()]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/safras', [
                    'descricao' => 'Safra liberada conflito Laravel',
                    'safra_referencia' => 'segunda',
                    'data_inicio' => '2026-08-01',
                    'status' => 'planejamento',
                    'talhoes' => [$talhaoId],
                ])
                ->assertRedirect('/safras');

            $this->assertDatabaseHas('safras', [
                'propriedade_id' => $propertyId,
                'descricao' => 'Safra liberada conflito Laravel',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_crop_season_definitive_delete_requires_password_and_no_linked_data(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $talhaoId = (int) DB::table('talhoes')
                ->where('propriedade_id', $propertyId)
                ->where('ativo', 1)
                ->orderBy('id')
                ->value('id');
            $userId = $this->userId();

            $this->assertGreaterThan(0, $propertyId);
            $this->assertGreaterThan(0, $talhaoId);
            $this->assertGreaterThan(0, $userId);

            DB::table('usuarios')->where('id', $userId)->update([
                'senha' => password_hash('senha-safra-delete', PASSWORD_DEFAULT),
            ]);

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra bloqueada delete Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $blockedSafraId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Despesa bloqueia delete safra',
                'fornecedor' => 'Teste',
                'categoria_id' => (int) DB::table('categorias')->orderBy('id')->value('id'),
                'safra_id' => $blockedSafraId,
                'valor_total' => 10,
                'data_lancamento' => '2026-07-02',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->delete('/safras/'.$blockedSafraId, ['senha_exclusao' => 'senha-safra-delete'])
                ->assertRedirect('/safras?status=todas')
                ->assertSessionHasErrors();

            $this->assertDatabaseHas('safras', ['id' => $blockedSafraId]);

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'segunda',
                'descricao' => 'Safra limpa delete Laravel',
                'data_inicio' => '2026-08-01',
                'status' => 'planejamento',
            ]);
            $cleanSafraId = (int) DB::getPdo()->lastInsertId();

            DB::table('safra_talhoes')->insert([
                'safra_id' => $cleanSafraId,
                'talhao_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->delete('/safras/'.$cleanSafraId, ['senha_exclusao' => 'senha-errada'])
                ->assertRedirect('/safras?status=todas')
                ->assertSessionHasErrors();

            $this->assertDatabaseHas('safras', ['id' => $cleanSafraId]);

            $this->withSession([...$this->loggedSession(propertyId: $propertyId), 'safra_id' => $cleanSafraId])
                ->delete('/safras/'.$cleanSafraId, ['senha_exclusao' => 'senha-safra-delete'])
                ->assertRedirect('/safras?status=todas')
                ->assertSessionMissing('safra_id');

            $this->assertDatabaseMissing('safras', ['id' => $cleanSafraId]);
            $this->assertDatabaseMissing('safra_talhoes', ['safra_id' => $cleanSafraId]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'excluir_safra_sem_dados',
                'tabela' => 'safras',
                'registro_id' => $cleanSafraId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/talhoes/novo')
            ->assertStatus(200)
            ->assertSee('Novo talhão');
    }

    public function test_fields_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/talhoes')
            ->assertStatus(200)
            ->assertSee('Talhões da propriedade')
            ->assertSee('Filtros');
    }

    public function test_field_map_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/talhoes/mapa')
            ->assertStatus(200)
            ->assertSee('Mapa');
    }

    public function test_field_polygon_can_create_and_update_from_map(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $points = json_encode([
                ['lat' => -15.7000, 'lng' => -47.9000],
                ['lat' => -15.7000, 'lng' => -47.8900],
                ['lat' => -15.6900, 'lng' => -47.8900],
                ['lat' => -15.6900, 'lng' => -47.9000],
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/mapa', [
                    'nome' => 'Talhao mapa Laravel',
                    'descricao' => 'Criado pelo desenho',
                    'coordenadas_json' => $points,
                ])
                ->assertRedirect('/talhoes/mapa');

            $talhaoId = (int) DB::table('talhoes')->where('nome', 'Talhao mapa Laravel')->value('id');
            $this->assertGreaterThan(0, $talhaoId);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'criar_talhao_mapa',
                'tabela' => 'talhoes',
                'registro_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);

            $updatedPoints = json_encode([
                ['lat' => -15.7100, 'lng' => -47.9100],
                ['lat' => -15.7100, 'lng' => -47.8950],
                ['lat' => -15.6950, 'lng' => -47.8950],
                ['lat' => -15.6950, 'lng' => -47.9100],
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/mapa', [
                    'talhao_id' => $talhaoId,
                    'nome' => 'Talhao mapa atualizado',
                    'descricao' => 'Atualizado pelo desenho',
                    'coordenadas_json' => $updatedPoints,
                ])
                ->assertRedirect('/talhoes/mapa');

            $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
            $this->assertSame('Talhao mapa atualizado', $talhao->nome);
            $this->assertSame('polygon', $talhao->geometria_tipo);
            $this->assertGreaterThan(0, (float) $talhao->area);
            $this->assertStringContainsString('-15.71', $talhao->coordenadas_json);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'salvar_poligono_talhao',
                'tabela' => 'talhoes',
                'registro_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_map_can_manage_pivot_and_exclusions(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $points = json_encode([
                ['lat' => -15.7200, 'lng' => -47.9200],
                ['lat' => -15.7200, 'lng' => -47.9000],
                ['lat' => -15.7000, 'lng' => -47.9000],
                ['lat' => -15.7000, 'lng' => -47.9200],
            ]);

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao mapa recursos',
                'area' => 480,
                'area_bruta' => 480,
                'area_excluida_ha' => 0,
                'descricao' => 'Mapa recursos',
                'latitude' => -15.71,
                'longitude' => -47.91,
                'geometria_tipo' => 'polygon',
                'coordenadas_json' => $points,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/'.$talhaoId.'/mapa/dados', [
                    'nome' => 'Talhao mapa recursos editado',
                    'area' => '481,5',
                    'descricao' => 'Editado no mapa',
                ])
                ->assertRedirect('/talhoes/mapa');

            $this->assertDatabaseHas('talhoes', [
                'id' => $talhaoId,
                'nome' => 'Talhao mapa recursos editado',
                'area' => '480.00',
                'area_bruta' => '480.00',
                'area_excluida_ha' => '0.00',
                'descricao' => 'Editado no mapa',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/'.$talhaoId.'/mapa/pivo', [
                    'pivo_lat' => '-15.71000000',
                    'pivo_lng' => '-47.91000000',
                    'pivo_raio_m' => '250',
                ])
                ->assertRedirect('/talhoes/mapa');

            $this->assertDatabaseHas('talhoes', [
                'id' => $talhaoId,
                'pivo_ativo' => 1,
                'pivo_raio_m' => '250.00',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->delete('/talhoes/'.$talhaoId.'/mapa/pivo')
                ->assertRedirect('/talhoes/mapa');

            $this->assertSame(0, (int) DB::table('talhoes')->where('id', $talhaoId)->value('pivo_ativo'));

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/mapa/pivo', [
                    'nome' => 'Pivo novo via mapa',
                    'pivo_lat' => '-15.715',
                    'pivo_lng' => '-47.915',
                    'pivo_raio_m' => '100',
                ])
                ->assertRedirect('/talhoes/mapa');

            $this->assertDatabaseHas('talhoes', [
                'propriedade_id' => $propertyId,
                'nome' => 'Pivo novo via mapa',
                'pivo_ativo' => 1,
                'geometria_tipo' => 'polygon',
            ]);

            $exclusion = json_encode([
                ['lat' => -15.7180, 'lng' => -47.9180],
                ['lat' => -15.7180, 'lng' => -47.9140],
                ['lat' => -15.7140, 'lng' => -47.9140],
                ['lat' => -15.7140, 'lng' => -47.9180],
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/'.$talhaoId.'/mapa/exclusoes', [
                    'exclusao_json' => $exclusion,
                ])
                ->assertRedirect('/talhoes/mapa');

            $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
            $this->assertNotEmpty($talhao->exclusoes_json);
            $this->assertGreaterThan(0, (float) $talhao->area_excluida_ha);
            $this->assertLessThan((float) $talhao->area_bruta, (float) $talhao->area);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->delete('/talhoes/'.$talhaoId.'/mapa/exclusoes')
                ->assertRedirect('/talhoes/mapa');

            $talhao = DB::table('talhoes')->where('id', $talhaoId)->first();
            $this->assertNull($talhao->exclusoes_json);
            $this->assertSame(0.0, (float) $talhao->area_excluida_ha);

            foreach (['editar_talhao_mapa', 'salvar_pivo_talhao', 'remover_pivo_talhao', 'criar_pivo_talhao_mapa', 'criar_exclusao_talhao', 'limpar_exclusoes_talhao'] as $acao) {
                $this->assertDatabaseHas('logs_auditoria', [
                    'acao' => $acao,
                    'tabela' => 'talhoes',
                    'propriedade_id' => $propertyId,
                ]);
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_geometry_can_be_exported(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao export Laravel',
                'area' => 123.45,
                'area_bruta' => 123.45,
                'area_excluida_ha' => 0,
                'descricao' => 'Exportado pelo teste',
                'ativo' => 1,
                'latitude' => -15.695,
                'longitude' => -47.895,
                'geometria_tipo' => 'polygon',
                'coordenadas_json' => json_encode([
                    ['lat' => -15.7000, 'lng' => -47.9000],
                    ['lat' => -15.7000, 'lng' => -47.8900],
                    ['lat' => -15.6900, 'lng' => -47.8900],
                    ['lat' => -15.6900, 'lng' => -47.9000],
                ]),
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/talhoes')
                ->assertStatus(200)
                ->assertSee('Exportar KML')
                ->assertSee('SHP');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/talhoes/exportar/kml')
                ->assertStatus(200)
                ->assertHeader('content-type', 'application/vnd.google-earth.kml+xml; charset=UTF-8')
                ->assertSee('Talhao export Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/talhoes/'.$talhaoId.'/exportar?formato=kml')
                ->assertStatus(200)
                ->assertHeader('content-type', 'application/vnd.google-earth.kml+xml; charset=UTF-8')
                ->assertSee('<Polygon>', false);

            if (class_exists(\ZipArchive::class)) {
                $this->withSession($this->loggedSession(propertyId: $propertyId))
                    ->get('/talhoes/'.$talhaoId.'/exportar?formato=kmz')
                    ->assertStatus(200)
                    ->assertDownload('farmfort_talhao_export_laravel.kmz');

                $this->withSession($this->loggedSession(propertyId: $propertyId))
                    ->get('/talhoes/'.$talhaoId.'/exportar?formato=shp')
                    ->assertStatus(200)
                    ->assertDownload('farmfort_talhao_export_laravel_shp.zip');
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_can_be_imported_from_kml(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $kml = <<<'KML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Talhao arquivo ignorado</name>
      <Polygon>
        <outerBoundaryIs>
          <LinearRing>
            <coordinates>
              -47.9000,-15.7000,0 -47.8900,-15.7000,0 -47.8900,-15.6900,0 -47.9000,-15.6900,0 -47.9000,-15.7000,0
            </coordinates>
          </LinearRing>
        </outerBoundaryIs>
      </Polygon>
    </Placemark>
  </Document>
</kml>
KML;

            $file = UploadedFile::fake()->createWithContent('talhao_import_laravel.kml', $kml);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/importar-geo', [
                    'geo' => $file,
                    'nome_importacao' => 'Talhao importado KML Laravel',
                ])
                ->assertRedirect('/talhoes?status=todos')
                ->assertSessionHas('success');

            $talhao = DB::table('talhoes')
                ->where('propriedade_id', $propertyId)
                ->where('nome', 'Talhao importado KML Laravel')
                ->first();

            $this->assertNotNull($talhao);
            $this->assertSame('polygon', $talhao->geometria_tipo);
            $this->assertGreaterThan(0, (float) $talhao->area);
            $this->assertStringContainsString('-15.7', $talhao->coordenadas_json);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'importar_geometria',
                'tabela' => 'talhoes',
                'registro_id' => 0,
                'propriedade_id' => $propertyId,
            ]);

            if (! empty($talhao->kml_arquivo)) {
                @unlink(public_path($talhao->kml_arquivo));
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_can_be_imported_from_shp(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $file = UploadedFile::fake()->createWithContent(
                'talhao_point_laravel.shp',
                $this->simplePointShp(-47.895, -15.695)
            );

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/importar-geo', [
                    'geo' => $file,
                    'nome_importacao' => 'Talhao importado SHP Laravel',
                ])
                ->assertRedirect('/talhoes?status=todos')
                ->assertSessionHas('success');

            $talhao = DB::table('talhoes')
                ->where('propriedade_id', $propertyId)
                ->where('nome', 'Talhao importado SHP Laravel')
                ->first();

            $this->assertNotNull($talhao);
            $this->assertSame('point', $talhao->geometria_tipo);
            $this->assertSame('-15.69500000', number_format((float) $talhao->latitude, 8, '.', ''));
            $this->assertSame('-47.89500000', number_format((float) $talhao->longitude, 8, '.', ''));
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'importar_geometria',
                'tabela' => 'talhoes',
                'registro_id' => 0,
                'propriedade_id' => $propertyId,
            ]);

            if (! empty($talhao->kml_arquivo)) {
                @unlink(public_path($talhao->kml_arquivo));
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_fields_can_be_merged(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao destino merge Laravel',
                'area' => 10,
                'area_bruta' => 10,
                'area_excluida_ha' => 0,
                'ativo' => 1,
            ]);
            $destinoId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao origem merge Laravel',
                'area' => 5,
                'area_bruta' => 5,
                'area_excluida_ha' => 0,
                'ativo' => 1,
            ]);
            $origemId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra merge Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'em_andamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('chuvas')->insert([
                'propriedade_id' => $propertyId,
                'talhao_id' => $origemId,
                'data_chuva' => '2026-07-09',
                'volume_mm' => 20,
                'fonte' => 'manual',
            ]);

            DB::table('atividades_campo')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'talhao_id' => $origemId,
                'tipo' => 'manejo',
                'data_inicio' => '2026-07-09',
                'status' => 'planejada',
                'descricao' => 'Atividade merge Laravel',
            ]);

            DB::table('safra_talhoes')->insert([
                'safra_id' => $safraId,
                'talhao_id' => $origemId,
                'propriedade_id' => $propertyId,
                'colheita_finalizada_em' => '2026-07-09 10:00:00',
                'colheita_finalizada_por' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/unificar', [
                    'talhao_destino_id' => $destinoId,
                    'talhoes_origem' => [$origemId],
                    'somar_area' => '1',
                ])
                ->assertRedirect('/talhoes?status=todos');

            $destino = DB::table('talhoes')->where('id', $destinoId)->first();
            $this->assertSame('15.00', number_format((float) $destino->area, 2, '.', ''));
            $this->assertStringContainsString('Talhao origem merge Laravel', (string) $destino->descricao);

            $this->assertDatabaseHas('talhoes', [
                'id' => $origemId,
                'ativo' => 0,
            ]);
            $this->assertDatabaseHas('chuvas', [
                'propriedade_id' => $propertyId,
                'talhao_id' => $destinoId,
                'volume_mm' => 20,
            ]);
            $this->assertDatabaseHas('atividades_campo', [
                'propriedade_id' => $propertyId,
                'talhao_id' => $destinoId,
                'descricao' => 'Atividade merge Laravel',
            ]);
            $this->assertDatabaseHas('safra_talhoes', [
                'safra_id' => $safraId,
                'talhao_id' => $destinoId,
                'propriedade_id' => $propertyId,
            ]);
            $this->assertDatabaseMissing('safra_talhoes', [
                'safra_id' => $safraId,
                'talhao_id' => $origemId,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'unificar_talhoes',
                'tabela' => 'talhoes',
                'registro_id' => $destinoId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_can_be_edited_and_toggled(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao editar Laravel',
                'area' => 10,
                'area_bruta' => 12,
                'area_excluida_ha' => 2,
                'descricao' => 'Criado pelo teste',
                'ativo' => 1,
                'latitude' => -15.1,
                'longitude' => -47.1,
                'geometria_tipo' => 'point',
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/talhoes/'.$talhaoId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar talhão')
                ->assertSee('Talhao editar Laravel');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/talhoes/'.$talhaoId, [
                    'nome' => 'Talhao atualizado Laravel',
                    'area' => '20,50',
                    'area_bruta' => '21,00',
                    'area_excluida_ha' => '0,50',
                    'descricao' => 'Atualizado pelo teste',
                    'latitude' => '-15.200000',
                    'longitude' => '-47.200000',
                    'geometria_tipo' => 'polygon',
                    'pivo_ativo' => 1,
                    'pivo_lat' => '-15.210000',
                    'pivo_lng' => '-47.210000',
                    'pivo_raio_m' => '300',
                    'pivo_area_ha' => '28,27',
                ])
                ->assertRedirect('/talhoes?status=todos');

            $this->assertDatabaseHas('talhoes', [
                'id' => $talhaoId,
                'nome' => 'Talhao atualizado Laravel',
                'geometria_tipo' => 'polygon',
                'pivo_ativo' => 1,
                'ativo' => 1,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'salvar_talhao',
                'tabela' => 'talhoes',
                'registro_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/'.$talhaoId.'/alternar-status')
                ->assertRedirect('/talhoes?status=todos');

            $this->assertDatabaseHas('talhoes', [
                'id' => $talhaoId,
                'ativo' => 0,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'desativar_talhao',
                'tabela' => 'talhoes',
                'registro_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/'.$talhaoId.'/alternar-status')
                ->assertRedirect('/talhoes?status=todos');

            $this->assertDatabaseHas('talhoes', [
                'id' => $talhaoId,
                'ativo' => 1,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'reativar_talhao',
                'tabela' => 'talhoes',
                'registro_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_with_active_crop_links_cannot_be_disabled(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao bloqueado Laravel',
                'area' => 10,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra bloqueio Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'em_andamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('safra_talhoes')->insert([
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/'.$talhaoId.'/alternar-status')
                ->assertRedirect('/talhoes?status=todos')
                ->assertSessionHas('error');

            $this->assertDatabaseHas('talhoes', [
                'id' => $talhaoId,
                'ativo' => 1,
            ]);

            DB::table('safra_talhoes')
                ->where('safra_id', $safraId)
                ->where('talhao_id', $talhaoId)
                ->update(['colheita_finalizada_em' => '2026-07-09 12:00:00']);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/'.$talhaoId.'/alternar-status')
                ->assertRedirect('/talhoes?status=todos')
                ->assertSessionHas('success');

            $this->assertDatabaseHas('talhoes', [
                'id' => $talhaoId,
                'ativo' => 0,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_rain_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/talhoes/chuva')
            ->assertStatus(200)
            ->assertSee('Chuva');
    }

    public function test_rain_store_creates_record(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/chuva', [
                    'data_chuva' => now()->format('Y-m-d'),
                    'volume_mm' => '12,5',
                    'fonte' => 'manual',
                    'observacoes' => 'Registro criado por teste',
                ])
                ->assertRedirect('/talhoes/chuva?ano='.now()->format('Y'));

            $this->assertDatabaseHas('chuvas', [
                'data_chuva' => now()->format('Y-m-d'),
                'fonte' => 'manual',
            ]);

            DB::table('propriedades')->insert([
                'nome' => 'Propriedade chuva externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'nome' => 'Talhao chuva externo',
                'area' => 1,
                'ativo' => 1,
            ]);
            $talhaoExternoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/chuva', [
                    'talhao_id' => $talhaoExternoId,
                    'data_chuva' => now()->format('Y-m-d'),
                    'volume_mm' => '8,5',
                    'fonte' => 'manual',
                    'observacoes' => 'Talhao externo deve virar geral',
                ])
                ->assertRedirect('/talhoes/chuva?ano='.now()->format('Y'));

            $this->assertDatabaseHas('chuvas', [
                'propriedade_id' => $propertyId,
                'talhao_id' => null,
                'observacoes' => 'Talhao externo deve virar geral',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_activities_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/talhoes/atividades')
            ->assertStatus(200)
            ->assertSee('Atividades');
    }

    public function test_field_activity_store_creates_record(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/atividades', [
                    'tipo' => 'manejo',
                    'data_inicio' => now()->format('Y-m-d'),
                    'status' => 'planejada',
                    'descricao' => 'Atividade teste Laravel',
                    'responsavel' => 'Equipe teste',
                    'produto' => 'Produto teste',
                    'dose' => '1 L/ha',
                    'custo_estimado' => '120,00',
                ])
                ->assertRedirect('/talhoes/atividades');

            $this->assertDatabaseHas('atividades_campo', [
                'descricao' => 'Atividade teste Laravel',
                'tipo' => 'manejo',
                'status' => 'planejada',
            ]);

            $atividadeId = (int) DB::table('atividades_campo')
                ->where('propriedade_id', $propertyId)
                ->where('descricao', 'Atividade teste Laravel')
                ->value('id');
            $this->assertGreaterThan(0, $atividadeId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/atividades/'.$atividadeId.'/status', ['status' => 'concluida'])
                ->assertRedirect('/talhoes/atividades');

            $this->assertDatabaseHas('atividades_campo', [
                'id' => $atividadeId,
                'status' => 'concluida',
            ]);

            DB::table('propriedades')->insert([
                'nome' => 'Propriedade atividade externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra externa atividade',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraExternaId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'nome' => 'Talhao externo atividade',
                'area' => 1,
                'ativo' => 1,
            ]);
            $talhaoExternoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/atividades', [
                    'safra_id' => $safraExternaId,
                    'talhao_id' => $talhaoExternoId,
                    'tipo' => 'manejo',
                    'data_inicio' => now()->format('Y-m-d'),
                    'status' => 'planejada',
                    'descricao' => 'Atividade com vinculos externos',
                ])
                ->assertRedirect('/talhoes/atividades');

            $this->assertDatabaseHas('atividades_campo', [
                'propriedade_id' => $propertyId,
                'safra_id' => null,
                'talhao_id' => null,
                'descricao' => 'Atividade com vinculos externos',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_rain_page_summarizes_year_and_month_by_property(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao chuva resumo Laravel',
                'area' => 10,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            DB::table('chuvas')->insert([
                'propriedade_id' => $propertyId,
                'talhao_id' => $talhaoId,
                'data_chuva' => '2026-01-15',
                'volume_mm' => 10,
                'fonte' => 'manual',
                'observacoes' => 'Chuva resumo 1',
            ]);
            DB::table('chuvas')->insert([
                'propriedade_id' => $propertyId,
                'talhao_id' => null,
                'data_chuva' => '2026-01-15',
                'volume_mm' => 25,
                'fonte' => 'pluviometro',
                'observacoes' => 'Chuva resumo 2',
            ]);
            DB::table('propriedades')->insert([
                'nome' => 'Propriedade chuva externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('chuvas')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'talhao_id' => null,
                'data_chuva' => '2026-01-15',
                'volume_mm' => 999,
                'fonte' => 'manual',
                'observacoes' => 'Chuva externa nao deve aparecer',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/talhoes/chuva?ano=2026')
                ->assertStatus(200)
                ->assertSee('35,0 mm')
                ->assertSee('25,0 mm')
                ->assertSee('Talhao chuva resumo Laravel')
                ->assertSee('Chuva resumo 1')
                ->assertDontSee('999,0 mm')
                ->assertDontSee('Chuva externa nao deve aparecer');
        } finally {
            DB::rollBack();
        }
    }

    public function test_rain_store_ignores_external_field_link(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('propriedades')->insert([
                'nome' => 'Propriedade chuva talhao externo',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'nome' => 'Talhao chuva externo',
                'area' => 10,
                'ativo' => 1,
            ]);
            $talhaoExternoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/talhoes/chuva', [
                    'talhao_id' => $talhaoExternoId,
                    'data_chuva' => '2026-02-10',
                    'volume_mm' => '12,50',
                    'fonte' => 'manual',
                    'observacoes' => 'Chuva sem talhao externo',
                ])
                ->assertRedirect('/talhoes/chuva?ano=2026');

            $this->assertDatabaseHas('chuvas', [
                'propriedade_id' => $propertyId,
                'talhao_id' => null,
                'data_chuva' => '2026-02-10',
                'volume_mm' => '12.50',
                'observacoes' => 'Chuva sem talhao externo',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_harvest_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/colheita/novo')
            ->assertStatus(200)
            ->assertSee('Nova colheita');
    }

    public function test_harvest_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/colheita')
            ->assertStatus(200)
            ->assertSee('Cargas de colheita')
            ->assertSee('Filtros');
    }

    public function test_harvest_store_validates_property_links(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra colheita valida Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao colheita Laravel',
                'area' => 10,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Propriedade colheita externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra externa colheita',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraExternaId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'nome' => 'Talhao externo colheita',
                'area' => 1,
                'ativo' => 1,
            ]);
            $talhaoExternoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/colheita', [
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoId,
                    'ticket_numero' => 'COL-VALIDA',
                    'data_colheita' => '2026-07-09',
                    'peso_bruto_kg' => '1200',
                    'tara_kg' => '100',
                    'desconto_kg' => '50',
                ])
                ->assertRedirect('/colheita?safra_id='.$safraId);

            $this->assertDatabaseHas('colheita_talhoes', [
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'ticket_numero' => 'COL-VALIDA',
                'peso_final_kg' => 1050,
            ]);
            $colheitaId = (int) DB::table('colheita_talhoes')
                ->where('propriedade_id', $propertyId)
                ->where('ticket_numero', 'COL-VALIDA')
                ->value('id');
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'salvar_colheita',
                'tabela' => 'colheita_talhoes',
                'registro_id' => $colheitaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/colheita/novo')
                ->post('/colheita', [
                    'safra_id' => $safraExternaId,
                    'talhao_id' => $talhaoId,
                    'ticket_numero' => 'COL-SAFRA-BLOQUEADA',
                    'data_colheita' => '2026-07-09',
                    'peso_bruto_kg' => '1200',
                ])
                ->assertRedirect('/colheita/novo')
                ->assertSessionHas('error');

            $this->assertDatabaseMissing('colheita_talhoes', [
                'propriedade_id' => $propertyId,
                'ticket_numero' => 'COL-SAFRA-BLOQUEADA',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/colheita/novo')
                ->post('/colheita', [
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoExternoId,
                    'ticket_numero' => 'COL-BLOQUEADA',
                    'data_colheita' => '2026-07-09',
                    'peso_bruto_kg' => '1000',
                ])
                ->assertRedirect('/colheita/novo')
                ->assertSessionHas('error');

            $this->assertDatabaseMissing('colheita_talhoes', [
                'propriedade_id' => $propertyId,
                'ticket_numero' => 'COL-BLOQUEADA',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_harvest_field_can_be_finished_and_reopened(): void
    {
        $safraId = DB::table('safras')->orderBy('id')->value('id');
        $talhaoId = DB::table('talhoes')->where('ativo', 1)->orderBy('id')->value('id');
        if (! $safraId || ! $talhaoId) {
            $this->markTestSkipped('Sem safra ou talhao para testar finalizacao.');
        }

        DB::beginTransaction();
        try {
            DB::table('colheita_talhoes')->insert([
                'propriedade_id' => app(FarmContext::class)->propertyId(),
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'ticket_numero' => 'TEST-FINALIZAR',
                'data_colheita' => '2026-07-09',
                'peso_bruto_kg' => 1000,
                'tara_kg' => 0,
                'peso_liquido_kg' => 1000,
                'desconto_kg' => 0,
                'peso_final_kg' => 1000,
                'sacas' => 16.67,
                'origem' => 'manual',
                'usuario_id' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession())
                ->post('/colheita/talhoes/finalizar', [
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoId,
                ])
                ->assertRedirect('/colheita?safra_id='.$safraId.'&talhao_id='.$talhaoId);

            $this->assertDatabaseHas('safra_talhoes', [
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'propriedade_id' => app(FarmContext::class)->propertyId(),
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'finalizar_colheita_talhao',
                'tabela' => 'safra_talhoes',
                'registro_id' => $talhaoId,
                'propriedade_id' => app(FarmContext::class)->propertyId(),
            ]);
            $this->assertNotNull(DB::table('safra_talhoes')->where('safra_id', $safraId)->where('talhao_id', $talhaoId)->value('colheita_finalizada_em'));

            $this->withSession($this->loggedSession())
                ->post('/colheita/talhoes/reabrir', [
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoId,
                ])
                ->assertRedirect('/colheita?safra_id='.$safraId.'&talhao_id='.$talhaoId);

            $this->assertNull(DB::table('safra_talhoes')->where('safra_id', $safraId)->where('talhao_id', $talhaoId)->value('colheita_finalizada_em'));
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'reabrir_colheita_talhao',
                'tabela' => 'safra_talhoes',
                'registro_id' => $talhaoId,
                'propriedade_id' => app(FarmContext::class)->propertyId(),
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_harvest_load_can_be_edited_and_deleted(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = app(FarmContext::class)->propertyId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra edicao colheita Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao edicao colheita Laravel',
                'area' => 12,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            DB::table('colheita_talhoes')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'ticket_numero' => 'COL-EDITAR',
                'data_colheita' => '2026-07-09',
                'peso_bruto_kg' => 1000,
                'tara_kg' => 100,
                'peso_liquido_kg' => 900,
                'desconto_kg' => 0,
                'peso_final_kg' => 900,
                'sacas' => 15,
                'origem' => 'manual',
                'usuario_id' => $this->userId(),
            ]);
            $cargaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/colheita/'.$cargaId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar colheita')
                ->assertSee('COL-EDITAR');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/colheita/'.$cargaId, [
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoId,
                    'ticket_numero' => 'COL-EDITADA',
                    'data_colheita' => '2026-07-10',
                    'peso_bruto_kg' => '1500',
                    'tara_kg' => '100',
                    'desconto_kg' => '200',
                    'area_colhida' => '10',
                    'destino_producao' => 'cooperativa',
                ])
                ->assertRedirect('/colheita?safra_id='.$safraId.'&talhao_id='.$talhaoId);

            $this->assertDatabaseHas('colheita_talhoes', [
                'id' => $cargaId,
                'ticket_numero' => 'COL-EDITADA',
                'peso_liquido_kg' => 1400,
                'peso_final_kg' => 1200,
                'sacas' => 20,
                'produtividade_sc_ha' => 2,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'editar_colheita',
                'tabela' => 'colheita_talhoes',
                'registro_id' => $cargaId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->delete('/colheita/'.$cargaId)
                ->assertRedirect();

            $this->assertDatabaseMissing('colheita_talhoes', [
                'id' => $cargaId,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'excluir_colheita',
                'tabela' => 'colheita_talhoes',
                'registro_id' => $cargaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_contracts_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/estoque-producao/contratos')
            ->assertStatus(200)
            ->assertSee('Contratos');
    }

    public function test_production_stock_entry_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/estoque-producao')
            ->assertStatus(200)
            ->assertSee('Contratos');
    }

    public function test_contract_store_creates_record(): void
    {
        DB::beginTransaction();

        try {
            $this->withSession($this->loggedSession())
                ->post('/estoque-producao/contratos', [
                    'tipo' => 'venda',
                    'numero' => 'CTR-LARAVEL-001',
                    'data_contrato' => now()->format('Y-m-d'),
                    'contraparte' => 'Cliente teste',
                    'produto' => 'Soja',
                    'quantidade' => '10',
                    'unidade' => 'sc',
                    'preco_unitario' => '100,00',
                ])
                ->assertRedirect('/estoque-producao/contratos');

            $this->assertDatabaseHas('contratos', [
                'numero' => 'CTR-LARAVEL-001',
                'tipo' => 'venda',
                'status' => 'aberto',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_contract_store_ignores_external_crop_season(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('propriedades')->insert([
                'nome' => 'Propriedade contrato externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra contrato externa',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraExternaId = (int) DB::getPdo()->lastInsertId();

            $numero = 'CTR-SAFRA-EXTERNA-'.uniqid();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/estoque-producao/contratos', [
                    'safra_id' => $safraExternaId,
                    'tipo' => 'venda',
                    'numero' => $numero,
                    'data_contrato' => '2026-07-09',
                    'contraparte' => 'Cliente teste',
                    'produto' => 'Soja',
                    'quantidade' => '10',
                    'unidade' => 'sc',
                    'preco_unitario' => '100,00',
                ])
                ->assertRedirect('/estoque-producao/contratos');

            $this->assertDatabaseHas('contratos', [
                'numero' => $numero,
                'propriedade_id' => $propertyId,
                'safra_id' => null,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_contract_delivery_updates_status_to_partial_and_delivered(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('contratos')->insert([
                'propriedade_id' => $propertyId,
                'tipo' => 'venda',
                'numero' => 'CTR-ENTREGA-LARAVEL',
                'contraparte' => 'Cliente entrega',
                'produto' => 'Soja',
                'quantidade' => 100,
                'unidade' => 'sc',
                'preco_unitario' => 120,
                'valor_total' => 12000,
                'data_contrato' => '2026-07-09',
                'status' => 'aberto',
                'usuario_id' => $this->userId(),
            ]);
            $contratoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/estoque-producao/contratos/entregas', [
                    'contrato_id' => $contratoId,
                    'data_entrega' => '2026-07-10',
                    'quantidade' => '40',
                    'unidade' => 'sc',
                    'valor' => '4800',
                    'observacoes' => 'Entrega parcial',
                ])
                ->assertRedirect('/estoque-producao/contratos');

            $this->assertDatabaseHas('contratos', [
                'id' => $contratoId,
                'status' => 'parcial',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/estoque-producao/contratos/entregas', [
                    'contrato_id' => $contratoId,
                    'data_entrega' => '2026-07-11',
                    'quantidade' => '60',
                    'unidade' => 'sc',
                    'valor' => '7200',
                    'observacoes' => 'Entrega final',
                ])
                ->assertRedirect('/estoque-producao/contratos');

            $this->assertDatabaseHas('contratos', [
                'id' => $contratoId,
                'status' => 'entregue',
            ]);
            $this->assertEquals(100.0, (float) DB::table('contrato_entregas')->where('contrato_id', $contratoId)->sum('quantidade'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_contract_page_has_delivery_shortcut_for_open_contracts(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('contratos')->insert([
                'propriedade_id' => $propertyId,
                'tipo' => 'venda',
                'numero' => 'CTR-ATALHO-ABERTO',
                'contraparte' => 'Cliente atalho',
                'produto' => 'Milho',
                'quantidade' => 50,
                'unidade' => 'kg',
                'preco_unitario' => 10,
                'valor_total' => 500,
                'data_contrato' => '2026-07-09',
                'status' => 'aberto',
                'usuario_id' => $this->userId(),
            ]);
            $contratoAbertoId = (int) DB::getPdo()->lastInsertId();

            DB::table('contratos')->insert([
                'propriedade_id' => $propertyId,
                'tipo' => 'venda',
                'numero' => 'CTR-ATALHO-ENTREGUE',
                'contraparte' => 'Cliente entregue',
                'produto' => 'Soja',
                'quantidade' => 10,
                'unidade' => 'sc',
                'preco_unitario' => 100,
                'valor_total' => 1000,
                'data_contrato' => '2026-07-09',
                'status' => 'entregue',
                'usuario_id' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/estoque-producao/contratos')
                ->assertStatus(200)
                ->assertSee('id="form-entrega-contrato"', false)
                ->assertSee('data-contrato-entrega-select', false)
                ->assertSee('data-contrato-id="'.$contratoAbertoId.'"', false)
                ->assertSee('data-unidade="kg"', false)
                ->assertSee('CTR-ATALHO-ENTREGUE');
        } finally {
            DB::rollBack();
        }
    }

    public function test_user_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/usuarios/novo')
            ->assertStatus(200)
            ->assertSee('Novo usuário');
    }

    public function test_users_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/usuarios')
            ->assertStatus(200)
            ->assertSee('Usuários')
            ->assertSee('Logins internos FarmFort');
    }

    public function test_property_users_page_does_not_show_users_from_other_property(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->propertyManagerUserId($propertyId);

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda isolada usuarios '.uniqid(),
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 10,
                'responsavel' => 'Teste',
                'cnpj_cpf' => '00000000000',
                'plano' => 'basico',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $otherPropertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuarios')->insert([
                'nome' => 'Usuario outra fazenda nao pode aparecer',
                'email' => 'usuario-outra-fazenda-'.uniqid().'@teste.local',
                'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
                'perfil' => 'visualizador',
                'ativo' => 1,
            ]);
            $otherUserId = (int) DB::getPdo()->lastInsertId();
            DB::table('usuario_propriedades')->insert([
                'usuario_id' => $otherUserId,
                'propriedade_id' => $otherPropertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->get('/usuarios')
                ->assertStatus(200)
                ->assertSee('Usuários e permissões por fazenda')
                ->assertDontSee('Usuario outra fazenda nao pode aparecer');
        } finally {
            DB::rollBack();
        }
    }

    public function test_user_can_be_created_with_audit_log(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->propertyManagerUserId($propertyId);
            $email = 'usuario-criado-'.uniqid().'@teste.local';

            DB::table('propriedades')->where('id', $propertyId)->update(['plano' => 'premium']);

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->post('/usuarios', [
                    'nome' => 'Usuario Laravel Criado',
                    'email' => $email,
                    'perfil' => 'visualizador',
                    'senha' => 'senha-criada',
                    'senha_confirmation' => 'senha-criada',
                ])
                ->assertRedirect('/usuarios');

            $usuarioId = (int) DB::table('usuarios')->where('email', $email)->value('id');
            $this->assertGreaterThan(0, $usuarioId);

            $this->assertDatabaseHas('usuario_propriedades', [
                'usuario_id' => $usuarioId,
                'propriedade_id' => $propertyId,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $userId,
                'acao' => 'salvar_usuario',
                'tabela' => 'usuarios',
                'registro_id' => $usuarioId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_user_audit_uses_real_client_ip_and_does_not_store_password(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->propertyManagerUserId($propertyId);
            $email = 'usuario-auditoria-'.uniqid().'@teste.local';

            DB::table('propriedades')->where('id', $propertyId)->update(['plano' => 'premium']);

            $this->withHeaders([
                    'CF-Connecting-IP' => '203.0.113.10',
                    'CF-Ray' => 'teste-ray-1234',
                    'User-Agent' => 'FarmFortTest/1.0',
                ])
                ->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->post('/usuarios', [
                    'nome' => 'Usuario Auditoria Segura',
                    'email' => $email,
                    'perfil' => 'visualizador',
                    'senha' => 'senha-nao-pode-aparecer',
                    'senha_confirmation' => 'senha-nao-pode-aparecer',
                ])
                ->assertRedirect('/usuarios');

            $usuarioId = (int) DB::table('usuarios')->where('email', $email)->value('id');
            $log = DB::table('logs_auditoria')
                ->where('usuario_id', $userId)
                ->where('acao', 'salvar_usuario')
                ->where('tabela', 'usuarios')
                ->where('registro_id', $usuarioId)
                ->first();

            $this->assertNotNull($log);
            $this->assertSame('203.0.113.10', (string) $log->ip);
            $this->assertStringNotContainsString('senha-nao-pode-aparecer', (string) $log->detalhes);

            if (Schema::hasColumn('logs_auditoria', 'ip_cliente')) {
                $this->assertSame('203.0.113.10', (string) $log->ip_cliente);
            }
            if (Schema::hasColumn('logs_auditoria', 'cf_ray')) {
                $this->assertSame('teste-ray-1234', (string) $log->cf_ray);
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_user_can_be_edited_and_toggled(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->propertyManagerUserId($propertyId);
            $this->assertGreaterThan(0, $propertyId);

            DB::table('usuarios')->insert([
                'nome' => 'Usuario Laravel Edicao',
                'email' => 'usuario-edicao-'.uniqid().'@teste.local',
                'senha' => password_hash('senha-antiga', PASSWORD_DEFAULT),
                'perfil' => 'visualizador',
                'ativo' => 1,
            ]);
            $usuarioId = (int) DB::getPdo()->lastInsertId();
            DB::table('usuario_propriedades')->insert([
                'usuario_id' => $usuarioId,
                'propriedade_id' => $propertyId,
            ]);

            $novoEmail = 'usuario-editado-'.uniqid().'@teste.local';

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->get('/usuarios/'.$usuarioId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar usuário')
                ->assertSee('Usuario Laravel Edicao');

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->put('/usuarios/'.$usuarioId, [
                    'nome' => 'Usuario Laravel Editado',
                    'email' => $novoEmail,
                    'perfil' => 'financeiro',
                    'senha' => 'nova-senha',
                    'senha_confirmation' => 'nova-senha',
                ])
                ->assertRedirect('/usuarios');

            $this->assertDatabaseHas('usuarios', [
                'id' => $usuarioId,
                'nome' => 'Usuario Laravel Editado',
                'email' => $novoEmail,
                'perfil' => 'financeiro',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $userId,
                'acao' => 'salvar_usuario',
                'tabela' => 'usuarios',
                'registro_id' => $usuarioId,
                'propriedade_id' => $propertyId,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $userId,
                'acao' => 'alterar_senha_usuario',
                'tabela' => 'usuarios',
                'registro_id' => $usuarioId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->post('/usuarios/'.$usuarioId.'/alternar-status')
                ->assertRedirect('/usuarios');

            $this->assertDatabaseHas('usuarios', [
                'id' => $usuarioId,
                'ativo' => 0,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'usuario_id' => $userId,
                'acao' => 'desativar_usuario',
                'tabela' => 'usuarios',
                'registro_id' => $usuarioId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_user_linked_by_farm_group_can_be_listed_edited_and_toggled(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->propertyManagerUserId($propertyId);
            $this->assertGreaterThan(0, $propertyId);

            DB::table('grupos_fazendas')->insert([
                'nome' => 'Grupo usuario Laravel',
                'ativo' => 1,
            ]);
            $grupoId = (int) DB::getPdo()->lastInsertId();
            DB::table('grupo_fazenda_propriedades')->insert([
                'grupo_id' => $grupoId,
                'propriedade_id' => $propertyId,
            ]);

            DB::table('usuarios')->insert([
                'nome' => 'Usuario grupo Laravel',
                'email' => 'usuario-grupo-'.uniqid().'@teste.local',
                'senha' => password_hash('senha-antiga', PASSWORD_DEFAULT),
                'perfil' => 'visualizador',
                'ativo' => 1,
            ]);
            $usuarioId = (int) DB::getPdo()->lastInsertId();
            DB::table('usuario_grupos_fazendas')->insert([
                'usuario_id' => $usuarioId,
                'grupo_id' => $grupoId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->get('/usuarios')
                ->assertStatus(200)
                ->assertSee('Usuario grupo Laravel');

            $novoEmail = 'usuario-grupo-editado-'.uniqid().'@teste.local';
            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->put('/usuarios/'.$usuarioId, [
                    'nome' => 'Usuario grupo editado Laravel',
                    'email' => $novoEmail,
                    'perfil' => 'financeiro',
                    'senha' => '',
                    'senha_confirmation' => '',
                ])
                ->assertRedirect('/usuarios');

            $this->assertDatabaseHas('usuarios', [
                'id' => $usuarioId,
                'nome' => 'Usuario grupo editado Laravel',
                'email' => $novoEmail,
                'perfil' => 'financeiro',
            ]);
            $this->assertDatabaseHas('usuario_grupos_fazendas', [
                'usuario_id' => $usuarioId,
                'grupo_id' => $grupoId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->post('/usuarios/'.$usuarioId.'/alternar-status')
                ->assertRedirect('/usuarios');

            $this->assertDatabaseHas('usuarios', [
                'id' => $usuarioId,
                'ativo' => 0,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_user_creation_limit_counts_group_linked_users(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = app(FarmContext::class)->propertyId();
            $userId = $this->propertyManagerUserId($propertyId);
            DB::table('propriedades')->where('id', $propertyId)->update(['plano' => 'basico']);

            DB::table('grupos_fazendas')->insert([
                'nome' => 'Grupo limite usuarios Laravel',
                'ativo' => 1,
            ]);
            $grupoId = (int) DB::getPdo()->lastInsertId();
            DB::table('grupo_fazenda_propriedades')->insert([
                'grupo_id' => $grupoId,
                'propriedade_id' => $propertyId,
            ]);

            $usuariosDiretos = DB::table('usuario_propriedades')
                ->where('propriedade_id', $propertyId)
                ->distinct('usuario_id')
                ->count('usuario_id');

            $faltamParaLimite = max(0, 2 - $usuariosDiretos);
            for ($i = 1; $i <= $faltamParaLimite; $i++) {
                DB::table('usuarios')->insert([
                    'nome' => 'Usuario direto limite grupo '.$i,
                    'email' => 'usuario-direto-limite-grupo-'.$i.'-'.uniqid().'@teste.local',
                    'senha' => 'teste',
                    'perfil' => 'visualizador',
                    'ativo' => 1,
                ]);
                $usuarioId = (int) DB::getPdo()->lastInsertId();
                DB::table('usuario_propriedades')->insert([
                    'usuario_id' => $usuarioId,
                    'propriedade_id' => $propertyId,
                ]);
            }

            DB::table('usuarios')->insert([
                'nome' => 'Usuario grupo conta limite',
                'email' => 'usuario-grupo-conta-limite-'.uniqid().'@teste.local',
                'senha' => 'teste',
                'perfil' => 'visualizador',
                'ativo' => 1,
            ]);
            $usuarioGrupoId = (int) DB::getPdo()->lastInsertId();
            DB::table('usuario_grupos_fazendas')->insert([
                'usuario_id' => $usuarioGrupoId,
                'grupo_id' => $grupoId,
            ]);

            $emailNovo = 'usuario-limite-grupo-'.uniqid().'@teste.local';
            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->from('/usuarios/novo')
                ->post('/usuarios', [
                    'nome' => 'Usuario excede limite por grupo',
                    'email' => $emailNovo,
                    'perfil' => 'visualizador',
                    'senha' => 'senha-limite',
                    'senha_confirmation' => 'senha-limite',
                ])
                ->assertRedirect('/usuarios/novo')
                ->assertSessionHasErrors();

            $this->assertDatabaseMissing('usuarios', [
                'email' => $emailNovo,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_user_creation_respects_property_plan_limit(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = app(FarmContext::class)->propertyId();
            $userId = $this->propertyManagerUserId($propertyId);
            DB::table('propriedades')->where('id', $propertyId)->update(['plano' => 'basico']);

            $usuariosVinculados = DB::table('usuarios as u')
                ->join('usuario_propriedades as up', 'up.usuario_id', '=', 'u.id')
                ->where('up.propriedade_id', $propertyId)
                ->where('u.ativo', 1)
                ->whereNotIn('u.perfil', ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'])
                ->distinct('u.id')
                ->count('u.id');

            for ($i = $usuariosVinculados + 1; $i <= 3; $i++) {
                DB::table('usuarios')->insert([
                    'nome' => 'Usuario limite criar '.$i,
                    'email' => 'usuario-limite-criar-'.$i.'-'.uniqid().'@teste.local',
                    'senha' => 'teste',
                    'perfil' => 'visualizador',
                    'ativo' => 1,
                ]);
                $usuarioId = (int) DB::getPdo()->lastInsertId();
                DB::table('usuario_propriedades')->insert([
                    'usuario_id' => $usuarioId,
                    'propriedade_id' => $propertyId,
                ]);
            }

            $emailNovo = 'usuario-excede-limite-'.uniqid().'@teste.local';

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade', userId: $userId))
                ->from('/usuarios/novo')
                ->post('/usuarios', [
                    'nome' => 'Usuario excede limite',
                    'email' => $emailNovo,
                    'perfil' => 'visualizador',
                    'senha' => 'senha-limite',
                    'senha_confirmation' => 'senha-limite',
                ])
                ->assertRedirect('/usuarios/novo')
                ->assertSessionHasErrors();

            $this->assertDatabaseMissing('usuarios', [
                'email' => $emailNovo,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_property_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/propriedades/novo')
            ->assertStatus(200)
            ->assertSee('Nova propriedade');
    }

    public function test_properties_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/propriedades')
            ->assertStatus(200)
            ->assertSee('Propriedades / Fazendas')
            ->assertSee('Filtros');
    }

    public function test_system_admin_property_selector_lists_only_active_properties(): void
    {
        DB::beginTransaction();

        try {
            $activePropertyName = 'Fazenda seletor ativa Laravel '.uniqid();
            $inactivePropertyName = 'Fazenda seletor inativa Laravel '.uniqid();

            DB::table('propriedades')->insert([
                'nome' => $activePropertyName,
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel seletor',
                'cnpj_cpf' => '12345678901',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);

            DB::table('propriedades')->insert([
                'nome' => $inactivePropertyName,
                'municipio' => 'Jatai',
                'estado' => 'GO',
                'area_total' => 50,
                'responsavel' => 'Responsavel seletor inativo',
                'cnpj_cpf' => '12345678902',
                'plano' => 'basico',
                'pecuaria_ativa' => 0,
                'ativo' => 0,
            ]);

            $this->withSession($this->loggedSession(profile: 'administrador_sistema'))
                ->get('/admin')
                ->assertStatus(200)
                ->assertSee($activePropertyName)
                ->assertDontSee($inactivePropertyName);
        } finally {
            DB::rollBack();
        }
    }

    public function test_property_selector_for_regular_user_keeps_property_scope(): void
    {
        DB::beginTransaction();

        try {
            $externalPropertyName = 'Fazenda fora do escopo Laravel '.uniqid();

            DB::table('propriedades')->insert([
                'nome' => $externalPropertyName,
                'municipio' => 'Cristalina',
                'estado' => 'GO',
                'area_total' => 80,
                'responsavel' => 'Responsavel externo',
                'cnpj_cpf' => '12345678903',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);

            $this->withSession($this->loggedSession(profile: 'visualizador'))
                ->get('/dashboard')
                ->assertStatus(200)
                ->assertDontSee($externalPropertyName);
        } finally {
            DB::rollBack();
        }
    }

    public function test_property_create_can_import_geo_file_as_fields(): void
    {
        DB::beginTransaction();

        try {
            $nome = 'Fazenda geo Laravel '.uniqid();
            $kml = <<<'KML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Talhao propriedade geo Laravel</name>
      <Polygon>
        <outerBoundaryIs>
          <LinearRing>
            <coordinates>
              -47.9000,-15.7000,0 -47.8900,-15.7000,0 -47.8900,-15.6900,0 -47.9000,-15.6900,0 -47.9000,-15.7000,0
            </coordinates>
          </LinearRing>
        </outerBoundaryIs>
      </Polygon>
    </Placemark>
  </Document>
</kml>
KML;

            $this->withSession($this->loggedSession())
                ->post('/propriedades', [
                    'nome' => $nome,
                    'municipio' => 'Rio Verde',
                    'estado' => 'GO',
                    'area_total' => '',
                    'responsavel' => 'Responsavel geo',
                    'inscricao_estadual' => '',
                    'cnpj_cpf' => '12345678901',
                    'plano' => 'premium',
                    'pecuaria_ativa' => '0',
                    'latitude' => '',
                    'longitude' => '',
                    'regiao_cotacao' => '',
                    'kml_area' => UploadedFile::fake()->createWithContent('fazenda_geo_laravel.kml', $kml),
                ])
                ->assertRedirect('/propriedades');

            $propriedade = DB::table('propriedades')->where('nome', $nome)->first();
            $this->assertNotNull($propriedade);
            $this->assertNotNull($propriedade->latitude);
            $this->assertNotNull($propriedade->longitude);
            $this->assertNotNull($propriedade->kml_arquivo);
            $this->assertGreaterThan(0, (float) $propriedade->area_total);

            $this->assertDatabaseHas('talhoes', [
                'propriedade_id' => $propriedade->id,
                'nome' => 'Talhao propriedade geo Laravel',
                'geometria_tipo' => 'polygon',
                'ativo' => 1,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_soy_quote_service_updates_property_from_market_rows(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda cotacao soja Laravel',
                'municipio' => 'Jatai',
                'estado' => 'GO',
                'latitude' => -17.87940000,
                'longitude' => -51.72170000,
                'ativo' => 1,
                'cotacao_soja_proxima_busca' => '2026-07-09 05:00:00',
                'cotacao_soja_status' => null,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            $atualizou = app(CotacaoSojaService::class)->atualizarPropriedade([
                'id' => $propertyId,
                'ativo' => 1,
                'municipio' => 'Jatai',
                'estado' => 'GO',
                'latitude' => -17.87940000,
                'longitude' => -51.72170000,
                'cotacao_soja_proxima_busca' => '2026-07-09 05:00:00',
            ], [
                'data' => '2026-07-10',
                'rows' => [
                    ['praca' => 'Rio Verde/GO', 'valor' => 121.50, 'cidade' => 'Rio Verde', 'uf' => 'GO', 'key' => 'rioverdego'],
                    ['praca' => 'Jatai/GO', 'valor' => 123.45, 'cidade' => 'Jatai', 'uf' => 'GO', 'key' => 'jataigo'],
                ],
            ]);

            $this->assertTrue($atualizou);
            $this->assertDatabaseHas('propriedades', [
                'id' => $propertyId,
                'regiao_cotacao' => 'Jatai/GO',
                'cotacao_soja' => '123.45',
                'cotacao_soja_atualizada_em' => '2026-07-10',
                'cotacao_soja_fonte' => 'Noticias Agricolas',
                'cotacao_soja_auto' => 1,
                'cotacao_soja_status' => 'atualizado',
                'cotacao_soja_erro' => null,
            ]);
            $this->assertNotNull(DB::table('propriedades')->where('id', $propertyId)->value('cotacao_soja_proxima_busca'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_property_can_be_edited_and_toggled(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda Laravel Edicao',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel teste',
                'cnpj_cpf' => '12345678901',
                'plano' => 'basico',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propriedadeId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession())
                ->get('/propriedades/'.$propriedadeId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar propriedade')
                ->assertSee('Fazenda Laravel Edicao');

            $this->withSession($this->loggedSession())
                ->put('/propriedades/'.$propriedadeId, [
                    'nome' => 'Fazenda Laravel Editada',
                    'municipio' => 'Jatai',
                    'estado' => 'GO',
                    'area_total' => '250,50',
                    'responsavel' => 'Responsavel editado',
                    'inscricao_estadual' => 'IE-123',
                    'cnpj_cpf' => '123.456.789-01',
                    'plano' => 'premium',
                    'pecuaria_ativa' => '1',
                    'latitude' => '-17,123456',
                    'longitude' => '-51,123456',
                    'regiao_cotacao' => 'Jatai/GO',
                ])
                ->assertRedirect('/propriedades');

            $this->assertDatabaseHas('propriedades', [
                'id' => $propriedadeId,
                'nome' => 'Fazenda Laravel Editada',
                'municipio' => 'Jatai',
                'plano' => 'premium',
                'pecuaria_ativa' => 1,
            ]);

            $this->withSession($this->loggedSession())
                ->post('/propriedades/'.$propriedadeId.'/alternar-status')
                ->assertRedirect('/propriedades?status=inativas');

            $this->assertDatabaseHas('propriedades', [
                'id' => $propriedadeId,
                'ativo' => 0,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_property_plan_cannot_be_reduced_below_linked_user_limit(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda limite plano Laravel',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel teste',
                'cnpj_cpf' => '12345678901',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propriedadeId = (int) DB::getPdo()->lastInsertId();

            for ($i = 1; $i <= 4; $i++) {
                DB::table('usuarios')->insert([
                    'nome' => 'Usuario limite plano '.$i,
                    'email' => 'limite-plano-'.$i.'-'.uniqid().'@teste.local',
                    'senha' => 'teste',
                    'perfil' => 'visualizador',
                    'ativo' => 1,
                ]);
                $usuarioId = (int) DB::getPdo()->lastInsertId();
                DB::table('usuario_propriedades')->insert([
                    'usuario_id' => $usuarioId,
                    'propriedade_id' => $propriedadeId,
                ]);
            }

            $this->withSession($this->loggedSession())
                ->from('/propriedades/'.$propriedadeId.'/editar')
                ->put('/propriedades/'.$propriedadeId, [
                    'nome' => 'Fazenda limite plano Laravel',
                    'municipio' => 'Rio Verde',
                    'estado' => 'GO',
                    'area_total' => '100',
                    'responsavel' => 'Responsavel teste',
                    'inscricao_estadual' => '',
                    'cnpj_cpf' => '12345678901',
                    'plano' => 'basico',
                    'pecuaria_ativa' => '0',
                    'latitude' => '',
                    'longitude' => '',
                    'regiao_cotacao' => '',
                ])
                ->assertRedirect('/propriedades/'.$propriedadeId.'/editar')
                ->assertSessionHasErrors();

            $this->assertDatabaseHas('propriedades', [
                'id' => $propriedadeId,
                'plano' => 'premium',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_property_cannot_link_user_already_linked_to_another_property(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda origem usuario Laravel',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel origem',
                'cnpj_cpf' => '11111111111',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propriedadeOrigemId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda destino usuario Laravel',
                'municipio' => 'Jatai',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel destino',
                'cnpj_cpf' => '22222222222',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propriedadeDestinoId = (int) DB::getPdo()->lastInsertId();

            $email = 'usuario-vinculo-unico-'.uniqid().'@teste.local';
            DB::table('usuarios')->insert([
                'nome' => 'Usuario Vinculo Unico',
                'email' => $email,
                'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
                'perfil' => 'visualizador',
                'ativo' => 1,
            ]);
            $usuarioId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuario_propriedades')->insert([
                'usuario_id' => $usuarioId,
                'propriedade_id' => $propriedadeOrigemId,
            ]);

            $this->withSession($this->loggedSession())
                ->from('/propriedades/'.$propriedadeDestinoId.'/editar')
                ->put('/propriedades/'.$propriedadeDestinoId, [
                    'nome' => 'Fazenda destino usuario Laravel',
                    'municipio' => 'Jatai',
                    'estado' => 'GO',
                    'area_total' => '100',
                    'responsavel' => 'Responsavel destino',
                    'inscricao_estadual' => '',
                    'cnpj_cpf' => '22222222222',
                    'plano' => 'premium',
                    'pecuaria_ativa' => '0',
                    'latitude' => '',
                    'longitude' => '',
                    'regiao_cotacao' => '',
                    'novos_usuarios' => [
                        [
                            'nome' => 'Usuario Vinculo Unico',
                            'email' => $email,
                            'senha' => 'senha-segura',
                            'perfil' => 'visualizador',
                        ],
                    ],
                ])
                ->assertRedirect('/propriedades/'.$propriedadeDestinoId.'/editar')
                ->assertSessionHasErrors();

            $this->assertDatabaseMissing('usuario_propriedades', [
                'usuario_id' => $usuarioId,
                'propriedade_id' => $propriedadeDestinoId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_property_admin_can_remove_user_link_from_property(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda remover usuario Laravel',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel remover',
                'cnpj_cpf' => '33333333333',
                'plano' => 'premium',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuarios')->insert([
                'nome' => 'Usuario Remover Propriedade',
                'email' => 'usuario-remover-propriedade-'.uniqid().'@teste.local',
                'senha' => password_hash('senha-segura', PASSWORD_DEFAULT),
                'perfil' => 'visualizador',
                'ativo' => 1,
            ]);
            $usuarioId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuario_propriedades')->insert([
                'usuario_id' => $usuarioId,
                'propriedade_id' => $propriedadeId,
            ]);

            $this->withSession($this->loggedSession())
                ->put('/propriedades/'.$propriedadeId, [
                    'nome' => 'Fazenda remover usuario Laravel',
                    'municipio' => 'Rio Verde',
                    'estado' => 'GO',
                    'area_total' => '100',
                    'responsavel' => 'Responsavel remover',
                    'inscricao_estadual' => '',
                    'cnpj_cpf' => '33333333333',
                    'plano' => 'premium',
                    'pecuaria_ativa' => '0',
                    'latitude' => '',
                    'longitude' => '',
                    'regiao_cotacao' => '',
                    'usuarios_vinculados' => [
                        [
                            'id' => $usuarioId,
                            'remover' => '1',
                        ],
                    ],
                ])
                ->assertRedirect('/propriedades');

            $this->assertDatabaseMissing('usuario_propriedades', [
                'usuario_id' => $usuarioId,
                'propriedade_id' => $propriedadeId,
            ]);
            $this->assertDatabaseHas('usuarios', [
                'id' => $usuarioId,
                'ativo' => 1,
            ]);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'remover_usuario_propriedade',
                'tabela' => 'usuario_propriedades',
                'registro_id' => $usuarioId,
                'propriedade_id' => $propriedadeId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_property_approver_can_be_saved_and_linked(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda aprovador Laravel',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsavel teste',
                'cnpj_cpf' => '12345678901',
                'plano' => 'avancado',
                'pecuaria_ativa' => 0,
                'ativo' => 1,
            ]);
            $propriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuarios')->insert([
                'nome' => 'Aprovador propriedade Laravel',
                'email' => 'aprovador-propriedade-'.uniqid().'@teste.local',
                'senha' => 'teste',
                'perfil' => 'gestor_financeiro',
                'ativo' => 1,
            ]);
            $aprovadorId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession())
                ->get('/propriedades/'.$propriedadeId.'/editar')
                ->assertStatus(200)
                ->assertSee('Aprovador')
                ->assertSee('Aprovador propriedade Laravel');

            $this->withSession($this->loggedSession())
                ->put('/propriedades/'.$propriedadeId, [
                    'nome' => 'Fazenda aprovador Laravel',
                    'municipio' => 'Rio Verde',
                    'estado' => 'GO',
                    'area_total' => '100',
                    'responsavel' => 'Responsavel teste',
                    'inscricao_estadual' => '',
                    'cnpj_cpf' => '12345678901',
                    'plano' => 'avancado',
                    'pecuaria_ativa' => '0',
                    'latitude' => '',
                    'longitude' => '',
                    'regiao_cotacao' => '',
                    'aprovador_usuario_id' => $aprovadorId,
                ])
                ->assertRedirect('/propriedades');

            $this->assertDatabaseHas('propriedades', [
                'id' => $propriedadeId,
                'aprovador_usuario_id' => $aprovadorId,
            ]);
            $this->assertDatabaseHas('usuario_propriedades', [
                'usuario_id' => $aprovadorId,
                'propriedade_id' => $propriedadeId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_farm_groups_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/propriedades/grupos')
            ->assertStatus(200)
            ->assertSee('Grupos');
    }

    public function test_farm_group_store_creates_record_when_premium_property_exists(): void
    {
        $propriedadeId = DB::table('propriedades')
            ->where('ativo', 1)
            ->where('plano', 'premium')
            ->orderBy('id')
            ->value('id');

        if (! $propriedadeId) {
            $this->markTestSkipped('Sem propriedade Premium para criar grupo de fazendas.');
        }

        DB::beginTransaction();

        try {
            $this->withSession($this->loggedSession())
                ->post('/propriedades/grupos', [
                    'nome' => 'Grupo teste Laravel',
                    'descricao' => 'Grupo criado em teste automatizado',
                    'propriedades' => [$propriedadeId],
                ])
                ->assertRedirect('/propriedades/grupos');

            $this->assertDatabaseHas('grupos_fazendas', [
                'nome' => 'Grupo teste Laravel',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_farm_group_rejects_non_premium_property(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda Premium Grupo Laravel',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Teste',
                'plano' => 'premium',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $premiumId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda Basica Grupo Laravel',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Teste',
                'plano' => 'basico',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $basicaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession())
                ->post('/propriedades/grupos', [
                    'nome' => 'Grupo com basica Laravel',
                    'propriedades' => [$premiumId, $basicaId],
                ])
                ->assertStatus(422);

            $this->assertDatabaseMissing('grupos_fazendas', [
                'nome' => 'Grupo com basica Laravel',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_farm_group_rejects_approver_without_access_to_selected_farms(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda Aprovador Grupo Laravel',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Teste',
                'plano' => 'premium',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $propriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('usuarios')->insert([
                'nome' => 'Aprovador sem vinculo Laravel',
                'email' => 'aprovador-sem-vinculo-'.uniqid().'@teste.local',
                'senha' => password_hash('teste', PASSWORD_BCRYPT),
                'perfil' => 'financeiro',
                'ativo' => 1,
                'criado_em' => now(),
            ]);
            $aprovadorId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession())
                ->post('/propriedades/grupos', [
                    'nome' => 'Grupo aprovador invalido Laravel',
                    'aprovador_usuario_id' => $aprovadorId,
                    'propriedades' => [$propriedadeId],
                ])
                ->assertStatus(422);

            $this->assertDatabaseMissing('grupos_fazendas', [
                'nome' => 'Grupo aprovador invalido Laravel',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_invoice_entry_create_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/fiscal/entrada-nf/novo')
            ->assertStatus(200)
            ->assertSee('Entrada de NF');
    }

    public function test_invoice_entry_index_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/fiscal/entrada-nf')
            ->assertStatus(200)
            ->assertSee('Entrada de NF')
            ->assertSee('Historico de entradas');
    }

    public function test_invoice_entry_can_create_patrimony(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            $nomePatrimonio = 'Patrimonio via entrada NF '.uniqid();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/entrada-nf', [
                    'numero' => 'NF-ENT-PAT',
                    'serie' => '1',
                    'chave_acesso' => '12345678901234567890123456789012345678901234',
                    'data_emissao' => '2026-07-09',
                    'data_entrada' => '2026-07-09',
                    'fornecedor' => 'Fornecedor entrada patrimonio',
                    'fornecedor_doc' => '12.345.678/0001-90',
                    'valor_total' => '75.000,00',
                    'valor_produtos' => '75.000,00',
                    'forma_pagamento' => 'transferencia',
                    'classificar_patrimonio' => '1',
                    'patrimonio_nome' => $nomePatrimonio,
                    'patrimonio_tipo' => 'implemento',
                    'patrimonio_controla_horimetro' => '1',
                    'patrimonio_controla_odometro' => '0',
                    'item_descricao' => 'Implemento fiscal',
                    'item_quantidade' => '1',
                    'item_unidade' => 'un',
                    'item_valor_unitario' => '75.000,00',
                ])
                ->assertRedirect('/modulos/fiscal');

            $entrada = DB::table('nf_entradas')
                ->where('propriedade_id', $propertyId)
                ->where('numero', 'NF-ENT-PAT')
                ->first();
            $this->assertNotNull($entrada);

            $this->assertDatabaseHas('maquinas', [
                'propriedade_id' => $propertyId,
                'nome' => $nomePatrimonio,
                'tipo' => 'implemento',
                'valor_aquisicao' => '75000.00',
                'nota_fiscal_numero' => 'NF-ENT-PAT',
                'nf_entrada_id' => $entrada->id,
                'controla_horimetro' => 1,
                'ativo' => 1,
            ]);

            $patrimonioId = DB::table('maquinas')
                ->where('propriedade_id', $propertyId)
                ->where('nome', $nomePatrimonio)
                ->value('id');
            $this->assertNotEmpty($patrimonioId);
            $this->assertSame((int) $patrimonioId, (int) DB::table('nf_entradas')->where('id', $entrada->id)->value('patrimonio_id'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_invoice_entry_can_be_shown_and_concluded_to_finance(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->userId();
            $this->assertGreaterThan(0, $propertyId);
            $this->assertGreaterThan(0, $userId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria entrada NF teste '.uniqid(),
                'tipo' => 'insumo',
                'cor' => '#22c55e',
                'icone' => 'bi-receipt',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();
            $numero = 'NF-CONCLUIR-'.uniqid();

            DB::table('nf_entradas')->insert([
                'propriedade_id' => $propertyId,
                'numero' => $numero,
                'serie' => '1',
                'origem_lancamento' => 'manual',
                'data_emissao' => '2026-07-09',
                'data_entrada' => '2026-07-09',
                'fornecedor' => 'Fornecedor conclusao NF',
                'fornecedor_doc' => '12345678000190',
                'valor_total' => 300.00,
                'valor_produtos' => 300.00,
                'valor_financeiro_final' => 300.00,
                'forma_pagamento' => 'boleto',
                'categoria_id' => $categoriaId,
                'status' => 'rascunho',
                'financeiro_confirmado' => 0,
                'usuario_id' => $userId,
            ]);
            $entradaId = (int) DB::getPdo()->lastInsertId();

            DB::table('nf_entrada_itens')->insert([
                'nf_entrada_id' => $entradaId,
                'descricao_nf' => 'Item validado para conclusao',
                'descricao_generica' => 'Item validado para conclusao',
                'quantidade' => 3,
                'unidade' => 'un',
                'valor_unitario' => 100,
                'valor_total' => 300,
                'total_liquido' => 300,
                'categoria_id' => $categoriaId,
                'fiscal_validado' => 1,
            ]);

            DB::table('nf_entrada_parcelas')->insert([
                [
                    'nf_entrada_id' => $entradaId,
                    'parcela_numero' => 1,
                    'data_vencimento' => '2026-08-01',
                    'valor' => 100,
                    'forma_pagamento' => 'boleto',
                    'status' => 'pendente',
                ],
                [
                    'nf_entrada_id' => $entradaId,
                    'parcela_numero' => 2,
                    'data_vencimento' => '2026-09-01',
                    'valor' => 200,
                    'forma_pagamento' => 'boleto',
                    'status' => 'pendente',
                ],
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/fiscal/entrada-nf/'.$entradaId)
                ->assertStatus(200)
                ->assertSee($numero)
                ->assertSee('Concluir para financeiro');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/entrada-nf/'.$entradaId.'/concluir')
                ->assertRedirect('/fiscal/entrada-nf/'.$entradaId);

            $entrada = DB::table('nf_entradas')->where('id', $entradaId)->first();
            $this->assertSame('concluida', $entrada->status);
            $this->assertSame(1, (int) $entrada->financeiro_confirmado);

            $this->assertSame(2, DB::table('despesas')->where('nota_fiscal', $numero)->count());
            $this->assertSame(300.0, (float) DB::table('despesas')->where('nota_fiscal', $numero)->sum('valor_total'));
            $this->assertSame(2, DB::table('nf_entrada_parcelas')->where('nf_entrada_id', $entradaId)->where('status', 'confirmada')->whereNotNull('despesa_id')->count());
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'concluir_entrada_nf',
                'tabela' => 'nf_entradas',
                'registro_id' => $entradaId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_invoice_entry_can_add_item_and_generate_installments(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $userId = $this->userId();
            DB::table('categorias')->insert([
                'nome' => 'Categoria item NF teste '.uniqid(),
                'tipo' => 'insumo',
                'cor' => '#14b8a6',
                'icone' => 'bi-box',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('nf_entradas')->insert([
                'propriedade_id' => $propertyId,
                'numero' => 'NF-ITEM-'.uniqid(),
                'serie' => '1',
                'origem_lancamento' => 'manual',
                'data_emissao' => '2026-07-09',
                'data_entrada' => '2026-07-09',
                'fornecedor' => 'Fornecedor item NF',
                'valor_total' => 600.00,
                'valor_produtos' => 600.00,
                'valor_financeiro_final' => 600.00,
                'forma_pagamento' => 'boleto',
                'categoria_id' => $categoriaId,
                'status' => 'rascunho',
                'financeiro_confirmado' => 0,
                'usuario_id' => $userId,
            ]);
            $entradaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/entrada-nf/'.$entradaId.'/itens', [
                    'descricao_nf' => 'Produto novo pela entrada NF',
                    'descricao_generica' => 'Produto novo pela entrada NF',
                    'quantidade' => '2',
                    'unidade' => 'un',
                    'valor_unitario' => '300',
                    'ncm' => '12345678',
                    'cst_icms' => '00',
                    'cst_pis' => '01',
                    'cst_cofins' => '01',
                    'categoria_id' => $categoriaId,
                ])
                ->assertRedirect('/fiscal/entrada-nf/'.$entradaId);

            $this->assertDatabaseHas('produtos', [
                'propriedade_id' => $propertyId,
                'descricao_generica' => 'Produto novo pela entrada NF',
                'ncm' => '12345678',
            ]);
            $this->assertDatabaseHas('nf_entrada_itens', [
                'nf_entrada_id' => $entradaId,
                'descricao_generica' => 'Produto novo pela entrada NF',
                'total_liquido' => '600.00',
                'fiscal_validado' => 1,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/entrada-nf/'.$entradaId.'/parcelas/gerar', [
                    'parcelas_qtd' => 3,
                    'primeiro_vencimento' => '2026-08-10',
                ])
                ->assertRedirect('/fiscal/entrada-nf/'.$entradaId);

            $this->assertSame(3, DB::table('nf_entrada_parcelas')->where('nf_entrada_id', $entradaId)->count());
            $this->assertSame(600.0, (float) DB::table('nf_entrada_parcelas')->where('nf_entrada_id', $entradaId)->sum('valor'));
            $this->assertDatabaseHas('nf_entrada_parcelas', [
                'nf_entrada_id' => $entradaId,
                'parcela_numero' => 3,
                'data_vencimento' => '2026-10-10',
                'valor' => '200.00',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_invoice_xml_import_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/fiscal/notas/importar')
            ->assertStatus(200)
            ->assertSee('Importar NF-e');
    }

    public function test_fiscal_entry_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/fiscal')
            ->assertStatus(200)
            ->assertSee('Fiscal Consolidado')
            ->assertSee('Registros fiscais aprovados');
    }

    public function test_fiscal_consolidated_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/fiscal/consolidado')
            ->assertStatus(200)
            ->assertSee('Fiscal Consolidado')
            ->assertSee('Registros fiscais aprovados');
    }

    public function test_digital_certificate_page_returns_a_successful_response(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('propriedades')
                ->where('id', $propertyId)
                ->update(['cnpj_cpf' => '11222333000144']);

            DB::table('certificados_digitais')->insert([
                'propriedade_id' => $propertyId,
                'tipo_certificado' => 'A3',
                'ambiente' => 'producao',
                'nome_identificacao' => 'Certificado pagina Laravel',
                'validade_fim' => now()->addDays(12)->format('Y-m-d'),
                'principal' => 0,
                'status' => 'ativo',
                'usuario_id' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/fiscal/certificados')
                ->assertStatus(200)
                ->assertSee('Certificados')
                ->assertSee('11222333000144')
                ->assertSee('Vence em 12 dia(s)');
        } finally {
            DB::rollBack();
        }
    }

    public function test_digital_certificate_store_creates_record(): void
    {
        DB::beginTransaction();
        Storage::fake('local');

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/fiscal/certificados')
                ->post('/fiscal/certificados', [
                    'nome_identificacao' => 'A1 incompleto Laravel',
                    'tipo_certificado' => 'A1',
                    'ambiente' => 'homologacao',
                ])
                ->assertRedirect('/fiscal/certificados')
                ->assertSessionHasErrors(['senha_certificado', 'certificado']);

            $tmp = tempnam(sys_get_temp_dir(), 'cert-a1-invalid-');
            file_put_contents($tmp, 'arquivo pfx invalido');
            $invalidPfx = new UploadedFile($tmp, 'certificado.pfx', 'application/x-pkcs12', null, true);
            $certificadosAntes = DB::table('certificados_digitais')
                ->where('propriedade_id', $propertyId)
                ->count();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/fiscal/certificados')
                ->post('/fiscal/certificados', [
                    'nome_identificacao' => 'A1 invalido Laravel',
                    'tipo_certificado' => 'A1',
                    'ambiente' => 'homologacao',
                    'senha_certificado' => 'senha-teste',
                    'certificado' => $invalidPfx,
                ])
                ->assertRedirect('/fiscal/certificados')
                ->assertSessionHasErrors(['certificado']);

            $this->assertSame($certificadosAntes, DB::table('certificados_digitais')
                ->where('propriedade_id', $propertyId)
                ->count());

            $this->withSession($this->loggedSession())
                ->post('/fiscal/certificados', [
                    'nome_identificacao' => 'Certificado teste Laravel',
                    'tipo_certificado' => 'A3',
                    'ambiente' => 'homologacao',
                    'titular' => 'Titular Teste',
                    'cpf_cnpj' => '12345678000195',
                    'validade_fim' => now()->addYear()->format('Y-m-d'),
                    'principal' => 1,
                ])
                ->assertRedirect('/fiscal/certificados');

            $this->assertDatabaseHas('certificados_digitais', [
                'nome_identificacao' => 'Certificado teste Laravel',
                'tipo_certificado' => 'A3',
                'ambiente' => 'homologacao',
                'principal' => 1,
            ]);

            $certificadoId = (int) DB::table('certificados_digitais')
                ->where('nome_identificacao', 'Certificado teste Laravel')
                ->value('id');
            $this->assertGreaterThan(0, $certificadoId);

            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'vincular_certificado_digital',
                'tabela' => 'certificados_digitais',
                'registro_id' => $certificadoId,
                'propriedade_id' => $propertyId,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/certificados', [
                    'nome_identificacao' => 'Certificado vence hoje Laravel',
                    'tipo_certificado' => 'A3',
                    'ambiente' => 'homologacao',
                    'titular' => 'Titular Hoje',
                    'validade_fim' => now()->format('Y-m-d'),
                    'principal' => 0,
                ])
                ->assertRedirect('/fiscal/certificados');

            $this->assertDatabaseHas('certificados_digitais', [
                'nome_identificacao' => 'Certificado vence hoje Laravel',
                'validade_fim' => now()->format('Y-m-d'),
                'status' => 'ativo',
            ]);

            DB::table('certificados_digitais')->insert([
                'propriedade_id' => $propertyId,
                'tipo_certificado' => 'A3',
                'ambiente' => 'producao',
                'nome_identificacao' => 'Certificado secundario Laravel',
                'validade_fim' => now()->addYear()->format('Y-m-d'),
                'principal' => 0,
                'status' => 'ativo',
                'usuario_id' => $this->userId(),
            ]);
            $secundarioId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/certificados/'.$secundarioId.'/principal')
                ->assertRedirect('/fiscal/certificados');

            $this->assertDatabaseHas('certificados_digitais', [
                'id' => $secundarioId,
                'principal' => 1,
                'status' => 'ativo',
            ]);

            $this->assertDatabaseHas('certificados_digitais', [
                'id' => $certificadoId,
                'principal' => 0,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/certificados/'.$secundarioId.'/desativar')
                ->assertRedirect('/fiscal/certificados');

            $this->assertDatabaseHas('certificados_digitais', [
                'id' => $secundarioId,
                'principal' => 0,
                'status' => 'inativo',
            ]);

            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'desativar_certificado_digital',
                'tabela' => 'certificados_digitais',
                'registro_id' => $secundarioId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_digital_certificate_principal_requires_current_property_active_certificate(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('certificados_digitais')->insert([
                'propriedade_id' => $propertyId,
                'tipo_certificado' => 'A3',
                'ambiente' => 'producao',
                'nome_identificacao' => 'Certificado principal preservado',
                'validade_fim' => now()->addYear()->format('Y-m-d'),
                'principal' => 1,
                'status' => 'ativo',
                'usuario_id' => $this->userId(),
            ]);
            $principalId = (int) DB::getPdo()->lastInsertId();

            DB::table('certificados_digitais')->insert([
                'propriedade_id' => $propertyId,
                'tipo_certificado' => 'A3',
                'ambiente' => 'producao',
                'nome_identificacao' => 'Certificado inativo nao principal',
                'validade_fim' => now()->addYear()->format('Y-m-d'),
                'principal' => 0,
                'status' => 'inativo',
                'usuario_id' => $this->userId(),
            ]);
            $inativoId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda certificado externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('certificados_digitais')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'tipo_certificado' => 'A3',
                'ambiente' => 'producao',
                'nome_identificacao' => 'Certificado externo',
                'validade_fim' => now()->addYear()->format('Y-m-d'),
                'principal' => 0,
                'status' => 'ativo',
                'usuario_id' => $this->userId(),
            ]);
            $externoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/certificados/'.$externoId.'/principal')
                ->assertNotFound();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/certificados/'.$inativoId.'/principal')
                ->assertNotFound();

            $this->assertDatabaseHas('certificados_digitais', [
                'id' => $principalId,
                'propriedade_id' => $propertyId,
                'principal' => 1,
                'status' => 'ativo',
            ]);
            $this->assertDatabaseHas('certificados_digitais', [
                'id' => $externoId,
                'propriedade_id' => $outraPropriedadeId,
                'principal' => 0,
            ]);
            $this->assertDatabaseHas('certificados_digitais', [
                'id' => $inativoId,
                'propriedade_id' => $propertyId,
                'principal' => 0,
                'status' => 'inativo',
            ]);

        } finally {
            DB::rollBack();
        }
    }

    public function test_producers_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/fiscal/produtores')
            ->assertStatus(200)
            ->assertSee('Produtores');
    }

    public function test_producer_store_creates_record(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->from('/fiscal/produtores')
                ->post('/fiscal/produtores', [
                    'nome' => 'Produtor percentual invalido',
                    'documento' => '12345678901',
                    'participacao_percentual' => '150,00',
                ])
                ->assertRedirect('/fiscal/produtores')
                ->assertSessionHasErrors(['participacao_percentual']);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/produtores', [
                    'nome' => 'Produtor teste Laravel',
                    'documento' => '12345678901',
                    'participacao_percentual' => '25,50',
                ])
                ->assertRedirect('/fiscal/produtores');

            $this->assertDatabaseHas('produtores', [
                'nome' => 'Produtor teste Laravel',
                'documento' => '12345678901',
            ]);

            $produtorId = (int) DB::table('produtores')
                ->where('propriedade_id', $propertyId)
                ->where('nome', 'Produtor teste Laravel')
                ->value('id');
            $this->assertGreaterThan(0, $produtorId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/fiscal/produtores/'.$produtorId, [
                    'nome' => 'Produtor atualizado Laravel',
                    'documento' => '10987654321',
                    'participacao_percentual' => '40,25',
                ])
                ->assertRedirect('/fiscal/produtores');

            $this->assertDatabaseHas('produtores', [
                'id' => $produtorId,
                'nome' => 'Produtor atualizado Laravel',
                'documento' => '10987654321',
                'ativo' => 1,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/produtores/'.$produtorId.'/toggle')
                ->assertRedirect('/fiscal/produtores');

            $this->assertDatabaseHas('produtores', [
                'id' => $produtorId,
                'ativo' => 0,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_producer_update_and_toggle_require_current_property(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda externa produtores Laravel',
                'ativo' => 1,
            ]);
            $externalPropertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('produtores')->insert([
                'propriedade_id' => $externalPropertyId,
                'nome' => 'Produtor externo Laravel',
                'documento' => '99988877766',
                'participacao_percentual' => 75,
                'ativo' => 1,
            ]);
            $externalProducerId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->put('/fiscal/produtores/'.$externalProducerId, [
                    'nome' => 'Produtor alterado indevidamente',
                    'documento' => '11122233344',
                    'participacao_percentual' => '10,00',
                ])
                ->assertNotFound();

            $this->assertDatabaseHas('produtores', [
                'id' => $externalProducerId,
                'propriedade_id' => $externalPropertyId,
                'nome' => 'Produtor externo Laravel',
                'documento' => '99988877766',
                'ativo' => 1,
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/produtores/'.$externalProducerId.'/toggle')
                ->assertNotFound();

            $this->assertDatabaseHas('produtores', [
                'id' => $externalProducerId,
                'propriedade_id' => $externalPropertyId,
                'ativo' => 1,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_documents_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/fiscal/documentos')
            ->assertStatus(200)
            ->assertSee('Documentos');
    }

    public function test_document_store_creates_record(): void
    {
        DB::beginTransaction();
        $arquivoPath = null;

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda externa documentos Laravel',
                'municipio' => 'Teste',
                'estado' => 'GO',
                'ativo' => 1,
            ]);
            $externalPropertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $externalPropertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra externa documentos Laravel',
                'data_inicio' => '2026-01-01',
                'status' => 'planejamento',
            ]);
            $externalSafraId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/documentos', [
                    'tipo' => 'comprovante',
                    'titulo' => 'Documento teste Laravel',
                    'numero' => 'DOC-001',
                    'pessoa' => 'Pessoa Teste',
                    'safra_id' => $externalSafraId,
                    'data_documento' => now()->format('Y-m-d'),
                    'valor' => '99,90',
                    'status' => 'pendente',
                    'observacoes' => 'Criado por teste automatizado',
                    'arquivo' => UploadedFile::fake()->create('documento-teste.pdf', 8, 'application/pdf'),
                ])
                ->assertRedirect('/fiscal/documentos');

            $this->assertDatabaseHas('documentos', [
                'titulo' => 'Documento teste Laravel',
                'tipo' => 'comprovante',
                'safra_id' => null,
                'status' => 'pendente',
            ]);

            $arquivoNome = DB::table('documentos')
                ->where('propriedade_id', $propertyId)
                ->where('titulo', 'Documento teste Laravel')
                ->value('arquivo');
            $this->assertNotEmpty($arquivoNome);
            $this->assertStringStartsWith('doc_', $arquivoNome);
            $arquivoPath = base_path('../uploads/comprovantes/'.$arquivoNome);
            $this->assertFileExists($arquivoPath);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/fiscal/documentos')
                ->assertStatus(200)
                ->assertSee('Abrir')
                ->assertSee('/fiscal/documentos/');

            $documentoId = (int) DB::table('documentos')
                ->where('propriedade_id', $propertyId)
                ->where('titulo', 'Documento teste Laravel')
                ->value('id');
            $this->assertGreaterThan(0, $documentoId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/fiscal/documentos/'.$documentoId.'/arquivo')
                ->assertStatus(200);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/documentos/'.$documentoId.'/status', ['status' => 'conferido'])
                ->assertRedirect('/fiscal/documentos');

            $this->assertDatabaseHas('documentos', [
                'id' => $documentoId,
                'status' => 'conferido',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/documentos/'.$documentoId.'/status', ['status' => 'arquivado'])
                ->assertRedirect('/fiscal/documentos');

            $this->assertDatabaseHas('documentos', [
                'id' => $documentoId,
                'status' => 'arquivado',
            ]);
        } finally {
            DB::rollBack();
            if ($arquivoPath && is_file($arquivoPath)) {
                unlink($arquivoPath);
            }
        }
    }

    public function test_document_pending_row_uses_dedicated_confer_action(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('documentos')->insert([
                'propriedade_id' => $propertyId,
                'tipo' => 'comprovante',
                'titulo' => 'Documento pendente conferir Laravel',
                'data_documento' => '2026-07-09',
                'valor' => 10,
                'status' => 'pendente',
            ]);
            $documentoId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda documento conferir externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('documentos')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'tipo' => 'comprovante',
                'titulo' => 'Documento externo conferir Laravel',
                'data_documento' => '2026-07-09',
                'status' => 'pendente',
            ]);
            $documentoExternoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/fiscal/documentos')
                ->assertStatus(200)
                ->assertSee('Documento pendente conferir Laravel')
                ->assertSee('/fiscal/documentos/'.$documentoId.'/conferir', false);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/documentos/'.$documentoId.'/conferir')
                ->assertRedirect('/fiscal/documentos');

            $this->assertDatabaseHas('documentos', [
                'id' => $documentoId,
                'propriedade_id' => $propertyId,
                'status' => 'conferido',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/documentos/'.$documentoExternoId.'/conferir')
                ->assertRedirect('/fiscal/documentos');

            $this->assertDatabaseHas('documentos', [
                'id' => $documentoExternoId,
                'propriedade_id' => $outraPropriedadeId,
                'status' => 'pendente',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_reports_pages_return_successful_responses(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/relatorios')
            ->assertStatus(200)
            ->assertSee('Indicadores');

        $this->withSession($this->loggedSession())
            ->get('/relatorios/dre')
            ->assertStatus(200)
            ->assertSee('DRE');

        $this->withSession($this->loggedSession())
            ->get('/relatorios/fluxo-caixa')
            ->assertStatus(200)
            ->assertSee('Fluxo de Caixa');

        $this->withSession($this->loggedSession())
            ->get('/relatorios/orcado-realizado')
            ->assertStatus(200)
            ->assertSee('Orçado x Realizado');
    }

    public function test_dre_and_cash_flow_ignore_rejected_expenses(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda relatorio rejeitada Laravel',
                'ativo' => 1,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria relatorio rejeitada Laravel',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Receita DRE valida Laravel',
                    'valor_total' => 1000,
                    'data_venda' => '2026-07-09',
                    'status' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Receita DRE cancelada Laravel',
                    'valor_total' => 400,
                    'data_venda' => '2026-07-09',
                    'status' => 'cancelado',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
            ]);

            DB::table('despesas')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa DRE pendente Laravel',
                    'fornecedor' => 'Fornecedor DRE',
                    'valor_total' => 250,
                    'data_lancamento' => '2026-07-09',
                    'data_vencimento' => '2026-07-10',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa DRE reprovada Laravel',
                    'fornecedor' => 'Fornecedor DRE',
                    'valor_total' => 700,
                    'data_lancamento' => '2026-07-09',
                    'data_vencimento' => '2026-07-10',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'reprovada',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa DRE cancelada Laravel',
                    'fornecedor' => 'Fornecedor DRE',
                    'valor_total' => 900,
                    'data_lancamento' => '2026-07-09',
                    'data_vencimento' => '2026-07-10',
                    'status_pagamento' => 'cancelado',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/relatorios/dre')
                ->assertStatus(200)
                ->assertSee('R$ 1.000,00')
                ->assertSee('R$ 250,00')
                ->assertSee('R$ 750,00')
                ->assertDontSee('R$ 700,00')
                ->assertDontSee('R$ 900,00');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/relatorios/fluxo-caixa')
                ->assertStatus(200)
                ->assertSee('R$ 1.000,00')
                ->assertSee('R$ 250,00')
                ->assertSee('R$ 750,00')
                ->assertDontSee('R$ 700,00')
                ->assertDontSee('R$ 900,00');
        } finally {
            DB::rollBack();
        }
    }

    public function test_dre_splits_direct_costs_from_operational_expenses(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda DRE gerencial Laravel',
                'ativo' => 1,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Sementes',
                'tipo' => 'insumo',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaCustoId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Taxas e juros',
                'tipo' => 'bancario',
                'cor' => '#f59e0b',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaDespesaId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'descricao' => 'Receita DRE gerencial Laravel',
                'valor_total' => 2000,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            DB::table('despesas')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaCustoId,
                    'descricao' => 'Custo direto DRE Laravel',
                    'fornecedor' => 'Fornecedor DRE',
                    'valor_total' => 700,
                    'data_lancamento' => '2026-07-09',
                    'data_vencimento' => '2026-07-10',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaDespesaId,
                    'descricao' => 'Despesa financeira DRE Laravel',
                    'fornecedor' => 'Fornecedor DRE',
                    'valor_total' => 300,
                    'data_lancamento' => '2026-07-09',
                    'data_vencimento' => '2026-07-10',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'usuario_id' => $this->userId(),
                ],
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/relatorios/dre')
                ->assertStatus(200)
                ->assertSee('Custos diretos')
                ->assertSee('Sementes')
                ->assertSee('Taxas e juros')
                ->assertSee('Despesas financeiras')
                ->assertSee('R$ 2.000,00')
                ->assertSee('R$ 700,00')
                ->assertSee('R$ 300,00')
                ->assertSee('R$ 1.000,00')
                ->assertSee('50,00%');
        } finally {
            DB::rollBack();
        }
    }

    public function test_dre_uses_period_filters(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda DRE periodo Laravel',
                'ativo' => 1,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria DRE periodo Laravel',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Receita DRE dentro periodo',
                    'valor_total' => 1200,
                    'data_venda' => '2026-07-15',
                    'status' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Receita DRE fora periodo',
                    'valor_total' => 3400,
                    'data_venda' => '2026-09-15',
                    'status' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
            ]);

            DB::table('despesas')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa DRE dentro periodo',
                    'valor_total' => 300,
                    'data_lancamento' => '2026-07-16',
                    'data_vencimento' => '2026-07-20',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa DRE fora periodo',
                    'valor_total' => 800,
                    'data_lancamento' => '2026-09-16',
                    'data_vencimento' => '2026-09-20',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/relatorios/dre?data_inicio=2026-07-01&data_fim=2026-07-31')
                ->assertStatus(200)
                ->assertSee('R$ 1.200,00')
                ->assertSee('R$ 300,00')
                ->assertSee('R$ 900,00')
                ->assertDontSee('R$ 3.400,00')
                ->assertDontSee('R$ 800,00');
        } finally {
            DB::rollBack();
        }
    }

    public function test_cash_flow_uses_period_filters_and_realized_dates(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda fluxo periodo Laravel',
                'ativo' => 1,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra fluxo periodo Laravel',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2026-12-31',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria fluxo periodo Laravel',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'descricao' => 'Receita recebida fluxo Laravel',
                    'valor_total' => 1000,
                    'data_venda' => '2026-07-05',
                    'data_recebimento' => '2026-07-20',
                    'status' => 'recebido',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'descricao' => 'Receita pendente fluxo Laravel',
                    'valor_total' => 600,
                    'data_venda' => '2026-07-10',
                    'data_recebimento' => null,
                    'status' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'descricao' => 'Receita fora fluxo Laravel',
                    'valor_total' => 900,
                    'data_venda' => '2026-08-10',
                    'data_recebimento' => null,
                    'status' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
            ]);

            DB::table('despesas')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa paga fora periodo fluxo Laravel',
                    'fornecedor' => 'Fornecedor fluxo',
                    'valor_total' => 400,
                    'data_lancamento' => '2026-07-01',
                    'data_vencimento' => '2026-07-15',
                    'data_pagamento' => '2026-08-01',
                    'status_pagamento' => 'pago',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa pendente fluxo Laravel',
                    'fornecedor' => 'Fornecedor fluxo',
                    'valor_total' => 250,
                    'data_lancamento' => '2026-07-03',
                    'data_vencimento' => '2026-07-25',
                    'data_pagamento' => null,
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'usuario_id' => $this->userId(),
                ],
            ]);

            $url = '/relatorios/fluxo-caixa?safra_id='.$safraId.'&data_inicio=2026-07-01&data_fim=2026-07-31';

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get($url)
                ->assertStatus(200)
                ->assertSee('Receitas previstas')
                ->assertSee('R$ 1.600,00')
                ->assertSee('R$ 650,00')
                ->assertSee('R$ 950,00')
                ->assertSee('R$ 1.000,00')
                ->assertSee('R$ 0,00')
                ->assertDontSee('R$ 900,00');
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_vs_actual_report_applies_main_filters(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda orcado realizado filtro Laravel',
                'ativo' => 1,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra filtro orcado Laravel',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2026-12-31',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria filtro orcado Laravel',
                'tipo' => 'insumo',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria fora filtro orcado Laravel',
                'tipo' => 'outros',
                'cor' => '#2f80ed',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaForaId = (int) DB::getPdo()->lastInsertId();

            DB::table('financeiro_projecoes')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'tipo_lancamento' => 'despesa',
                    'tipo_safra' => 'principal',
                    'ano_safra' => '2026/2027',
                    'mes_referencia' => '2026-07-01',
                    'categoria_id' => $categoriaId,
                    'valor_projetado' => 1000,
                ],
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'tipo_lancamento' => 'receita',
                    'tipo_safra' => 'principal',
                    'ano_safra' => '2026/2027',
                    'mes_referencia' => '2026-07-01',
                    'categoria_id' => $categoriaForaId,
                    'valor_projetado' => 3000,
                ],
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'tipo_lancamento' => 'despesa',
                    'tipo_safra' => 'principal',
                    'ano_safra' => '2026/2027',
                    'mes_referencia' => '2026-08-01',
                    'categoria_id' => $categoriaId,
                    'valor_projetado' => 500,
                ],
            ]);

            DB::table('despesas')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa realizada filtro orcado Laravel',
                    'fornecedor' => 'Fornecedor filtro',
                    'valor_total' => 250,
                    'data_lancamento' => '2026-07-09',
                    'data_vencimento' => '2026-07-10',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'usuario_id' => $this->userId(),
                ],
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'categoria_id' => $categoriaId,
                    'descricao' => 'Despesa fora periodo filtro orcado Laravel',
                    'fornecedor' => 'Fornecedor filtro',
                    'valor_total' => 450,
                    'data_lancamento' => '2026-08-09',
                    'data_vencimento' => '2026-08-10',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'usuario_id' => $this->userId(),
                ],
            ]);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaForaId,
                'descricao' => 'Receita fora tipo filtro orcado Laravel',
                'valor_total' => 1800,
                'data_venda' => '2026-07-09',
                'status' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            $url = '/relatorios/orcado-realizado?safra_id='.$safraId
                .'&categoria_id='.$categoriaId
                .'&tipo=custos_despesas'
                .'&data_inicio=2026-07-01'
                .'&data_fim=2026-07-31';

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get($url)
                ->assertStatus(200)
                ->assertSee('Categoria filtro orcado Laravel')
                ->assertSee('R$ 1.000,00')
                ->assertSee('R$ 250,00')
                ->assertSee('R$ -750,00')
                ->assertDontSee('R$ 3.000,00')
                ->assertDontSee('R$ 1.800,00')
                ->assertDontSee('R$ 500,00')
                ->assertDontSee('R$ 450,00');
        } finally {
            DB::rollBack();
        }
    }

    public function test_category_report_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/relatorios/categorias')
            ->assertStatus(200)
            ->assertSee('Relatório por Categoria')
            ->assertSee('Distribuição por categoria')
            ->assertSee('Total por tipo')
            ->assertSee('Sem dados para esta safra.')
            ->assertSee('Imprimir');
    }

    public function test_category_report_filters_receipts_expenses_category_and_period(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria relatorio receita Laravel',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaReceitaId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria relatorio despesa Laravel',
                'tipo' => 'insumo',
                'cor' => '#ef4444',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaDespesaId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaReceitaId,
                'descricao' => 'Receita categoria filtrada Laravel',
                'comprador' => 'Comprador categoria',
                'valor_total' => 900,
                'data_venda' => '2026-07-12',
                'data_recebimento' => '2026-07-15',
                'status' => 'recebido',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaDespesaId,
                'descricao' => 'Despesa categoria fora Laravel',
                'valor_total' => 700,
                'data_lancamento' => '2026-07-12',
                'data_vencimento' => '2026-07-15',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/relatorios/categorias?tipo=receitas&categoria_id='.$categoriaReceitaId.'&data_inicio=2026-07-01&data_fim=2026-07-31')
                ->assertStatus(200)
                ->assertSee('Categoria relatorio receita Laravel')
                ->assertSee('R$ 900,00')
                ->assertDontSee('Despesa categoria fora Laravel')
                ->assertDontSee('R$ 700,00');
        } finally {
            DB::rollBack();
        }
    }

    public function test_crop_and_field_reports_return_successful_responses(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/relatorios/safra')
            ->assertStatus(200)
            ->assertSee('Safra');

        $this->withSession($this->loggedSession())
            ->get('/relatorios/talhao')
            ->assertStatus(200)
            ->assertSee('Talhão');
    }

    public function test_crop_report_shows_profit_per_hectare_and_net_margin(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria relatorio safra Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'circle',
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra relatorio margem Laravel',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2027-06-30',
                'area_plantada' => 100,
                'producao_estimada' => 200,
                'preco_estimado' => 50,
                'status' => 'em_andamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Receita safra margem Laravel',
                'valor_total' => 2000,
                'data_venda' => '2026-08-01',
                'data_recebimento' => '2026-08-10',
                'status' => 'recebido',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa safra margem Laravel',
                'fornecedor' => 'Fornecedor safra margem',
                'valor_total' => 500,
                'data_lancamento' => '2026-08-02',
                'data_vencimento' => '2026-08-12',
                'data_pagamento' => '2026-08-12',
                'status_pagamento' => 'pago',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/relatorios/safra?safra_id='.$safraId)
                ->assertStatus(200)
                ->assertSee('Lucro/ha')
                ->assertSee('Margem liquida')
                ->assertSee('R$ 15,00')
                ->assertSee('75,0%');
        } finally {
            DB::rollBack();
        }
    }

    public function test_field_report_shows_cost_chart_per_hectare(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria relatorio talhao Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'circle',
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra relatorio talhao Laravel',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2027-06-30',
                'status' => 'em_andamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao grafico Laravel',
                'area' => 10,
                'area_bruta' => 10,
                'area_excluida_ha' => 0,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa talhao grafico Laravel',
                'fornecedor' => 'Fornecedor talhao grafico',
                'valor_total' => 500,
                'data_lancamento' => '2026-08-02',
                'data_vencimento' => '2026-08-12',
                'status_pagamento' => 'pendente',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/relatorios/talhao?safra_id='.$safraId)
                ->assertStatus(200)
                ->assertSee('chartTalhao')
                ->assertSee('Custo por talhao')
                ->assertSee('Talhao grafico Laravel')
                ->assertSee('R$ 50,00');
        } finally {
            DB::rollBack();
        }
    }

    public function test_kpi_report_page_returns_a_successful_response(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria kpi grafico Laravel',
                'tipo' => 'outros',
                'cor' => '#6c757d',
                'icone' => 'circle',
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra KPI grafico Laravel',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2027-06-30',
                'area_plantada' => 100,
                'producao_estimada' => 60,
                'producao_realizada' => 5000,
                'preco_estimado' => 80,
                'status' => 'em_andamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Receita KPI grafico Laravel',
                'valor_total' => 3000,
                'data_venda' => '2026-08-01',
                'status' => 'recebido',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa KPI grafico Laravel',
                'fornecedor' => 'Fornecedor KPI grafico',
                'valor_total' => 1000,
                'data_lancamento' => '2026-08-02',
                'data_vencimento' => '2026-08-12',
                'status_pagamento' => 'pago',
                'status_aprovacao' => 'aprovada',
                'usuario_id' => $this->userId(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/relatorios/kpis?safra_id='.$safraId)
                ->assertStatus(200)
                ->assertSee('KPIs / ROI')
                ->assertSee('Comparativo entre safras')
                ->assertSee('chartComp')
                ->assertSee('Safra KPI grafico Laravel')
                ->assertSee('R$ 2.000,00');
        } finally {
            DB::rollBack();
        }
    }

    public function test_crop_comparison_report_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/relatorios/comparativo-safras')
            ->assertStatus(200)
            ->assertSee('Comparativo de Safras')
            ->assertSee('Filtros');
    }

    public function test_crop_comparison_report_exports_files(): void
    {
        $csvResponse = $this->withSession($this->loggedSession())
            ->get('/relatorios/comparativo-safras/exportar?formato=csv');

        $csvResponse->assertStatus(200)->assertDownload();
        $this->assertStringContainsString('Categoria', $csvResponse->streamedContent());

        $excelResponse = $this->withSession($this->loggedSession())
            ->get('/relatorios/comparativo-safras/exportar?formato=excel');

        $excelResponse->assertStatus(200)
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString('<table', $excelResponse->streamedContent());

        $pdfResponse = $this->withSession($this->loggedSession())
            ->get('/relatorios/comparativo-safras/exportar?formato=pdf');

        $pdfResponse->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());

        $legacyResponse = $this->withSession($this->loggedSession())
            ->get('/relatorios/comparativo-safras?export=csv');

        $legacyResponse->assertStatus(200)->assertDownload();
        $this->assertStringContainsString('Categoria', $legacyResponse->streamedContent());
    }

    public function test_audit_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession(profile: 'gestor_propriedade'))
            ->get('/auditoria')
            ->assertStatus(200)
            ->assertSee('Auditoria')
            ->assertSee('Registros');
    }

    public function test_audit_page_requires_authorized_profile(): void
    {
        $this->withSession($this->loggedSession(profile: 'visualizador'))
            ->get('/auditoria')
            ->assertStatus(403);
    }

    public function test_audit_page_shows_only_selected_property_logs(): void
    {
        DB::beginTransaction();

        try {
            DB::table('propriedades')->insert([
                'nome' => 'Fazenda auditoria isolada A',
                'municipio' => 'Rio Verde',
                'estado' => 'GO',
                'area_total' => 100,
                'responsavel' => 'Responsável auditoria',
                'plano' => 'premium',
                'ativo' => 1,
            ]);
            $propertyId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Fazenda auditoria isolada B',
                'municipio' => 'Jataí',
                'estado' => 'GO',
                'area_total' => 80,
                'responsavel' => 'Responsável auditoria',
                'plano' => 'premium',
                'ativo' => 1,
            ]);
            $otherPropertyId = (int) DB::getPdo()->lastInsertId();

            foreach ([
                ['acao' => 'salvar_usuario', 'tabela' => 'usuarios', 'registro_id' => 10, 'propriedade_id' => $propertyId, 'detalhes' => 'auditoria-propriedade-correta'],
                ['acao' => 'salvar_usuario', 'tabela' => 'usuarios', 'registro_id' => 11, 'propriedade_id' => $otherPropertyId, 'detalhes' => 'auditoria-outra-propriedade'],
                ['acao' => 'login', 'tabela' => 'usuarios', 'registro_id' => $this->userId(), 'propriedade_id' => null, 'detalhes' => 'auditoria-login-global'],
                ['acao' => 'editar_propriedade', 'tabela' => 'propriedades', 'registro_id' => $propertyId, 'propriedade_id' => null, 'detalhes' => 'auditoria-admin-sem-propriedade'],
                ['acao' => 'liberar_edicao_sistema', 'tabela' => 'usuarios', 'registro_id' => $this->userId(), 'propriedade_id' => $propertyId, 'detalhes' => 'auditoria-admin-liberado-na-propriedade'],
            ] as $log) {
                DB::table('logs_auditoria')->insert($log + [
                    'usuario_id' => $this->userId(),
                    'ip' => '127.0.0.1',
                    'criado_em' => now(),
                ]);
            }

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade'))
                ->get('/auditoria')
                ->assertStatus(200)
                ->assertSee('auditoria-propriedade-correta')
                ->assertSee('auditoria-admin-liberado-na-propriedade')
                ->assertDontSee('auditoria-outra-propriedade')
                ->assertDontSee('auditoria-login-global')
                ->assertDontSee('auditoria-admin-sem-propriedade');
        } finally {
            DB::rollBack();
        }
    }

    public function test_audit_export_downloads_filtered_csv(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('logs_auditoria')->insert([
                'usuario_id' => $this->userId(),
                'acao' => 'exportar_auditoria_teste',
                'tabela' => 'despesas',
                'registro_id' => 123,
                'propriedade_id' => $propertyId,
                'detalhes' => 'detalhe-exportacao-laravel',
                'ip' => '127.0.0.1',
                'criado_em' => now(),
            ]);

            $response = $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade'))
                ->get('/auditoria/exportar?busca=detalhe-exportacao-laravel')
                ->assertStatus(200)
                ->assertDownload();

            $this->assertStringContainsString('detalhe-exportacao-laravel', $response->streamedContent());
            $this->assertStringContainsString('Data;Usuario;Acao;Area;Registro;Detalhes;IP', $response->streamedContent());
        } finally {
            DB::rollBack();
        }
    }

    public function test_audit_filters_by_area_expense_type_and_period(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria auditoria insumo',
                'tipo' => 'insumo',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaInsumoId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria auditoria servico',
                'tipo' => 'servico',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaServicoId = (int) DB::getPdo()->lastInsertId();

            foreach ([[$categoriaInsumoId, 'detalhe-auditoria-insumo'], [$categoriaServicoId, 'detalhe-auditoria-servico']] as [$categoriaId, $detalhe]) {
                DB::table('despesas')->insert([
                    'propriedade_id' => $propertyId,
                    'categoria_id' => $categoriaId,
                    'descricao' => $detalhe,
                    'valor_total' => 100,
                    'data_lancamento' => '2026-07-10',
                    'data_vencimento' => '2026-07-10',
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'aprovada',
                    'usuario_id' => $this->userId(),
                ]);
                $despesaId = (int) DB::getPdo()->lastInsertId();

                DB::table('logs_auditoria')->insert([
                    'usuario_id' => $this->userId(),
                    'acao' => 'nova_despesa',
                    'tabela' => 'despesas',
                    'registro_id' => $despesaId,
                    'propriedade_id' => $propertyId,
                    'detalhes' => $detalhe,
                    'ip' => '127.0.0.1',
                    'criado_em' => '2026-07-10 08:00:00',
                ]);
            }

            $query = 'lancamento=despesas&tipo_despesa=insumo&inicio=2026-07-01&fim=2026-07-31';

            $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade'))
                ->get('/auditoria?'.$query)
                ->assertStatus(200)
                ->assertSee('detalhe-auditoria-insumo')
                ->assertDontSee('detalhe-auditoria-servico');

            $response = $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gestor_propriedade'))
                ->get('/auditoria/exportar?'.$query)
                ->assertStatus(200)
                ->assertDownload();

            $conteudo = $response->streamedContent();
            $this->assertStringContainsString('detalhe-auditoria-insumo', $conteudo);
            $this->assertStringNotContainsString('detalhe-auditoria-servico', $conteudo);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_pages_return_successful_responses(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/orcamento')
            ->assertStatus(200)
            ->assertSee('Orçamento');

        $this->withSession($this->loggedSession())
            ->get('/orcamento/novo')
            ->assertStatus(200)
            ->assertSee('Nova projeção');
        $projecaoId = DB::table('financeiro_projecoes')->orderByDesc('id')->value('id');
        if ($projecaoId) {
            $this->withSession($this->loggedSession())
                ->get('/orcamento/'.$projecaoId.'/editar')
                ->assertStatus(200)
                ->assertSee('Editar projeção');
        }
    }

    public function test_budget_projection_ignores_external_crop_season(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria orcamento externo',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Propriedade orcamento externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra orcamento externa',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraExternaId = (int) DB::getPdo()->lastInsertId();

            $observacao = 'Projecao com safra externa '.uniqid();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento', [
                    'tipo_lancamento' => 'despesa',
                    'tipo_safra' => 'principal',
                    'ano_safra' => '2026/2027',
                    'mes_referencia' => '2026-07-01',
                    'safra_id' => $safraExternaId,
                    'categoria_id' => $categoriaId,
                    'valor_projetado' => '1.500,00',
                    'observacoes' => $observacao,
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseHas('financeiro_projecoes', [
                'propriedade_id' => $propertyId,
                'safra_id' => null,
                'categoria_id' => $categoriaId,
                'observacoes' => $observacao,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_recurring_projection_creates_monthly_items(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria recorrente Laravel',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra recorrente Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            $observacao = 'Recorrencia Laravel '.uniqid();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/recorrente', [
                    'safra_id' => $safraId,
                    'tipo_safra' => 'principal',
                    'ano_safra' => '2026/2027',
                    'categoria_id' => $categoriaId,
                    'mes_inicial' => '2026-07',
                    'mes_final' => '2026-09',
                    'valor_projetado' => '2.500,00',
                    'observacoes' => $observacao,
                ])
                ->assertRedirect('/orcamento');

            $rows = DB::table('financeiro_projecoes')
                ->where('propriedade_id', $propertyId)
                ->where('safra_id', $safraId)
                ->where('categoria_id', $categoriaId)
                ->where('observacoes', $observacao)
                ->orderBy('mes_referencia')
                ->get(['mes_referencia', 'valor_projetado', 'tipo_lancamento', 'recorrencia_grupo']);

            $this->assertCount(3, $rows);
            $this->assertSame(['2026-07-01', '2026-08-01', '2026-09-01'], $rows->pluck('mes_referencia')->map(fn ($date) => (string) $date)->all());
            $this->assertSame('2500.00', (string) $rows->first()->valor_projetado);
            $this->assertSame('despesa', (string) $rows->first()->tipo_lancamento);
            $this->assertCount(1, $rows->pluck('recorrencia_grupo')->unique());
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_update_crop_season_base_data(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra base orcamento Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/base-safra', [
                    'safra_id' => $safraId,
                    'area_plantada' => '123,45',
                    'producao_estimada' => '67,89',
                    'preco_estimado' => '145,50',
                ])
                ->assertRedirect('/orcamento');

            $safra = DB::table('safras')
                ->where('id', $safraId)
                ->where('propriedade_id', $propertyId)
                ->first(['area_plantada', 'producao_estimada', 'preco_estimado']);

            $this->assertSame('123.45', (string) $safra->area_plantada);
            $this->assertSame('67.89', (string) $safra->producao_estimada);
            $this->assertSame('145.50', (string) $safra->preco_estimado);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/base-safra', [
                    'safra_id' => $safraId,
                    'area_plantada' => '0',
                    'producao_estimada' => '',
                    'preco_estimado' => '0,00',
                ])
                ->assertRedirect('/orcamento');

            $safraZerada = DB::table('safras')
                ->where('id', $safraId)
                ->where('propriedade_id', $propertyId)
                ->first(['area_plantada', 'producao_estimada', 'preco_estimado']);

            $this->assertNull($safraZerada->area_plantada);
            $this->assertNull($safraZerada->producao_estimada);
            $this->assertNull($safraZerada->preco_estimado);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_create_or_reuse_planning_category(): void
    {
        DB::beginTransaction();

        try {
            $nome = 'Categoria planejamento atalho '.uniqid();

            $this->withSession($this->loggedSession())
                ->post('/orcamento/categorias', [
                    'nome' => '  '.$nome.'  ',
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseHas('categorias', [
                'nome' => $nome,
                'tipo' => 'outros',
                'cor' => '#2fc89b',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);

            $categoriaId = (int) DB::table('categorias')->where('nome', $nome)->value('id');
            DB::table('categorias')->where('id', $categoriaId)->update(['ativo' => 0]);

            $this->withSession($this->loggedSession())
                ->post('/orcamento/categorias', [
                    'nome' => strtolower($nome),
                ])
                ->assertRedirect('/orcamento');

            $this->assertSame(1, DB::table('categorias')->whereRaw('LOWER(nome) = LOWER(?)', [$nome])->count());
            $this->assertDatabaseHas('categorias', [
                'id' => $categoriaId,
                'ativo' => 1,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_create_or_reuse_planning_crop(): void
    {
        DB::beginTransaction();

        try {
            $nome = 'Cultura planejamento atalho '.uniqid();

            $this->withSession($this->loggedSession())
                ->post('/orcamento/culturas', [
                    'nome' => '  '.$nome.'  ',
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseHas('culturas', [
                'nome' => $nome,
                'unidade_producao' => 'sc',
            ]);

            $this->withSession($this->loggedSession())
                ->post('/orcamento/culturas', [
                    'nome' => strtoupper($nome),
                ])
                ->assertRedirect('/orcamento');

            $this->assertSame(1, DB::table('culturas')->whereRaw('LOWER(nome) = LOWER(?)', [$nome])->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_create_retroactive_crop_season(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('culturas')->insert([
                'nome' => 'Cultura retroativa Laravel',
                'unidade_producao' => 'sc',
            ]);
            $culturaId = (int) DB::getPdo()->lastInsertId();

            $descricao = 'Safra retroativa Laravel '.uniqid();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/safras-retroativas', [
                    'descricao' => $descricao,
                    'cultura_id' => $culturaId,
                    'data_inicio' => '2026-04-01',
                    'data_fim' => '2026-06-30',
                    'area_plantada' => '88,50',
                    'producao_estimada' => '55,25',
                    'producao_realizada' => '4700,00',
                    'preco_estimado' => '130,75',
                    'observacoes' => '',
                ])
                ->assertRedirect('/orcamento');

            $safra = DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->where('descricao', $descricao)
                ->first();

            $this->assertNotNull($safra);
            $this->assertSame($culturaId, (int) $safra->cultura_id);
            $this->assertSame('primeira', (string) $safra->safra_referencia);
            $this->assertSame('encerrada', (string) $safra->status);
            $this->assertSame('2026-04-01', (string) $safra->data_inicio);
            $this->assertSame('2026-06-30', (string) $safra->data_fim);
            $this->assertSame('88.50', (string) $safra->area_plantada);
            $this->assertSame('55.25', (string) $safra->producao_estimada);
            $this->assertSame('4700.00', (string) $safra->producao_realizada);
            $this->assertSame('130.75', (string) $safra->preco_estimado);
            $this->assertSame('Safra retroativa cadastrada pelo planejamento da safra.', (string) $safra->observacoes);

            $this->assertDatabaseHas('anos_agricolas', [
                'propriedade_id' => $propertyId,
                'ano_inicio' => 2025,
                'descricao' => '2025/26',
                'data_inicio' => '2025-07-01',
                'data_fim' => '2026-06-30',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_save_agricultural_year(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/anos-agricolas', [
                    'ano_inicio' => 2028,
                    'observacoes' => 'Ano agricola criado pelo teste Laravel',
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseHas('anos_agricolas', [
                'propriedade_id' => $propertyId,
                'ano_inicio' => 2028,
                'descricao' => '2028/29',
                'data_inicio' => '2028-07-01',
                'data_fim' => '2029-06-30',
                'observacoes' => 'Ano agricola criado pelo teste Laravel',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/anos-agricolas', [
                    'ano_inicio' => 2028,
                    'observacoes' => 'Ano agricola atualizado pelo teste Laravel',
                ])
                ->assertRedirect('/orcamento');

            $this->assertSame(1, DB::table('anos_agricolas')
                ->where('propriedade_id', $propertyId)
                ->where('ano_inicio', 2028)
                ->count());
            $this->assertDatabaseHas('anos_agricolas', [
                'propriedade_id' => $propertyId,
                'ano_inicio' => 2028,
                'observacoes' => 'Ano agricola atualizado pelo teste Laravel',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_create_planned_field_activity(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra atividade orcamento Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('talhoes')->insert([
                'propriedade_id' => $propertyId,
                'nome' => 'Talhao atividade orcamento Laravel',
                'area' => 15,
                'ativo' => 1,
            ]);
            $talhaoId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/atividades-planejadas', [
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoId,
                    'tipo' => 'plantio',
                    'data_inicio' => '2026-09-10',
                    'data_fim' => '2026-09-12',
                    'area_executada' => '12,50',
                    'descricao' => 'Plantio planejado pelo orcamento',
                    'responsavel' => 'Equipe Campo',
                    'servico' => 'Plantio',
                    'produto' => 'Semente teste',
                    'custo_estimado' => '1.250,75',
                    'observacoes' => '',
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseHas('atividades_campo', [
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'talhao_id' => $talhaoId,
                'tipo' => 'plantio',
                'status' => 'planejada',
                'descricao' => 'Plantio planejado pelo orcamento',
                'responsavel' => 'Equipe Campo',
                'servico' => 'Plantio',
                'produto' => 'Semente teste',
                'data_inicio' => '2026-09-10',
                'data_fim' => '2026-09-12',
                'area_executada' => '12.50',
                'custo_estimado' => '1250.75',
                'observacoes' => 'Planejamento operacional da safra',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_list_and_delete_planned_field_activity(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra lista atividade orcamento Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('atividades_campo')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'tipo' => 'manejo',
                'data_inicio' => '2026-09-20',
                'status' => 'planejada',
                'descricao' => 'Atividade planejada para listar',
                'custo_estimado' => 500,
            ]);
            $atividadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('atividades_campo')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'tipo' => 'manejo',
                'data_inicio' => '2026-09-21',
                'status' => 'concluida',
                'descricao' => 'Atividade concluida preservada',
            ]);
            $atividadeConcluidaId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Propriedade atividade orcamento externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('atividades_campo')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'tipo' => 'manejo',
                'data_inicio' => '2026-09-22',
                'status' => 'planejada',
                'descricao' => 'Atividade externa preservada',
            ]);
            $atividadeExternaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/orcamento')
                ->assertStatus(200)
                ->assertSee('Atividade planejada para listar');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->delete('/orcamento/atividades-planejadas/'.$atividadeId)
                ->assertRedirect('/orcamento');

            $this->assertDatabaseMissing('atividades_campo', [
                'id' => $atividadeId,
                'propriedade_id' => $propertyId,
            ]);
            $this->assertDatabaseHas('atividades_campo', [
                'id' => $atividadeConcluidaId,
                'status' => 'concluida',
            ]);
            $this->assertDatabaseHas('atividades_campo', [
                'id' => $atividadeExternaId,
                'propriedade_id' => $outraPropriedadeId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_add_planned_expense_inline(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('culturas')->insert([
                'nome' => 'Cultura despesa planejada Laravel',
                'unidade_producao' => 'sc',
            ]);
            $culturaId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria despesa planejada Laravel',
                'tipo' => 'outros',
                'cor' => '#2fc89b',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'cultura_id' => $culturaId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra despesa planejada Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/despesas-planejadas', [
                    'safra_id' => $safraId,
                    'cultura_id' => $culturaId,
                    'categoria_id' => $categoriaId,
                    'mes_referencia' => '2026-10',
                    'valor_projetado' => '3.450,80',
                    'observacoes' => 'Despesa avulsa do planejamento',
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseHas('financeiro_projecoes', [
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'cultura_id' => $culturaId,
                'tipo_lancamento' => 'despesa',
                'tipo_safra' => 'principal',
                'ano_safra' => 'Safra despesa planejada Laravel',
                'mes_referencia' => '2026-10-01',
                'categoria_id' => $categoriaId,
                'valor_projetado' => '3450.80',
                'observacoes' => 'Despesa avulsa do planejamento',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_update_projection_rows_in_batch(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria lote origem Laravel',
                'tipo' => 'outros',
                'cor' => '#2fc89b',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaOrigemId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria lote destino Laravel',
                'tipo' => 'insumo',
                'cor' => '#35c49a',
                'icone' => 'bi-box',
                'ativo' => 1,
            ]);
            $categoriaDestinoId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'categoria_pai_id' => $categoriaDestinoId,
                'nome' => 'Subcategoria lote destino Laravel',
                'tipo' => 'insumo',
                'cor' => '#35c49a',
                'icone' => 'bi-box',
                'ativo' => 1,
            ]);
            $subcategoriaDestinoId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra lote novo Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('financeiro_projecoes')->insert([
                'propriedade_id' => $propertyId,
                'tipo_lancamento' => 'despesa',
                'tipo_safra' => 'principal',
                'ano_safra' => '2026/2027',
                'mes_referencia' => '2026-08-01',
                'categoria_id' => $categoriaOrigemId,
                'quantidade' => 1,
                'unidade' => 'un',
                'valor_unitario' => 100,
                'valor_projetado' => 100,
                'observacoes' => 'Original lote 1',
            ]);
            $projecaoId = (int) DB::getPdo()->lastInsertId();

            DB::table('propriedades')->insert([
                'nome' => 'Propriedade lote externa',
                'ativo' => 1,
            ]);
            $outraPropriedadeId = (int) DB::getPdo()->lastInsertId();

            DB::table('financeiro_projecoes')->insert([
                'propriedade_id' => $outraPropriedadeId,
                'tipo_lancamento' => 'despesa',
                'tipo_safra' => 'principal',
                'ano_safra' => '2026/2027',
                'mes_referencia' => '2026-08-01',
                'categoria_id' => $categoriaOrigemId,
                'valor_projetado' => 999,
                'observacoes' => 'Nao pode alterar',
            ]);
            $projecaoExternaId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/projecoes/lote', [
                    'projecao_id' => [$projecaoId, $projecaoExternaId, 0],
                    'categoria_id' => [$categoriaDestinoId, $categoriaDestinoId, $categoriaDestinoId],
                    'subcategoria_id' => [$subcategoriaDestinoId, '', $subcategoriaDestinoId],
                    'mes_referencia' => ['2026-12', '2026-12', '2027-01'],
                    'safra_id' => ['', '', $safraId],
                    'tipo_lancamento' => ['', '', 'despesa'],
                    'tipo_safra' => ['', '', 'principal'],
                    'ano_safra' => ['', '', 'Safra lote novo Laravel'],
                    'quantidade' => ['5,50', '9', '2'],
                    'unidade' => ['Litro', 'kg', 'ha'],
                    'valor_unitario' => ['22,30', '1', '50,00'],
                    'valor_projetado' => ['122,65', '10', '100,00'],
                    'observacoes' => ['Atualizado em lote', 'Tentativa externa', 'Linha nova em lote'],
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseHas('financeiro_projecoes', [
                'id' => $projecaoId,
                'propriedade_id' => $propertyId,
                'categoria_id' => $categoriaDestinoId,
                'subcategoria_id' => $subcategoriaDestinoId,
                'mes_referencia' => '2026-12-01',
                'quantidade' => '5.50',
                'unidade' => 'Litro',
                'valor_unitario' => '22.30',
                'valor_projetado' => '122.65',
                'observacoes' => 'Atualizado em lote',
            ]);
            $this->assertDatabaseHas('financeiro_projecoes', [
                'id' => $projecaoExternaId,
                'propriedade_id' => $outraPropriedadeId,
                'valor_projetado' => '999.00',
                'observacoes' => 'Nao pode alterar',
            ]);
            $this->assertDatabaseHas('financeiro_projecoes', [
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaDestinoId,
                'subcategoria_id' => $subcategoriaDestinoId,
                'tipo_lancamento' => 'despesa',
                'tipo_safra' => 'principal',
                'ano_safra' => 'Safra lote novo Laravel',
                'mes_referencia' => '2027-01-01',
                'quantidade' => '2.00',
                'unidade' => 'ha',
                'valor_unitario' => '50.00',
                'valor_projetado' => '100.00',
                'observacoes' => 'Linha nova em lote',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_add_planned_input_inline(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('culturas')->insert([
                'nome' => 'Cultura insumo planejado Laravel',
                'unidade_producao' => 'sc',
            ]);
            $culturaId = (int) DB::getPdo()->lastInsertId();

            DB::table('categorias')->insert([
                'nome' => 'Categoria insumo planejado Laravel',
                'tipo' => 'insumo',
                'cor' => '#2fc89b',
                'icone' => 'bi-box',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'cultura_id' => $culturaId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra insumo planejado Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/insumos-planejados', [
                    'safra_id' => $safraId,
                    'cultura_id' => $culturaId,
                    'categoria_id' => $categoriaId,
                    'data_utilizacao' => '2026-11-15',
                    'quantidade' => '12,50',
                    'unidade' => 'Litro',
                    'valor_unitario' => '80,40',
                    'observacoes' => '',
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseHas('financeiro_projecoes', [
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'cultura_id' => $culturaId,
                'tipo_lancamento' => 'despesa',
                'tipo_safra' => 'principal',
                'ano_safra' => 'Safra insumo planejado Laravel',
                'mes_referencia' => '2026-11-15',
                'categoria_id' => $categoriaId,
                'quantidade' => '12.50',
                'unidade' => 'Litro',
                'valor_unitario' => '80.40',
                'valor_projetado' => '1005.00',
                'observacoes' => 'Insumo planejado da safra',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_budget_can_copy_previous_crop_season_projection(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria copia safra Laravel',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra origem copia Laravel',
                'data_inicio' => '2026-06-01',
                'status' => 'encerrada',
            ]);
            $safraOrigemId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra destino copia Laravel',
                'data_inicio' => '2026-07-01',
                'status' => 'planejamento',
            ]);
            $safraDestinoId = (int) DB::getPdo()->lastInsertId();

            DB::table('financeiro_projecoes')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraOrigemId,
                'tipo_lancamento' => 'despesa',
                'tipo_safra' => 'principal',
                'ano_safra' => '2025/2026',
                'mes_referencia' => '2026-07-01',
                'categoria_id' => $categoriaId,
                'quantidade' => 2,
                'unidade' => 'ha',
                'valor_unitario' => 100,
                'valor_projetado' => 200,
                'observacoes' => 'Original copia',
            ]);

            DB::table('financeiro_projecoes')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraDestinoId,
                'tipo_lancamento' => 'despesa',
                'tipo_safra' => 'principal',
                'ano_safra' => '2026/2027',
                'mes_referencia' => '2026-07-01',
                'categoria_id' => $categoriaId,
                'valor_projetado' => 999,
                'observacoes' => 'Destino antigo copia',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/orcamento/copiar-safra-anterior', [
                    'safra_id' => $safraDestinoId,
                ])
                ->assertRedirect('/orcamento');

            $this->assertDatabaseMissing('financeiro_projecoes', [
                'propriedade_id' => $propertyId,
                'safra_id' => $safraDestinoId,
                'observacoes' => 'Destino antigo copia',
            ]);

            $row = DB::table('financeiro_projecoes')
                ->where('propriedade_id', $propertyId)
                ->where('safra_id', $safraDestinoId)
                ->where('categoria_id', $categoriaId)
                ->first();

            $this->assertNotNull($row);
            $this->assertSame('2026-08-01', (string) $row->mes_referencia);
            $this->assertSame('200.00', (string) $row->valor_projetado);
            $this->assertSame('Safra destino copia Laravel', (string) $row->ano_safra);
            $this->assertStringContainsString('Original copia', (string) $row->observacoes);
            $this->assertStringContainsString('Copiado de Safra origem copia Laravel', (string) $row->observacoes);
        } finally {
            DB::rollBack();
        }
    }

    public function test_financial_planning_page_returns_a_successful_response(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/financeiro/planejamento')
            ->assertStatus(200)
            ->assertSee('Resultado da Safra x Projetado')
            ->assertSee('Resultado por categoria');
    }

    public function test_financial_planning_uses_revenue_minus_expense_results(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('categorias')->insert([
                'nome' => 'Categoria planejamento resultado',
                'tipo' => 'outros',
                'cor' => '#35c49a',
                'icone' => 'bi-tag',
                'ativo' => 1,
            ]);
            $categoriaId = (int) DB::getPdo()->lastInsertId();

            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'safra_referencia' => 'primeira',
                'descricao' => 'Safra planejamento resultado',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2027-06-30',
                'status' => 'planejamento',
            ]);
            $safraId = (int) DB::getPdo()->lastInsertId();

            DB::table('financeiro_projecoes')->insert([
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'tipo_lancamento' => 'receita',
                    'tipo_safra' => 'principal',
                    'ano_safra' => '2026/2027',
                    'mes_referencia' => '2026-07-01',
                    'categoria_id' => $categoriaId,
                    'valor_projetado' => 1000,
                ],
                [
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraId,
                    'tipo_lancamento' => 'despesa',
                    'tipo_safra' => 'principal',
                    'ano_safra' => '2026/2027',
                    'mes_referencia' => '2026-07-01',
                    'categoria_id' => $categoriaId,
                    'valor_projetado' => 300,
                ],
            ]);

            DB::table('receitas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Receita planejamento resultado',
                'valor_total' => 800,
                'data_venda' => '2026-07-10',
                'status' => 'recebido',
                'status_aprovacao' => 'aprovada',
            ]);

            DB::table('despesas')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'categoria_id' => $categoriaId,
                'descricao' => 'Despesa planejamento resultado',
                'valor_total' => 250,
                'data_lancamento' => '2026-07-12',
                'status_pagamento' => 'pago',
                'status_aprovacao' => 'aprovada',
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/financeiro/planejamento?safra_planejamento='.$safraId)
                ->assertStatus(200)
                ->assertSee('R$ 550,00')
                ->assertSee('R$ 700,00');
        } finally {
            DB::rollBack();
        }
    }

    public function test_invoice_xml_import_stores_invoice_and_item(): void
    {
        DB::beginTransaction();
        Storage::fake('local');

        try {
            $accessKey = '35260712345678000195550010000012341000012345';
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$accessKey}">
      <ide><serie>1</serie><nNF>1234</nNF><dhEmi>2026-07-09T08:00:00-03:00</dhEmi></ide>
      <emit><CNPJ>12345678000195</CNPJ><xNome>Fornecedor Teste</xNome></emit>
      <dest><CNPJ>98765432000110</CNPJ><xNome>Fazenda Teste</xNome></dest>
      <det nItem="1"><prod><cProd>P001</cProd><xProd>Produto XML Teste</xProd><uCom>UN</uCom><qCom>2.0000</qCom><vUnCom>50.0000</vUnCom><vProd>100.00</vProd></prod></det>
      <total><ICMSTot><vNF>100.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
</nfeProc>
XML;

            $tmp = tempnam(sys_get_temp_dir(), 'nfe-test-');
            file_put_contents($tmp, $xml);

            $invoiceId = app(NotaFiscalXmlService::class)->importar(
                new UploadedFile($tmp, 'nfe.xml', 'text/xml', null, true),
                app(FarmContext::class)->propertyId(),
                $this->userId()
            );

            $invoice = DB::table('fiscal_invoices')->where('access_key', $accessKey)->first();
            $this->assertNotNull($invoice);
            $this->assertSame($invoiceId, (int) $invoice->id);
            $this->assertSame('1234', $invoice->invoice_number);
            $item = DB::table('fiscal_invoice_items')->where('invoice_id', $invoice->id)->first();
            $this->assertNotNull($item);
            $this->assertSame('Produto XML Teste', $item->description);
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'criar_nota_fiscal_xml',
                'tabela' => 'fiscal_invoices',
                'registro_id' => $invoiceId,
                'propriedade_id' => (int) $invoice->propriedade_id,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_invoice_xml_web_flow_previews_before_confirming(): void
    {
        DB::beginTransaction();
        Storage::fake('local');

        try {
            $accessKey = '35260712345678000195550010000043211000043210';
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$accessKey}">
      <ide><serie>1</serie><nNF>4321</nNF><dhEmi>2026-07-09T08:00:00-03:00</dhEmi></ide>
      <emit><CNPJ>12345678000195</CNPJ><xNome>Fornecedor Preview</xNome></emit>
      <dest><CNPJ>98765432000110</CNPJ><xNome>Fazenda Teste</xNome></dest>
      <det nItem="1"><prod><cProd>P4321</cProd><xProd>Produto Preview XML</xProd><uCom>UN</uCom><qCom>3.0000</qCom><vUnCom>25.0000</vUnCom><vProd>75.00</vProd></prod></det>
      <total><ICMSTot><vNF>75.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
</nfeProc>
XML;

            $file = UploadedFile::fake()->createWithContent('preview-nfe.xml', $xml);
            $propertyId = app(FarmContext::class)->propertyId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/notas/importar', ['xml' => $file])
                ->assertRedirect('/fiscal/notas/importar')
                ->assertSessionHas('fiscal_invoice_preview');

            $this->assertDatabaseMissing('fiscal_invoices', [
                'access_key' => $accessKey,
            ]);

            $this->withSession([...$this->loggedSession(propertyId: $propertyId), 'fiscal_invoice_preview' => session('fiscal_invoice_preview')])
                ->get('/fiscal/notas/importar')
                ->assertStatus(200)
                ->assertSee('Conferencia da nota fiscal')
                ->assertSee('Produto Preview XML');

            $this->withSession([...$this->loggedSession(propertyId: $propertyId), 'fiscal_invoice_preview' => session('fiscal_invoice_preview')])
                ->post('/fiscal/notas/importar/confirmar')
                ->assertRedirect();

            $invoice = DB::table('fiscal_invoices')->where('access_key', $accessKey)->first();
            $this->assertNotNull($invoice);
            $this->assertSame('aguardando_aprovacao', $invoice->status);
            $this->assertDatabaseHas('fiscal_invoice_items', [
                'invoice_id' => $invoice->id,
                'description' => 'Produto Preview XML',
                'total_value' => '75.00',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_invoice_xml_import_rejects_non_xml_file(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('nota.txt', '<xml></xml>');

        $this->withSession($this->loggedSession())
            ->from('/fiscal/notas/importar')
            ->post('/fiscal/notas/importar', ['xml' => $file])
            ->assertRedirect('/fiscal/notas/importar')
            ->assertSessionHasErrors('xml');
    }

    public function test_fiscal_invoices_page_returns_a_successful_response(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);

            DB::table('fiscal_invoices')->insert([
                'propriedade_id' => $propertyId,
                'user_id' => $this->userId(),
                'access_key' => 'NFE-LARAVEL-LIST-'.uniqid(),
                'invoice_number' => 'NF-LARAVEL-LIST',
                'series' => '1',
                'issue_date' => '2026-07-09',
                'issuer_cnpj' => '12345678000195',
                'issuer_name' => 'Fornecedor listagem Laravel',
                'recipient_cnpj' => '98765432000110',
                'recipient_name' => 'Fazenda Teste',
                'total_value' => 210,
                'status' => 'aguardando_aprovacao',
                'xml_file_path' => 'storage/app/private/teste-list.xml',
                'created_by' => $this->userId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/fiscal/notas')
                ->assertStatus(200)
                ->assertSee('Notas Fiscais')
                ->assertSee('Notas fiscais importadas')
                ->assertSee('NF-LARAVEL-LIST')
                ->assertSee('/fiscal/notas/'.$invoiceId.'/xml', false);
        } finally {
            DB::rollBack();
        }
    }

    public function test_fiscal_invoice_approval_updates_status(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            session(['propriedade_id' => $propertyId]);

            DB::table('fiscal_invoices')->insert([
                'propriedade_id' => $propertyId,
                'user_id' => $this->userId(),
                'access_key' => 'NFE-LARAVEL-APPROVAL-'.uniqid(),
                'invoice_number' => 'NF-LARAVEL-APPROVAL',
                'series' => '1',
                'issue_date' => '2026-07-09',
                'issuer_cnpj' => '12345678000195',
                'issuer_name' => 'Fornecedor aprovacao Laravel',
                'recipient_cnpj' => '98765432000110',
                'recipient_name' => 'Fazenda Teste',
                'total_value' => 210,
                'status' => 'aguardando_aprovacao',
                'xml_file_path' => 'storage/app/private/teste.xml',
                'created_by' => $this->userId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/notas/'.$invoiceId.'/aprovar')
                ->assertRedirect('/fiscal/notas');

            $this->assertDatabaseHas('fiscal_invoices', [
                'id' => $invoiceId,
                'status' => 'aprovada',
                'previous_status' => 'aguardando_aprovacao',
            ]);
            $this->assertNotNull(DB::table('fiscal_invoices')->where('id', $invoiceId)->value('approval_metadata'));
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'aprovar_nota_fiscal',
                'tabela' => 'fiscal_invoices',
                'registro_id' => $invoiceId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_fiscal_invoice_can_be_rejected(): void
    {
        DB::beginTransaction();

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            session(['propriedade_id' => $propertyId]);

            DB::table('fiscal_invoices')->insert([
                'propriedade_id' => $propertyId,
                'user_id' => $this->userId(),
                'access_key' => 'NFE-LARAVEL-REJEITAR-'.uniqid(),
                'invoice_number' => 'NF-LARAVEL-REJEITAR',
                'series' => '1',
                'issue_date' => '2026-07-09',
                'issuer_cnpj' => '12345678000195',
                'issuer_name' => 'Fornecedor rejeição Laravel',
                'recipient_cnpj' => '98765432000110',
                'recipient_name' => 'Fazenda Teste',
                'total_value' => 210,
                'status' => 'aguardando_aprovacao',
                'xml_file_path' => 'storage/app/private/teste-rejeitar.xml',
                'created_by' => $this->userId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceId = (int) DB::getPdo()->lastInsertId();

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->post('/fiscal/notas/'.$invoiceId.'/rejeitar', [
                    'motivo_rejeicao' => 'XML não corresponde ao pedido conferido.',
                ])
                ->assertRedirect('/fiscal/notas?status=rejeitada');

            $this->assertDatabaseHas('fiscal_invoices', [
                'id' => $invoiceId,
                'status' => 'rejeitada',
                'previous_status' => 'aguardando_aprovacao',
            ]);
            $this->assertNotNull(DB::table('fiscal_invoices')->where('id', $invoiceId)->value('approval_metadata'));
            $this->assertDatabaseHas('logs_auditoria', [
                'acao' => 'rejeitar_nota_fiscal',
                'tabela' => 'fiscal_invoices',
                'registro_id' => $invoiceId,
                'propriedade_id' => $propertyId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_fiscal_invoice_detail_page_shows_items(): void
    {
        DB::beginTransaction();
        $xmlPath = storage_path('app/private/teste-detail.xml');

        try {
            $propertyId = (int) DB::table('propriedades')->orderBy('id')->value('id');
            $this->assertGreaterThan(0, $propertyId);
            session(['propriedade_id' => $propertyId]);
            if (! is_dir(dirname($xmlPath))) {
                mkdir(dirname($xmlPath), 0777, true);
            }
            file_put_contents($xmlPath, '<xml>teste</xml>');

            DB::table('fiscal_invoices')->insert([
                'propriedade_id' => $propertyId,
                'user_id' => $this->userId(),
                'access_key' => 'NFE-LARAVEL-DETAIL-'.uniqid(),
                'invoice_number' => 'NF-LARAVEL-DETAIL',
                'series' => '1',
                'issue_date' => '2026-07-09',
                'issuer_cnpj' => '12345678000195',
                'issuer_name' => 'Fornecedor detalhe Laravel',
                'recipient_cnpj' => '98765432000110',
                'recipient_name' => 'Fazenda Teste',
                'total_value' => 210,
                'status' => 'aguardando_aprovacao',
                'xml_file_path' => 'storage/app/private/teste-detail.xml',
                'created_by' => $this->userId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoiceId = (int) DB::getPdo()->lastInsertId();

            DB::table('fiscal_invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'product_code' => 'DET-001',
                'description' => 'Produto detalhe fiscal Laravel',
                'unit' => 'Unidade',
                'quantity' => 2,
                'unit_value' => 105,
                'total_value' => 210,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/fiscal/notas/'.$invoiceId)
                ->assertStatus(200)
                ->assertSee('NF-LARAVEL-DETAIL')
                ->assertSee('Fornecedor detalhe Laravel')
                ->assertSee('Produto detalhe fiscal Laravel')
                ->assertSee('/fiscal/notas/'.$invoiceId.'/xml')
                ->assertSee('Aprovar nota');

            $this->withSession($this->loggedSession(propertyId: $propertyId))
                ->get('/fiscal/notas/'.$invoiceId.'/xml')
                ->assertStatus(200);
        } finally {
            DB::rollBack();
            if (is_file($xmlPath)) {
                unlink($xmlPath);
            }
        }
    }

    public function test_legacy_ajax_chat_routes_are_served_by_laravel(): void
    {
        $propertyId = app(FarmContext::class)->propertyId();

        $this->withSession($this->loggedSession(propertyId: $propertyId))
            ->get('/pages/ajax/chat_interno.php?action=peers')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->withSession($this->loggedSession(propertyId: $propertyId))
            ->post('/pages/ajax/chat_interno.php?action=heartbeat')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_nginx_safe_ajax_chat_routes_are_served_by_laravel(): void
    {
        $propertyId = app(FarmContext::class)->propertyId();

        $this->withSession($this->loggedSession(propertyId: $propertyId))
            ->get('/ajax/chat-interno?action=peers')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->withSession($this->loggedSession(propertyId: $propertyId))
            ->get('/ajax/suporte-chat?action=client_boot')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_support_widget_uses_nginx_safe_ajax_routes(): void
    {
        $this->withSession($this->loggedSession())
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('data-chat-endpoint="/ajax/chat-interno"', false)
            ->assertSee('data-support-endpoint="/ajax/suporte-chat"', false)
            ->assertDontSee('data-chat-endpoint="/pages/ajax/chat_interno.php"', false)
            ->assertDontSee('data-support-endpoint="/pages/ajax/suporte_chat.php"', false);
    }

    public function test_chat_alerts_do_not_request_system_notifications(): void
    {
        $script = file_get_contents(public_path('js/farmfort.js'));

        $this->assertIsString($script);
        $this->assertStringNotContainsString('Notification.requestPermission', $script);
        $this->assertStringNotContainsString('new Notification', $script);
        $this->assertStringNotContainsString('supportRequestNotificationPermission', $script);
    }

    public function test_legacy_ajax_support_routes_are_served_by_laravel(): void
    {
        $propertyId = app(FarmContext::class)->propertyId();

        $this->withSession($this->loggedSession(propertyId: $propertyId))
            ->get('/pages/ajax/suporte_chat.php?action=client_boot')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->withSession($this->loggedSession(propertyId: $propertyId, profile: 'gerencia_sistema'))
            ->get('/pages/ajax/suporte_chat.php?action=admin_summary')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }
}

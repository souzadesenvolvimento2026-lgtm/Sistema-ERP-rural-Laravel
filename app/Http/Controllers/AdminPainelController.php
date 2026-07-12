<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminPainelController extends Controller
{
    public function __construct(private readonly ProfileAccess $access) {}

    public function index(): View|RedirectResponse
    {
        if (! $this->access->isSystemAdministrator((string) session('perfil'))) {
            return redirect()->route('dashboard')->with('error', 'Acesso restrito ao administrador do sistema.');
        }

        return view('admin.index', [
            'activeModule' => 'admin',
            'title' => 'Painel Administrativo',
            'propertyName' => session('propriedade_nome', 'Fazenda teste'),
            'summaryCards' => $this->summaryCards(),
            'healthCards' => $this->healthCards(),
            'areas' => $this->areas(),
            'updatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }

    private function summaryCards(): array
    {
        $usuariosAtivos = $this->countTable('usuarios', ['ativo' => 1]);
        $usuariosInativos = $this->countTable('usuarios', ['ativo' => 0]);
        $propriedadesAtivas = $this->countTable('propriedades', ['ativo' => 1]);
        $propriedadesInativas = $this->countTable('propriedades', ['ativo' => 0]);
        $grupos = $this->countTable('grupos_fazendas');
        $sessoes = $this->countRecentSessions();
        $chamados = $this->countOpenSupport();

        return [
            ['label' => 'Usuários ativos', 'value' => $usuariosAtivos, 'hint' => $usuariosInativos.' inativo(s)'],
            ['label' => 'Propriedades ativas', 'value' => $propriedadesAtivas, 'hint' => $propriedadesInativas.' inativa(s)'],
            ['label' => 'Grupos', 'value' => $grupos, 'hint' => 'Conjuntos com múltiplas fazendas'],
            ['label' => 'Sessões ativas', 'value' => $sessoes, 'hint' => 'Últimas 24 horas'],
            ['label' => 'Chamados abertos', 'value' => $chamados, 'hint' => 'Suporte em andamento'],
            ['label' => 'Servidor', 'value' => $this->diskPercent().'%', 'hint' => 'Armazenamento usado'],
        ];
    }

    private function healthCards(): array
    {
        return [
            [
                'icon' => 'bi-hdd-stack',
                'label' => 'Armazenamento',
                'value' => $this->diskPercent().'% usado',
                'hint' => $this->diskFreeLabel().' livres de '.$this->diskTotalLabel(),
            ],
            [
                'icon' => 'bi-database',
                'label' => 'Banco de dados',
                'value' => $this->databaseSize(),
                'hint' => DB::connection()->getDriverName(),
            ],
            [
                'icon' => 'bi-folder2-open',
                'label' => 'Uploads',
                'value' => $this->uploadsSize(),
                'hint' => $this->uploadsCount().' arquivo(s)',
            ],
            [
                'icon' => 'bi-memory',
                'label' => 'Memória PHP',
                'value' => ini_get('memory_limit') ?: 'Indisponivel',
                'hint' => 'Limite configurado',
            ],
            [
                'icon' => 'bi-cpu',
                'label' => 'Processador',
                'value' => 'Indisponível',
                'hint' => 'PHP '.PHP_VERSION,
            ],
            [
                'icon' => 'bi-activity',
                'label' => 'Tráfego HTTP',
                'value' => 'Log ausente',
                'hint' => 'Ative o access.log do Apache',
            ],
        ];
    }

    private function areas(): array
    {
        return [
            [
                'group' => 'Clientes / propriedades',
                'title' => 'Planos, fazendas e grupos operacionais',
                'cards' => [
                    ['icon' => 'bi-map', 'title' => 'Propriedades', 'text' => 'Planos, KML, gestores, status e limite de usuários.', 'route' => route('propriedades.index')],
                    ['icon' => 'bi-diagram-3', 'title' => 'Grupos de fazendas', 'text' => 'Conjuntos de propriedades, aprovadores e vínculos.', 'route' => route('propriedades.grupos.index')],
                ],
            ],
            [
                'group' => 'Equipe e acessos',
                'title' => 'Usuários, permissões e sessões',
                'cards' => [
                    ['icon' => 'bi-people', 'title' => 'Usuários', 'text' => 'Criação de acessos, perfis, senha e fazendas vinculadas.', 'route' => route('usuarios.index')],
                    ['icon' => 'bi-clock-history', 'title' => 'Sessões e limites', 'text' => $this->countRecentSessions().' sessão(ões) ativa(s) e controle por plano.', 'route' => route('usuarios.index')],
                ],
            ],
            [
                'group' => 'Segurança',
                'title' => 'Auditoria e rastreabilidade',
                'cards' => [
                    ['icon' => 'bi-shield-check', 'title' => 'Auditoria', 'text' => 'Histórico de ações, alterações críticas e acessos.', 'route' => route('auditoria.index')],
                ],
            ],
            [
                'group' => 'Atendimento',
                'title' => 'Suporte aos usuarios',
                'cards' => [
                    ['icon' => 'bi-chat-dots', 'title' => 'Atendimento de suporte', 'text' => $this->countOpenSupport().' chamado(s) em andamento para acompanhar.', 'route' => route('suporte.admin.index')],
                ],
            ],
        ];
    }

    private function countTable(string $table, array $where = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        foreach ($where as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return (int) $query->count();
    }

    private function countRecentSessions(): int
    {
        if (! Schema::hasTable('usuarios') || ! Schema::hasColumn('usuarios', 'sessao_atualizada_em')) {
            return 0;
        }

        return (int) DB::table('usuarios')
            ->where('ativo', 1)
            ->where('sessao_atualizada_em', '>=', now()->subDay())
            ->count();
    }

    private function countOpenSupport(): int
    {
        if (! Schema::hasTable('suporte_conversas')) {
            return 0;
        }

        $query = DB::table('suporte_conversas');
        if (Schema::hasColumn('suporte_conversas', 'status')) {
            $query->whereNotIn('status', ['fechado', 'encerrado', 'finalizado']);
        }

        return (int) $query->count();
    }

    private function diskPercent(): int
    {
        $total = @disk_total_space(base_path()) ?: 0;
        $free = @disk_free_space(base_path()) ?: 0;
        if ($total <= 0) {
            return 0;
        }

        return (int) round((($total - $free) / $total) * 100);
    }

    private function diskFreeLabel(): string
    {
        return $this->bytesLabel((int) (@disk_free_space(base_path()) ?: 0));
    }

    private function diskTotalLabel(): string
    {
        return $this->bytesLabel((int) (@disk_total_space(base_path()) ?: 0));
    }

    private function databaseSize(): string
    {
        try {
            $database = DB::connection()->getDatabaseName();
            $size = DB::selectOne(
                'SELECT SUM(data_length + index_length) AS total FROM information_schema.tables WHERE table_schema = ?',
                [$database]
            );

            return $this->bytesLabel((int) ($size->total ?? 0));
        } catch (\Throwable $exception) {
            report($exception);

            return 'Indisponível';
        }
    }

    private function uploadsSize(): string
    {
        $path = base_path('../uploads');
        if (! File::isDirectory($path)) {
            return '0 B';
        }

        $total = 0;
        foreach (File::allFiles($path) as $file) {
            $total += $file->getSize();
        }

        return $this->bytesLabel($total);
    }

    private function uploadsCount(): int
    {
        $path = base_path('../uploads');

        return File::isDirectory($path) ? count(File::allFiles($path)) : 0;
    }

    private function bytesLabel(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 1, ',', '.').' '.$units[$unit];
    }
}

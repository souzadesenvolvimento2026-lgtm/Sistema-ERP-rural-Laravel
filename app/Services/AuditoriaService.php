<?php

namespace App\Services;

use App\Support\FarmContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;

class AuditoriaService
{
    private const LIMITE_TELA = 1000;
    private const LIMITE_EXPORTACAO = 5000;
    private const ACOES_USUARIO_GLOBAIS = ['login', 'logout'];

    private const GRUPOS = [
        'despesas' => 'Despesas',
        'receitas' => 'Receitas',
        'planejamento_financeiro' => 'Planejamento financeiro',
        'colheita' => 'Colheita',
        'usuarios' => 'Usuários e acessos',
        'fiscal' => 'Fiscal/NF',
        'talhoes' => 'Talhões e mapa',
        'suporte' => 'Suporte',
    ];

    private const ACOES = [
        'login' => 'Entrou no sistema',
        'logout' => 'Saiu do sistema',
        'exportar_auditoria' => 'Exportou auditoria',
        'envio_formulario' => 'Enviou formulário',
        'liberar_edicao_sistema' => 'Liberou edição operacional',
        'nova_despesa' => 'Lançou despesa',
        'editar_despesa' => 'Editou despesa',
        'pagar_despesa' => 'Baixou/pagou despesa',
        'cancelar_despesa' => 'Cancelou despesa',
        'solicitar_ajuste_despesa' => 'Solicitou ajuste de despesa',
        'solicitar_exclusao_despesa' => 'Solicitou exclusão de despesa',
        'aprovar_despesa' => 'Aprovou despesa',
        'reprovar_despesa' => 'Reprovou despesa',
        'aprovar_exclusao_despesa' => 'Aprovou exclusão de despesa',
        'reprovar_exclusao_despesa' => 'Reprovou exclusão de despesa',
        'nova_receita' => 'Lançou receita',
        'editar_receita' => 'Editou receita',
        'receber_receita' => 'Confirmou recebimento',
        'cancelar_receita' => 'Cancelou receita',
        'salvar_colheita' => 'Lançou colheita',
        'salvar_usuario' => 'Criou/editou usuário',
        'alterar_senha_usuario' => 'Alterou senha de usuário',
        'ativar_usuario' => 'Ativou usuário',
        'desativar_usuario' => 'Desativou usuário',
        'salvar_propriedade' => 'Criou/editou propriedade',
        'editar_propriedade' => 'Editou propriedade',
        'desativar_propriedade' => 'Desativou propriedade',
        'reativar_propriedade' => 'Reativou propriedade',
        'salvar_safra' => 'Criou/editou safra',
        'importar_xml_nf' => 'Importou XML de NF',
        'criar_entrada_nf' => 'Criou entrada de NF',
        'aprovar_pedido_fiscal' => 'Aprovou pedido fiscal',
        'rejeitar_pedido_fiscal' => 'Rejeitou pedido fiscal',
        'unificar_talhoes' => 'Unificou talhões',
        'criar_area_excluida' => 'Criou área excluída',
        'limpar_areas_excluidas' => 'Limpou áreas excluídas',
        'criar_transferencia' => 'Criou transferência',
        'editar_transferencia' => 'Editou transferência',
    ];

    private const TABELAS = [
        'logs_auditoria' => 'Auditoria',
        'usuarios' => 'Usuários',
        'usuario_propriedades' => 'Usuários e propriedades',
        'despesas' => 'Despesas',
        'receitas' => 'Receitas',
        'colheita_talhoes' => 'Colheita',
        'safras' => 'Safras',
        'financeiro_projecoes' => 'Planejamento financeiro',
        'propriedades' => 'Propriedades',
        'talhoes' => 'Talhões',
        'maquinas' => 'Patrimônio',
        'patrimonios' => 'Patrimônio',
        'nf_entradas' => 'Entrada de NF',
        'notas_fiscais' => 'Notas fiscais',
        'fiscal_orders' => 'Pedidos fiscais',
        'contas' => 'Contas bancárias',
        'transferencias' => 'Transferências',
        'suporte_conversas' => 'Suporte',
    ];

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function dados(Request $request): array
    {
        $property = app(FarmContext::class)->property();
        abort_if(! $property, 403, 'Nenhuma propriedade ativa autorizada na sessão.');

        $propertyId = (int) $property->id;
        $propertyName = (string) $property->nome;
        $filters = $this->normalizarFiltros($request);
        $query = $this->filtrar($this->baseQuery($propertyId), $filters);
        $logs = (clone $query)
            ->orderByDesc('l.criado_em')
            ->orderByDesc('l.id')
            ->limit(self::LIMITE_TELA)
            ->get()
            ->map(fn (stdClass $log): stdClass => $this->formatLog($log, $propertyName));

        return [
            'activeModule' => 'usuarios',
            'topbarLabel' => 'Auditoria',
            'property' => $property,
            'cards' => $this->cards($query),
            'logs' => $logs,
            'usuarios' => $this->usuariosFiltro($propertyId),
            'acoes' => $this->valoresFiltro($propertyId, 'acao'),
            'tabelas' => $this->valoresFiltro($propertyId, 'tabela'),
            'lancamentos' => self::GRUPOS,
            'tiposDespesa' => $this->tiposDespesa($propertyId),
            'filtros' => $filters,
            'limiteTela' => self::LIMITE_TELA,
            'periodoInvertido' => (bool) $filters['periodo_invertido'],
        ];
    }

    public function detalhar(int $logId): stdClass
    {
        $property = app(FarmContext::class)->property();
        abort_if(! $property, 403, 'Nenhuma propriedade ativa autorizada na sessão.');

        $log = $this->baseQuery((int) $property->id)
            ->where('l.id', $logId)
            ->first();

        abort_if(! $log, 404);

        return $this->formatLog($log, (string) $property->nome);
    }

    public function exportar(Request $request): array
    {
        $property = app(FarmContext::class)->property();
        abort_if(! $property, 403, 'Nenhuma propriedade ativa autorizada na sessão.');

        $propertyId = (int) $property->id;
        $propertyName = (string) $property->nome;

        $this->audit->registrar(
            usuarioId: null,
            acao: 'exportar_auditoria',
            tabela: 'logs_auditoria',
            propriedadeId: $propertyId,
            detalhes: 'Exportação dos registros de auditoria.',
            request: $request,
        );

        $filters = $this->normalizarFiltros($request);
        $rows = $this->filtrar($this->baseQuery($propertyId), $filters)
            ->orderByDesc('l.criado_em')
            ->orderByDesc('l.id')
            ->limit(self::LIMITE_EXPORTACAO)
            ->get()
            ->map(fn (stdClass $log): stdClass => $this->formatLog($log, $propertyName));

        return [
            'filename' => 'farmfort_auditoria_'.now()->format('Ymd_His').'.csv',
            'headers' => [
                'Data/Hora',
                'Usuário',
                'Propriedade',
                'Lançamento',
                'Ação',
                'Origem',
                'Registro',
                'Tipo de despesa',
                'Detalhes',
                'IP real',
                'IP proxy',
                'CF-Ray',
            ],
            'rows' => $rows,
        ];
    }

    private function normalizarFiltros(Request $request): array
    {
        $start = $this->dataValida($request->query('inicio'));
        $end = $this->dataValida($request->query('fim'));
        $inverted = false;

        if ($start !== '' && $end !== '' && $start > $end) {
            [$start, $end] = [$end, $start];
            $inverted = true;
        }

        return [
            'inicio' => $start,
            'fim' => $end,
            'usuario_id' => (int) $request->query('usuario_id', 0) ?: null,
            'acao' => trim((string) $request->query('acao', '')),
            'tabela' => trim((string) $request->query('tabela', '')),
            'lancamento' => array_key_exists((string) $request->query('lancamento', ''), self::GRUPOS)
                ? (string) $request->query('lancamento', '')
                : '',
            'tipo_despesa' => trim((string) $request->query('tipo_despesa', '')),
            'busca' => trim((string) $request->query('busca', '')),
            'periodo_invertido' => $inverted,
        ];
    }

    private function dataValida(mixed $date): string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private function filtrar(Builder $query, array $filters): Builder
    {
        if ($filters['usuario_id']) {
            $query->where('l.usuario_id', $filters['usuario_id']);
        }

        if ($filters['acao'] !== '') {
            $query->where('l.acao', $filters['acao']);
        }

        if ($filters['tabela'] !== '') {
            $query->where('l.tabela', $filters['tabela']);
        }

        if ($filters['lancamento'] !== '') {
            $this->filtrarLancamento($query, $filters['lancamento']);
        }

        if ($filters['tipo_despesa'] !== '') {
            $query->where('c.tipo', $filters['tipo_despesa']);
        }

        if ($filters['inicio'] !== '') {
            $query->where('l.criado_em', '>=', $filters['inicio'].' 00:00:00');
        }

        if ($filters['fim'] !== '') {
            $query->where('l.criado_em', '<=', $filters['fim'].' 23:59:59');
        }

        if ($filters['busca'] !== '') {
            $search = '%'.$filters['busca'].'%';
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('u.nome', 'like', $search)
                    ->orWhere('u.email', 'like', $search)
                    ->orWhere('p.nome', 'like', $search)
                    ->orWhere('l.acao', 'like', $search)
                    ->orWhere('l.tabela', 'like', $search)
                    ->orWhere('l.detalhes', 'like', $search);
            });
        }

        return $query;
    }

    private function filtrarLancamento(Builder $query, string $group): void
    {
        $query->where(function (Builder $query) use ($group): void {
            match ($group) {
                'despesas' => $query->where('l.tabela', 'despesas')->orWhere('l.acao', 'like', '%despesa%'),
                'receitas' => $query->where('l.tabela', 'receitas')->orWhere('l.acao', 'like', '%receita%'),
                'planejamento_financeiro' => $query
                    ->where('l.tabela', 'financeiro_projecoes')
                    ->orWhere('l.acao', 'like', '%planejamento%')
                    ->orWhere('l.acao', 'like', '%orcamento%'),
                'colheita' => $query->where('l.tabela', 'colheita_talhoes')->orWhere('l.acao', 'like', '%colheita%'),
                'usuarios' => $query
                    ->where('l.tabela', 'usuarios')
                    ->orWhere('l.acao', 'like', '%usuario%')
                    ->orWhereIn('l.acao', self::ACOES_USUARIO_GLOBAIS),
                'fiscal' => $query
                    ->whereIn('l.tabela', ['nf_entradas', 'notas_fiscais', 'certificados_digitais', 'fiscal_orders'])
                    ->orWhere('l.acao', 'like', '%nf%')
                    ->orWhere('l.acao', 'like', '%fiscal%')
                    ->orWhere('l.acao', 'like', '%certificado%')
                    ->orWhere('l.acao', 'like', '%pedido_fiscal%'),
                'talhoes' => $query
                    ->where('l.tabela', 'talhoes')
                    ->orWhere('l.acao', 'like', '%talhao%')
                    ->orWhere('l.acao', 'like', '%talhoes%')
                    ->orWhere('l.acao', 'like', '%pivo%')
                    ->orWhere('l.acao', 'like', '%poligono%'),
                'suporte' => $query
                    ->where('l.tabela', 'suporte_conversas')
                    ->orWhere('l.acao', 'like', '%suporte%')
                    ->orWhere('l.acao', 'like', '%chat%'),
                default => null,
            };
        });
    }

    private function cards(Builder $query): array
    {
        $base = clone $query;

        return [
            [
                'label' => 'Ações auditadas',
                'value' => number_format((clone $base)->count(), 0, ',', '.'),
            ],
            [
                'label' => 'Usuários envolvidos',
                'value' => number_format((clone $base)->whereNotNull('l.usuario_id')->distinct()->count('l.usuario_id'), 0, ',', '.'),
            ],
            [
                'label' => 'Despesas lançadas',
                'value' => number_format((clone $base)->where('l.acao', 'nova_despesa')->count(), 0, ',', '.'),
                'tone' => 'danger',
            ],
            [
                'label' => 'Colheitas lançadas',
                'value' => number_format((clone $base)->where('l.acao', 'salvar_colheita')->count(), 0, ',', '.'),
                'tone' => 'success',
            ],
        ];
    }

    private function usuariosFiltro(int $propertyId)
    {
        return DB::table('usuarios as u')
            ->select('u.id', 'u.nome', 'u.email')
            ->where(function (Builder $query) use ($propertyId): void {
                $query
                    ->whereExists(function (Builder $subQuery) use ($propertyId): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from('usuario_propriedades as up')
                            ->whereColumn('up.usuario_id', 'u.id')
                            ->where('up.propriedade_id', $propertyId);
                    })
                    ->orWhereExists(function (Builder $subQuery) use ($propertyId): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from('logs_auditoria as l')
                            ->whereColumn('l.usuario_id', 'u.id')
                            ->where('l.propriedade_id', $propertyId);
                    });
            })
            ->orderBy('u.nome')
            ->get();
    }

    private function valoresFiltro(int $propertyId, string $column)
    {
        return $this->baseQuery($propertyId)
            ->whereNotNull('l.'.$column)
            ->where('l.'.$column, '<>', '')
            ->distinct()
            ->orderBy('l.'.$column)
            ->pluck('l.'.$column)
            ->filter()
            ->values();
    }

    private function tiposDespesa(int $propertyId)
    {
        return DB::table('logs_auditoria as l')
            ->leftJoin('despesas as d', function ($join): void {
                $join
                    ->on('d.id', '=', 'l.registro_id')
                    ->where('l.tabela', '=', 'despesas');
            })
            ->leftJoin('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->where('l.propriedade_id', $propertyId)
            ->whereNotNull('c.tipo')
            ->where('c.tipo', '<>', '')
            ->distinct()
            ->orderBy('c.tipo')
            ->pluck('c.tipo')
            ->filter()
            ->values();
    }

    private function baseQuery(int $propertyId): Builder
    {
        return DB::table('logs_auditoria as l')
            ->leftJoin('usuarios as u', 'u.id', '=', 'l.usuario_id')
            ->leftJoin('propriedades as p', 'p.id', '=', 'l.propriedade_id')
            ->leftJoin('despesas as d', function ($join): void {
                $join
                    ->on('d.id', '=', 'l.registro_id')
                    ->where('l.tabela', '=', 'despesas');
            })
            ->leftJoin('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->where(function (Builder $query) use ($propertyId): void {
                $query
                    ->where('l.propriedade_id', $propertyId)
                    ->orWhere(function (Builder $subQuery) use ($propertyId): void {
                        $subQuery
                            ->whereNull('l.propriedade_id')
                            ->whereIn('l.acao', self::ACOES_USUARIO_GLOBAIS)
                            ->whereExists(function (Builder $exists) use ($propertyId): void {
                                $exists
                                    ->selectRaw('1')
                                    ->from('usuario_propriedades as up')
                                    ->whereColumn('up.usuario_id', 'l.usuario_id')
                                    ->where('up.propriedade_id', $propertyId);
                            });
                    });
            })
            ->select([
                'l.id',
                'l.usuario_id',
                'l.acao',
                'l.tabela',
                'l.registro_id',
                'l.propriedade_id',
                'l.detalhes',
                'l.ip',
                'l.criado_em',
                'u.nome as usuario_nome',
                'u.email as usuario_email',
                'u.perfil as usuario_perfil',
                'p.nome as propriedade_nome',
                'c.tipo as categoria_tipo',
                'c.nome as categoria_nome',
                $this->selectOptionalAuditColumn('ip_cliente'),
                $this->selectOptionalAuditColumn('ip_proxy'),
                $this->selectOptionalAuditColumn('cf_ray'),
                $this->selectOptionalAuditColumn('host'),
                $this->selectOptionalAuditColumn('rota'),
                $this->selectOptionalAuditColumn('metodo'),
                $this->selectOptionalAuditColumn('user_agent'),
            ]);
    }

    private function selectOptionalAuditColumn(string $column): mixed
    {
        if (Schema::hasColumn('logs_auditoria', $column)) {
            return 'l.'.$column;
        }

        return DB::raw('NULL as '.$column);
    }

    private function formatLog(stdClass $log, string $activePropertyName): stdClass
    {
        $action = (string) ($log->acao ?: 'registro_antigo');
        $table = (string) ($log->tabela ?: '');
        $actionLabel = $this->acaoLegivel($action);
        $tableLabel = $this->tabelaLegivel($table);
        $propertyName = $log->propriedade_nome
            ?: ($log->propriedade_id ? 'Propriedade #'.$log->propriedade_id : $activePropertyName);
        $details = $this->detalhesLegiveis($log, $actionLabel);
        $clientIp = trim((string) ($log->ip_cliente ?? '')) ?: trim((string) ($log->ip ?? ''));
        $proxyIp = trim((string) ($log->ip_proxy ?? ''));
        $recordId = $log->registro_id ? '#'.$log->registro_id : '-';

        return (object) [
            'id' => (int) $log->id,
            'criado_em' => (string) $log->criado_em,
            'criado_em_legivel' => $this->formatarDataHora($log->criado_em),
            'usuario' => $log->usuario_nome ?: 'Usuário removido',
            'usuario_nome' => $log->usuario_nome ?: 'Usuário removido',
            'usuario_email' => $log->usuario_email ?: '-',
            'usuario_perfil' => $log->usuario_perfil ? Str::headline((string) $log->usuario_perfil) : '-',
            'propriedade' => $propertyName ?: 'Sem propriedade vinculada',
            'lancamento' => $this->grupoLancamento($table, $action),
            'acao_legivel' => $actionLabel,
            'acao_tecnica' => $action,
            'tabela_legivel' => $tableLabel,
            'tabela_tecnica' => $table ?: '-',
            'onde' => $tableLabel,
            'tipo_despesa' => $log->categoria_tipo ?: '-',
            'categoria_despesa' => $log->categoria_nome ?: '-',
            'registro' => $recordId,
            'detalhes' => $details,
            'detalhes_resumo' => $this->resumir($details),
            'ip' => $clientIp !== '' ? $clientIp : '-',
            'ip_cliente' => $clientIp !== '' ? $clientIp : '-',
            'ip_proxy' => $proxyIp !== '' ? $proxyIp : '-',
            'cf_ray' => $log->cf_ray ?: '-',
            'host' => $log->host ?: '-',
            'rota' => $log->rota ?: '-',
            'metodo' => $log->metodo ?: '-',
            'user_agent' => $log->user_agent ?: '-',
            'critico' => $this->acaoCritica($action, $actionLabel),
            'tom' => $this->tomAcao($action, $table),
        ];
    }

    private function detalhesLegiveis(stdClass $log, string $actionLabel): string
    {
        $details = trim((string) ($log->detalhes ?? ''));
        if ($details !== '') {
            return $this->mascararDetalhes($details);
        }

        if (empty($log->acao)) {
            return 'Registro antigo ou incompleto de auditoria.';
        }

        return $actionLabel.' registrada pelo FarmFort.';
    }

    private function grupoLancamento(string $table, string $action): string
    {
        foreach (array_keys(self::GRUPOS) as $group) {
            if ($this->pertenceAoGrupo($group, $table, $action)) {
                return self::GRUPOS[$group];
            }
        }

        return $this->tabelaLegivel($table);
    }

    private function pertenceAoGrupo(string $group, string $table, string $action): bool
    {
        return match ($group) {
            'despesas' => $table === 'despesas' || str_contains($action, 'despesa'),
            'receitas' => $table === 'receitas' || str_contains($action, 'receita'),
            'usuarios' => $table === 'usuarios' || str_contains($action, 'usuario') || in_array($action, self::ACOES_USUARIO_GLOBAIS, true),
            'planejamento_financeiro' => $table === 'financeiro_projecoes' || str_contains($action, 'planejamento') || str_contains($action, 'orcamento'),
            'colheita' => $table === 'colheita_talhoes' || str_contains($action, 'colheita'),
            'fiscal' => in_array($table, ['nf_entradas', 'notas_fiscais', 'certificados_digitais', 'fiscal_orders'], true)
                || str_contains($action, 'nf')
                || str_contains($action, 'fiscal')
                || str_contains($action, 'certificado')
                || str_contains($action, 'pedido_fiscal'),
            'talhoes' => $table === 'talhoes'
                || str_contains($action, 'talhao')
                || str_contains($action, 'talhoes')
                || str_contains($action, 'pivo')
                || str_contains($action, 'poligono'),
            'suporte' => $table === 'suporte_conversas' || str_contains($action, 'suporte') || str_contains($action, 'chat'),
            default => false,
        };
    }

    private function acaoLegivel(string $action): string
    {
        return self::ACOES[$action] ?? Str::headline($action ?: 'registro');
    }

    private function tabelaLegivel(?string $table): string
    {
        return self::TABELAS[$table] ?? Str::headline((string) ($table ?: 'não informado'));
    }

    private function acaoCritica(string $action, string $actionLabel): bool
    {
        $text = Str::lower($action.' '.$actionLabel);

        return str_contains($text, 'exclu')
            || str_contains($text, 'cancel')
            || str_contains($text, 'reprov')
            || str_contains($text, 'rejeit')
            || str_contains($text, 'desativ');
    }

    private function tomAcao(string $action, string $table): string
    {
        $text = Str::lower($action.' '.$table);

        if (str_contains($text, 'suporte') || str_contains($text, 'chat')) {
            return 'support';
        }

        if ($this->acaoCritica($action, $this->acaoLegivel($action))) {
            return 'danger';
        }

        if (str_contains($text, 'aprovar') || str_contains($text, 'pagar') || str_contains($text, 'receber') || str_contains($text, 'salvar')) {
            return 'success';
        }

        if (str_contains($text, 'importar') || str_contains($text, 'exportar') || str_contains($text, 'nf') || str_contains($text, 'fiscal')) {
            return 'info';
        }

        return in_array($action, self::ACOES_USUARIO_GLOBAIS, true) ? 'neutral' : 'info';
    }

    private function formatarDataHora(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '-';
        }

        try {
            return Carbon::parse($date)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    private function resumir(string $details): string
    {
        $details = trim(preg_replace('/\s+/', ' ', $details) ?? $details);

        return Str::limit($details, 180);
    }

    private function mascararDetalhes(string $value): string
    {
        $value = mb_substr($value, 0, 5000);
        $patterns = [
            '/("?(?:senha|password|token|cookie|authorization|secret|api_key|chave|certificado)"?\s*[:=]\s*)"[^"]*"/iu',
            '/((?:senha|password|token|cookie|authorization|secret|api_key|chave|certificado)\s*[:=]\s*)[^;\n\r,}]+/iu',
        ];

        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '$1"[removido]"', $value) ?? $value;
        }

        return $value;
    }

    public function valorExportacao(mixed $value): string
    {
        $text = (string) $value;
        $trimmed = ltrim($text);

        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'".$text;
        }

        return $text;
    }
}

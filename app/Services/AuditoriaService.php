<?php

namespace App\Services;

use App\Support\FarmContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditoriaService
{
    public function dados(Request $request): array
    {
        $propertyId = app(FarmContext::class)->propertyId();
        $logs = $this->filtrar($this->baseQuery($propertyId), $request)
            ->orderByDesc('l.criado_em')
            ->limit(120)
            ->get()
            ->map(fn ($log) => $this->formatLog($log));

        return [
            'activeModule' => 'auditoria',
            'cards' => $this->cards($logs),
            'logs' => $logs,
            'columns' => [
                'criado_em' => 'Data',
                'usuario' => 'Usuário',
                'acao_legivel' => 'Ação',
                'tabela_legivel' => 'Área',
                'registro' => 'Registro',
                'detalhes' => 'Detalhes',
                'ip' => 'IP',
            ],
            'usuarios' => $this->usuariosFiltro($propertyId),
            'acoes' => $this->valoresFiltro($propertyId, 'acao'),
            'tabelas' => $this->valoresFiltro($propertyId, 'tabela'),
            'filtros' => [
                'usuario_id' => (int)$request->query('usuario_id'),
                'acao' => trim((string)$request->query('acao')),
                'tabela' => trim((string)$request->query('tabela')),
                'lancamento' => trim((string)$request->query('lancamento')),
                'tipo_despesa' => trim((string)$request->query('tipo_despesa')),
                'inicio' => trim((string)$request->query('inicio')),
                'fim' => trim((string)$request->query('fim')),
                'busca' => trim((string)$request->query('busca')),
            ],
            'lancamentos' => [
                'despesas' => 'Despesas',
                'receitas' => 'Receitas',
                'planejamento' => 'Planejamento',
                'colheita' => 'Colheita',
                'usuarios' => 'Usuários',
                'fiscal' => 'Fiscal',
                'talhoes' => 'Talhões',
                'suporte' => 'Suporte',
            ],
            'tiposDespesa' => DB::table('categorias')
                ->whereNotNull('tipo')
                ->where('tipo', '!=', '')
                ->distinct()
                ->orderBy('tipo')
                ->pluck('tipo'),
        ];
    }

    public function exportar(Request $request): array
    {
        $propertyId = app(FarmContext::class)->propertyId();
        $logs = $this->filtrar($this->baseQuery($propertyId), $request)
            ->orderByDesc('l.criado_em')
            ->limit(5000)
            ->get()
            ->map(fn ($log) => $this->formatLog($log));

        return [
            'filename' => 'auditoria-'.now()->format('Ymd-His').'.csv',
            'headers' => ['Data', 'Usuario', 'Acao', 'Area', 'Registro', 'Detalhes', 'IP'],
            'rows' => $logs,
        ];
    }

    private function filtrar($query, Request $request)
    {
        if ($usuarioId = (int)$request->query('usuario_id')) {
            $query->where('l.usuario_id', $usuarioId);
        }

        if ($acao = trim((string)$request->query('acao'))) {
            $query->where('l.acao', $acao);
        }

        if ($tabela = trim((string)$request->query('tabela'))) {
            $query->where('l.tabela', $tabela);
        }

        if ($lancamento = trim((string)$request->query('lancamento'))) {
            $this->filtrarLancamento($query, $lancamento);
        }

        if ($tipoDespesa = trim((string)$request->query('tipo_despesa'))) {
            $query->where('l.tabela', 'despesas')->where('cd.tipo', $tipoDespesa);
        }

        if ($inicio = trim((string)$request->query('inicio'))) {
            $query->where('l.criado_em', '>=', $inicio.' 00:00:00');
        }

        if ($fim = trim((string)$request->query('fim'))) {
            $query->where('l.criado_em', '<=', $fim.' 23:59:59');
        }

        if ($busca = trim((string)$request->query('busca'))) {
            $like = '%' . $busca . '%';
            $query->where(function ($q) use ($like) {
                $q->where('l.acao', 'like', $like)
                    ->orWhere('l.tabela', 'like', $like)
                    ->orWhere('l.detalhes', 'like', $like)
                    ->orWhere('u.nome', 'like', $like)
                    ->orWhere('u.email', 'like', $like)
                    ->orWhere('p.nome', 'like', $like);
            });
        }

        return $query;
    }

    private function filtrarLancamento($query, string $lancamento): void
    {
        $grupos = [
            'despesas' => ['despesas', ['nova_despesa', 'solicitar_ajuste_despesa', 'solicitar_exclusao_despesa', 'aprovar_despesa', 'reprovar_despesa', 'aprovar_exclusao_despesa', 'reprovar_exclusao_despesa', 'pagar_despesa', 'cancelar_despesa', 'agenda_pagar_despesa']],
            'receitas' => ['receitas', ['nova_receita', 'editar_receita', 'solicitar_ajuste_receita', 'solicitar_exclusao_receita', 'aprovar_receita', 'reprovar_receita', 'aprovar_exclusao_receita', 'reprovar_exclusao_receita', 'receber_receita', 'cancelar_receita', 'agenda_receber_receita']],
            'usuarios' => ['usuarios', ['login', 'logout', 'salvar_usuario', 'alterar_senha_usuario', 'ativar_usuario', 'desativar_usuario']],
        ];

        if (isset($grupos[$lancamento])) {
            [$tabela, $acoes] = $grupos[$lancamento];
            $query->where(function ($q) use ($tabela, $acoes) {
                $q->where('l.tabela', $tabela)->orWhereIn('l.acao', $acoes);
            });
            return;
        }

        match ($lancamento) {
            'planejamento' => $query->where(fn ($q) => $q->where('l.tabela', 'financeiro_projecoes')->orWhere('l.acao', 'like', '%planejamento%')),
            'colheita' => $query->where(fn ($q) => $q->where('l.tabela', 'colheita_talhoes')->orWhere('l.acao', 'like', '%colheita%')),
            'fiscal' => $query->where(fn ($q) => $q->whereIn('l.tabela', ['nf_entradas', 'notas_fiscais', 'certificados_digitais'])->orWhere('l.acao', 'like', '%nf%')->orWhere('l.acao', 'like', '%certificado%')),
            'talhoes' => $query->where(fn ($q) => $q->where('l.tabela', 'talhoes')->orWhere('l.acao', 'like', '%talhao%')->orWhere('l.acao', 'like', '%pivo%')->orWhere('l.acao', 'like', '%poligono%')),
            'suporte' => $query->where(fn ($q) => $q->where('l.tabela', 'suporte_conversas')->orWhere('l.acao', 'like', 'suporte_%')),
            default => null,
        };
    }

    private function baseQuery(int $propertyId)
    {
        return DB::table('logs_auditoria as l')
            ->leftJoin('usuarios as u', 'u.id', '=', 'l.usuario_id')
            ->leftJoin('propriedades as p', 'p.id', '=', 'l.propriedade_id')
            ->leftJoin('despesas as dlog', function ($join) {
                $join->on('dlog.id', '=', 'l.registro_id')->where('l.tabela', 'despesas');
            })
            ->leftJoin('categorias as cd', 'cd.id', '=', 'dlog.categoria_id')
            ->where(function ($query) use ($propertyId) {
                $query->where('l.propriedade_id', $propertyId)
                    ->orWhere(function ($q) use ($propertyId) {
                        $q->where('l.tabela', 'propriedades')->where('l.registro_id', $propertyId);
                    })
                    ->orWhere(function ($q) use ($propertyId) {
                        $q->whereNull('l.propriedade_id')
                            ->whereIn('l.acao', ['login', 'logout'])
                            ->whereExists(function ($sub) use ($propertyId) {
                                $sub->selectRaw('1')
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
                'l.detalhes',
                'l.ip',
                'l.criado_em',
                'u.nome as usuario_nome',
                'u.email as usuario_email',
                'p.nome as propriedade_nome',
            ]);
    }

    private function formatLog($log): object
    {
        return (object)[
            'id' => $log->id,
            'criado_em' => $log->criado_em,
            'usuario' => trim(($log->usuario_nome ?: 'Usuário removido') . ($log->usuario_email ? ' - ' . $log->usuario_email : '')),
            'acao_legivel' => $this->acaoLegivel((string)$log->acao),
            'tabela_legivel' => $this->tabelaLegivel($log->tabela),
            'registro' => $log->registro_id ? '#' . $log->registro_id : '-',
            'detalhes' => $log->detalhes ?: $this->acaoLegivel((string)$log->acao) . ' em ' . $this->tabelaLegivel($log->tabela),
            'ip' => $log->ip ?: '-',
        ];
    }

    private function cards($logs): array
    {
        return [
            ['label' => 'Registros', 'value' => (string)$logs->count(), 'tone' => 'success'],
            ['label' => 'Usuários', 'value' => (string)$logs->pluck('usuario')->filter()->unique()->count(), 'tone' => 'success'],
            ['label' => 'Ações críticas', 'value' => (string)$logs->filter(fn ($log) => $this->acaoCritica($log->acao_legivel))->count(), 'tone' => 'danger'],
            ['label' => 'Último registro', 'value' => $logs->first()->criado_em ?? '-', 'tone' => 'success'],
        ];
    }

    private function usuariosFiltro(int $propertyId)
    {
        return DB::table('logs_auditoria as l')
            ->join('usuarios as u', 'u.id', '=', 'l.usuario_id')
            ->where('l.propriedade_id', $propertyId)
            ->select('u.id', 'u.nome', 'u.email')
            ->distinct()
            ->orderBy('u.nome')
            ->get();
    }

    private function valoresFiltro(int $propertyId, string $campo)
    {
        return DB::table('logs_auditoria')
            ->where('propriedade_id', $propertyId)
            ->whereNotNull($campo)
            ->where($campo, '!=', '')
            ->distinct()
            ->orderBy($campo)
            ->pluck($campo);
    }

    private function acaoLegivel(string $acao): string
    {
        $mapa = [
            'login' => 'Entrou no sistema',
            'logout' => 'Saiu do sistema',
            'envio_formulario' => 'Enviou formulário',
            'liberar_edicao_sistema' => 'Liberou edição operacional',
            'nova_despesa' => 'Lançou despesa',
            'pagar_despesa' => 'Baixou/pagou despesa',
            'nova_receita' => 'Lançou receita',
            'receber_receita' => 'Confirmou recebimento',
            'salvar_colheita' => 'Lançou colheita',
            'salvar_usuario' => 'Criou/editou usuário',
            'salvar_propriedade' => 'Criou/editou propriedade',
            'salvar_safra' => 'Criou/editou safra',
            'importar_xml_nf' => 'Importou XML de NF',
            'criar_entrada_nf' => 'Criou entrada de NF',
            'aprovar_pedido_fiscal' => 'Aprovou pedido fiscal',
        ];

        return $mapa[$acao] ?? Str::headline($acao ?: 'registro');
    }

    private function tabelaLegivel(?string $tabela): string
    {
        $mapa = [
            'usuarios' => 'Usuários',
            'despesas' => 'Despesas',
            'receitas' => 'Receitas',
            'colheita_talhoes' => 'Colheita',
            'safras' => 'Safras',
            'financeiro_projecoes' => 'Planejamento financeiro',
            'propriedades' => 'Propriedades',
            'talhoes' => 'Talhões',
            'maquinas' => 'Patrimônio',
            'nf_entradas' => 'Entrada de NF',
            'notas_fiscais' => 'Notas fiscais',
            'fiscal_orders' => 'Pedidos fiscais',
        ];

        return $mapa[$tabela] ?? Str::headline((string)($tabela ?: 'não informado'));
    }

    private function acaoCritica(string $acao): bool
    {
        $acao = Str::lower($acao);

        return str_contains($acao, 'exclu')
            || str_contains($acao, 'cancel')
            || str_contains($acao, 'reprov')
            || str_contains($acao, 'desativ');
    }
}

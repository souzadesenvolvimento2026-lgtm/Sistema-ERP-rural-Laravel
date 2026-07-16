@extends('layouts.farmfort', [
    'title' => 'FarmFort - '.$title,
    'topbarLabel' => 'Painel',
])

@php
    use App\Support\FarmFormat;

    $fmtMoney = fn ($value) => FarmFormat::money($value);
    $tipoAtual = $filtros['tipo'] ?? 'todos';
    $contaAtual = (int) ($filtros['conta_id'] ?? 0);
    $lancamentoDestacado = (int) ($filtros['lancamento_id'] ?? 0);
    $urlFiltro = function (array $params = [], array $forget = []) {
        $query = request()->query();
        unset($query['lancamento_id']);

        foreach ($forget as $key) {
            unset($query[$key]);
        }

        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        return route('financeiro.index', $query);
    };
@endphp

@section('content')
    <div class="ff-finance-legacy-page">
        <span class="visually-hidden">Painel Financeiro</span>
        <span class="visually-hidden">Agenda financeira</span>
        <span class="visually-hidden">Saldos por conta</span>

        <div class="page-head ff-finance-page-head">
            <div>
                <h1>{{ $title }}</h1>
                <p class="subtitle">{{ $subtitle }}: {{ $periodoLabel }}</p>
            </div>
            <div class="actions">
                <a class="btn" href="{{ route('financeiro.relatorio-lancamentos.index', request()->query()) }}">
                    <i class="bi bi-download"></i> Gerar relatório
                </a>
                <button class="btn primary" type="button" data-bs-toggle="modal" data-bs-target="#financeiroNovoLancamentoModal">
                    <i class="bi bi-plus-lg"></i> Novo Lançamento
                </button>
            </div>
        </div>

        @include('partials.filter-panel', [
            'action' => route('financeiro.index'),
            'clearUrl' => route('financeiro.index'),
            'hidden' => [
                'conta_id' => $contaAtual ?: null,
            ],
            'fields' => [
                [
                    'type' => 'select',
                    'name' => 'filtro',
                    'label' => 'Tipo de lançamento',
                    'value' => $tipoAtual,
                    'options' => [
                        'todos' => 'Todos',
                        'despesas' => 'Despesas',
                        'receitas' => 'Receitas',
                        'transferencias' => 'Transferências',
                    ],
                    'columns' => 3,
                ],
                [
                    'type' => 'month',
                    'name' => 'mes',
                    'label' => 'Mês',
                    'value' => $filtros['mes'] ?? '',
                    'columns' => 2,
                ],
                [
                    'type' => 'date',
                    'name' => 'data_inicio',
                    'label' => 'Data inicial',
                    'value' => $filtros['data_inicio'] ?? '',
                    'columns' => 2,
                ],
                [
                    'type' => 'date',
                    'name' => 'data_fim',
                    'label' => 'Data final',
                    'value' => $filtros['data_fim'] ?? '',
                    'columns' => 2,
                ],
                [
                    'type' => 'search',
                    'name' => 'search',
                    'label' => 'Buscar',
                    'value' => $filtros['search'] ?? '',
                    'placeholder' => 'Descrição, pessoa, safra, categoria ou conta',
                    'columns' => 5,
                    'attributes' => [
                        'data-ff-ledger-search' => true,
                    ],
                ],
            ],
            'actionsColumns' => 2,
        ])

        <section class="stats ff-finance-summary-cards">
            @foreach ($cards as $card)
                <article class="stat {{ $card['tone'] ?? '' }}">
                    <span>{{ $card['label'] }}</span>
                    <strong>{{ $card['value'] }}</strong>
                </article>
            @endforeach
        </section>

        <div class="ff-finance-quickbar">
            <span class="ff-finance-filter-label">Filtrar:</span>

            <div class="dropdown">
                <button class="btn btn-sm {{ in_array($tipoAtual, ['todos', 'despesas', 'receitas', 'transferencias'], true) ? 'btn-farmflow' : 'btn-outline-secondary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul me-1"></i>{{ match($tipoAtual) { 'despesas' => 'Despesas', 'receitas' => 'Receitas', 'transferencias' => 'Transferências', default => 'Lançamentos' } }}
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item @if($tipoAtual === 'todos') active @endif" href="{{ $urlFiltro(['filtro' => 'todos']) }}"><i class="bi bi-list-ul me-2"></i>Todos os lançamentos</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item @if($tipoAtual === 'despesas') active @endif" href="{{ $urlFiltro(['filtro' => 'despesas']) }}"><i class="bi bi-arrow-down-circle me-2"></i>Despesas</a></li>
                    <li><a class="dropdown-item @if($tipoAtual === 'receitas') active @endif" href="{{ $urlFiltro(['filtro' => 'receitas']) }}"><i class="bi bi-arrow-up-circle me-2"></i>Receitas</a></li>
                    <li><a class="dropdown-item @if($tipoAtual === 'transferencias') active @endif" href="{{ $urlFiltro(['filtro' => 'transferencias']) }}"><i class="bi bi-arrow-left-right me-2"></i>Transferências</a></li>
                </ul>
            </div>

            <div class="dropdown">
                <button class="btn btn-sm {{ in_array($tipoAtual, ['pagar', 'receber'], true) ? 'btn-farmflow' : 'btn-outline-secondary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-list-check me-1"></i>{{ $tipoAtual === 'receber' ? 'A receber' : ($tipoAtual === 'pagar' ? 'A pagar' : 'Pendências') }}
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item @if($tipoAtual === 'pagar') active @endif" href="{{ $urlFiltro(['filtro' => 'pagar']) }}"><i class="bi bi-calendar2-exclamation me-2"></i>A pagar</a></li>
                    <li><a class="dropdown-item @if($tipoAtual === 'receber') active @endif" href="{{ $urlFiltro(['filtro' => 'receber']) }}"><i class="bi bi-calendar2-check me-2"></i>A receber</a></li>
                </ul>
            </div>

            @if ($podeAprovarFinanceiro)
                <a class="btn btn-sm {{ $tipoAtual === 'solicitacoes' ? 'btn-farmflow' : 'btn-outline-secondary' }}" href="{{ $urlFiltro(['filtro' => 'solicitacoes']) }}">
                    <i class="bi bi-shield-exclamation me-1"></i>Solicitações
                </a>
            @endif

            <div class="dropdown">
                <button class="btn btn-sm {{ $contaAtual ? 'btn-farmflow' : 'btn-outline-secondary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-bank me-1"></i>{{ $contaAtual ? 'Banco filtrado' : 'Bancos' }}
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item @if(!$contaAtual) active @endif" href="{{ $urlFiltro(['conta_id' => null]) }}">Todos os bancos</a></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ($contas as $conta)
                        <li>
                            <a class="dropdown-item @if($contaAtual === $conta->id) active @endif" href="{{ $urlFiltro(['conta_id' => $conta->id]) }}">
                                {{ $conta->nome }}
                                <small class="d-block">{{ $conta->detalhe }}</small>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.relatorio-lancamentos.index', request()->query()) }}">
                <i class="bi bi-download"></i> Gerar relatório
            </a>
        </div>

        <section class="panel ff-finance-ledger-panel">
            <div class="panel-head">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h2 class="mb-0">
                        <i class="bi {{ match($tipoAtual) { 'despesas', 'pagar' => 'bi-arrow-down-circle', 'receitas', 'receber' => 'bi-arrow-up-circle', 'transferencias' => 'bi-arrow-left-right', default => 'bi-list-ul' } }} me-2"></i>
                        Lançamentos ({{ $totalLancamentos }})
                    </h2>
                    <a class="btn btn-sm {{ in_array($tipoAtual, ['pagar', 'receber'], true) ? 'btn-farmflow' : 'btn-outline-secondary' }}" href="{{ $urlFiltro(['filtro' => 'pagar']) }}">
                        <i class="bi bi-list-check"></i> Pendentes
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.contas.index', [], false) }}">
                        <i class="bi bi-bank"></i> Bancos
                    </a>
                </div>
                <button class="btn primary" type="button" data-bs-toggle="modal" data-bs-target="#financeiroNovoLancamentoModal">
                    <i class="bi bi-plus-lg"></i> Novo Lançamento
                </button>
            </div>

            <div class="ff-finance-datatable-toolbar">
                <label>
                    <span>Exibir</span>
                    <select class="form-select form-select-sm" data-ff-ledger-page-size>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Todos</option>
                    </select>
                    <span>resultados por página</span>
                </label>

            </div>

            <div class="table-wrap ff-finance-ledger-wrap">
                <table class="ff-lancamentos-table" data-ff-ledger-table>
                    <thead>
                    <tr>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="0" data-type="date" data-default-direction="desc" aria-label="Ordenar por data">
                                <span>Data</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="1" data-type="text" data-default-direction="asc" aria-label="Ordenar por tipo">
                                <span>Tipo</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="2" data-type="text" data-default-direction="asc" aria-label="Ordenar por descrição">
                                <span>Descrição</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="3" data-type="text" data-default-direction="asc" aria-label="Ordenar por pessoa">
                                <span>Pessoa</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="4" data-type="text" data-default-direction="asc" aria-label="Ordenar por safra e categoria">
                                <span>Safra/Categoria</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="5" data-type="text" data-default-direction="asc" aria-label="Ordenar por conta">
                                <span>Conta</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="6" data-type="number" data-default-direction="desc" aria-label="Ordenar por valor">
                                <span>Valor</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="7" data-type="date" data-default-direction="desc" aria-label="Ordenar por previsto">
                                <span>Previsto</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="8" data-type="text" data-default-direction="asc" aria-label="Ordenar por status financeiro">
                                <span>Status financeiro</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($lancamentos as $row)
                        @php
                            $linhaDestacada = $row->tipo === 'despesa' && (int) $row->id === $lancamentoDestacado;
                        @endphp
                        <tr
                            @class([
                            'ff-row-approval' => $row->needs_approval,
                            'ff-row-rejected' => $row->is_rejected,
                            'ff-row-overdue' => $row->is_overdue,
                            'ff-row-pending' => $row->is_pending,
                            'ff-row-highlight' => $linhaDestacada,
                        ])
                            @if ($linhaDestacada) data-ff-highlighted-ledger @endif
                        >
                            <td data-order="{{ $row->data_sort }}" data-ff-sort-value="{{ $row->data_sort ?: $row->data }}">{{ FarmFormat::date($row->data) }}</td>
                            <td data-ff-sort-value="{{ $row->tipo_label }}"><span class="badge {{ $row->type_tone }}">{{ $row->tipo_label }}</span></td>
                            <td data-ff-sort-value="{{ trim(($row->descricao ?? '').' '.($row->descricao_extra ?? '')) }}">
                                <strong>{{ $row->descricao }}</strong>
                                @if ($row->descricao_extra)
                                    <small class="d-block">{{ $row->descricao_extra }}</small>
                                @endif
                            </td>
                            <td data-ff-sort-value="{{ trim(($row->pessoa ?? '').' '.($row->pessoa_extra ?? '')) }}">
                                {{ $row->pessoa }}
                                @if ($row->pessoa_extra)
                                    <small class="d-block">{{ $row->pessoa_extra }}</small>
                                @endif
                            </td>
                            <td data-ff-sort-value="{{ $row->safra_categoria ?: '-' }}">{{ $row->safra_categoria ?: '-' }}</td>
                            <td data-ff-sort-value="{{ $row->conta }}">{{ $row->conta }}</td>
                            <td class="{{ $row->value_tone }}" data-ff-sort-value="{{ $row->valor }}">{{ $fmtMoney($row->valor) }}</td>
                            <td data-ff-sort-value="{{ $row->previsto ?: $row->data }}">{{ FarmFormat::date($row->previsto) }}</td>
                            <td data-ff-sort-value="{{ trim(($row->status_label ?? '').' '.($row->workflow_detail ?? '')) }}">
                                <span class="pill {{ $row->status_tone }}">{{ $row->status_label }}</span>
                                @if ($row->workflow_detail)
                                    <small class="d-block {{ $row->workflow_detail_tone }}">{{ $row->workflow_detail }}</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="dropdown ff-row-actions">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-label="Ações">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if ($row->tipo === 'transferencia')
                                            <li><a class="dropdown-item" href="{{ route('financeiro.contas.index', [], false) }}"><i class="bi bi-bank me-2"></i>Ver bancos</a></li>
                                            <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#financeiroTransferenciaModal"><i class="bi bi-arrow-left-right me-2"></i>Nova transferência</button></li>
                                        @else
                                            <li>
                                                @if ($row->tipo === 'despesa')
                                                    <a
                                                        class="dropdown-item"
                                                        href="{{ $row->action_url }}"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#financeiroEditarDespesaModal"
                                                        data-ff-expense-edit-trigger
                                                        data-ff-expense-edit-url="{{ $row->action_url }}{{ str_contains($row->action_url, '?') ? '&' : '?' }}modal=1"
                                                    >
                                                        <i class="bi bi-pencil-square me-2"></i>Editar
                                                    </a>
                                                @else
                                                    <a class="dropdown-item" href="{{ $row->action_url }}">
                                                        <i class="bi bi-pencil-square me-2"></i>Editar
                                                    </a>
                                                @endif
                                            </li>
                                            <li><a class="dropdown-item" href="{{ $row->duplicate_url }}"><i class="bi bi-files me-2"></i>Duplicar</a></li>

                                            @if ($podeAprovarFinanceiro && $row->can_approve)
                                                <li>
                                                    <form method="post" action="{{ $row->approve_url }}">
                                                        @csrf
                                                        <button class="dropdown-item" type="submit"><i class="bi bi-check2-circle me-2"></i>Aprovar</button>
                                                    </form>
                                                </li>
                                            @endif

                                            @if ($podeAprovarFinanceiro && $row->tipo === 'despesa' && $row->can_pay)
                                                <li>
                                                    <button
                                                        class="dropdown-item"
                                                        type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#financeiroPagamentoModal"
                                                        data-ff-pay-url="{{ $row->pay_url }}"
                                                        data-ff-pay-description="{{ $row->descricao }}"
                                                        data-ff-pay-value="{{ $fmtMoney($row->valor) }}"
                                                        data-ff-pay-date="{{ date('Y-m-d') }}"
                                                    >
                                                        <i class="bi bi-cash-coin me-2"></i>Confirmar pagamento
                                                    </button>
                                                </li>
                                            @endif

                                            @if ($podeAprovarFinanceiro && $row->tipo === 'receita' && $row->can_receive)
                                                <li>
                                                    <form method="post" action="{{ $row->receive_url }}">
                                                        @csrf
                                                        <input type="hidden" name="data_recebimento" value="{{ date('Y-m-d') }}">
                                                        <button class="dropdown-item" type="submit"><i class="bi bi-cash-coin me-2"></i>Confirmar recebimento</button>
                                                    </form>
                                                </li>
                                            @endif

                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="post" action="{{ $row->cancel_url }}" onsubmit="return confirm('Excluir/cancelar este lançamento?');">
                                                    @csrf
                                                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash3 me-2"></i>Excluir</button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr data-ff-ledger-empty><td colspan="10" class="text-center py-4">Nenhum lançamento encontrado para o filtro selecionado.</td></tr>
                    @endforelse
                        <tr data-ff-ledger-filter-empty hidden><td colspan="10" class="text-center py-4">Nenhum lançamento encontrado para a busca informada.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="ff-finance-datatable-footer">
                <span data-ff-ledger-info>Mostrando registros.</span>
                <div class="ff-finance-datatable-pagination">
                    <button class="btn btn-sm" type="button" data-ff-ledger-prev>Anterior</button>
                    <span data-ff-ledger-page>1</span>
                    <button class="btn btn-sm" type="button" data-ff-ledger-next>Próximo</button>
                </div>
            </div>

        </section>

        <div class="grid two mt-4">
            @include('financeiro.partials.agenda')
            @include('financeiro.partials.contas')
        </div>
    </div>

    @include('financeiro.partials.novo-lancamento-modal')

    <div class="modal fade ff-financial-form-modal ff-payment-modal" id="financeiroPagamentoModal" tabindex="-1" aria-labelledby="financeiroPagamentoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content ff-financial-form-content" data-ff-payment-form>
                @csrf
                <div class="modal-header modal-header-green ff-financial-form-header">
                    <div class="ff-financial-form-title">
                        <h5 class="modal-title" id="financeiroPagamentoModalLabel">
                            <i class="bi bi-cash-coin"></i> Confirmar pagamento
                        </h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <p class="ff-financial-launch-help" data-ff-payment-summary>
                        Selecione a conta real usada para pagar a despesa.
                    </p>

                    <div class="ff-financial-form-grid ff-payment-grid">
                        <label class="ff-financial-field">
                            <span>Data do pagamento</span>
                            <input type="date" name="data_pagamento" value="{{ date('Y-m-d') }}" data-ff-payment-date>
                        </label>

                        <label class="ff-financial-field">
                            <span>Conta real usada no pagamento *</span>
                            <select name="conta_id" required data-ff-payment-account>
                                <option value="">Selecione a conta...</option>
                                @foreach ($contas as $conta)
                                    <option value="{{ $conta->id }}" data-balance="{{ $conta->saldo }}" data-balance-number="{{ $conta->saldo_numero }}">
                                        {{ $conta->nome }}{{ $conta->detalhe ? ' - '.$conta->detalhe : '' }} | Saldo {{ $conta->saldo }}
                                    </option>
                                @endforeach
                            </select>
                            <small data-ff-payment-balance>O saldo será exibido ao selecionar a conta.</small>
                        </label>

                        <div class="ff-payment-warning" data-ff-payment-warning hidden>
                            <i class="bi bi-exclamation-triangle"></i>
                            <span></span>
                        </div>
                    </div>

                    @if ($contas->isEmpty())
                        <div class="alert alert-warning mt-3">
                            Cadastre uma conta ativa antes de confirmar pagamentos.
                        </div>
                    @endif
                </div>

                <div class="modal-footer ff-modal-footer-split">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn primary" @disabled($contas->isEmpty())>
                        <i class="bi bi-check2-circle"></i> Confirmar pagamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade ff-expense-edit-ajax-modal" id="financeiroEditarDespesaModal" tabindex="-1" aria-labelledby="financeiroEditarDespesaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered ff-expense-edit-dialog" data-ff-expense-edit-container>
            <div class="modal-content ff-expense-edit-loading">
                <div class="modal-header modal-header-green ff-expense-edit-header">
                    <h5 class="modal-title" id="financeiroEditarDespesaModalLabel">
                        <i class="bi bi-pencil-square"></i>
                        Editar Despesa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    Carregando formulário...
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.querySelector('[data-ff-ledger-table]');
            const tbody = table?.querySelector('tbody');
            const pageSizeSelect = document.querySelector('[data-ff-ledger-page-size]');
            const searchInput = document.querySelector('[data-ff-ledger-search]');
            const info = document.querySelector('[data-ff-ledger-info]');
            const pageIndicator = document.querySelector('[data-ff-ledger-page]');
            const previousButton = document.querySelector('[data-ff-ledger-prev]');
            const nextButton = document.querySelector('[data-ff-ledger-next]');
            const paymentModal = document.getElementById('financeiroPagamentoModal');
            const paymentForm = paymentModal?.querySelector('[data-ff-payment-form]');
            const paymentSummary = paymentModal?.querySelector('[data-ff-payment-summary]');
            const paymentDate = paymentModal?.querySelector('[data-ff-payment-date]');
            const paymentAccount = paymentModal?.querySelector('[data-ff-payment-account]');
            const paymentBalance = paymentModal?.querySelector('[data-ff-payment-balance]');
            const paymentWarning = paymentModal?.querySelector('[data-ff-payment-warning]');
            const expenseEditModal = document.getElementById('financeiroEditarDespesaModal');
            const expenseEditContainer = expenseEditModal?.querySelector('[data-ff-expense-edit-container]');
            const highlightedRow = document.querySelector('[data-ff-highlighted-ledger]');

            const loadingEditModal = () => `
                <div class="modal-content ff-expense-edit-loading">
                    <div class="modal-header modal-header-green ff-expense-edit-header">
                        <h5 class="modal-title" id="financeiroEditarDespesaModalLabel">
                            <i class="bi bi-pencil-square"></i>
                            Editar Despesa
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">Carregando formulário...</div>
                </div>
            `;

            const errorEditModal = (fallbackUrl) => `
                <div class="modal-content ff-expense-edit-loading">
                    <div class="modal-header modal-header-green ff-expense-edit-header">
                        <h5 class="modal-title" id="financeiroEditarDespesaModalLabel">
                            <i class="bi bi-exclamation-triangle"></i>
                            Não foi possível abrir a edição
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Tente novamente ou abra a tela de edição em modo completo.</p>
                        <a class="btn primary" href="${fallbackUrl}">Abrir edição</a>
                    </div>
                </div>
            `;

            expenseEditModal?.addEventListener('show.bs.modal', async (event) => {
                const trigger = event.relatedTarget;
                const modalUrl = trigger?.getAttribute('data-ff-expense-edit-url');
                const fallbackUrl = trigger?.getAttribute('href') || modalUrl || '#';

                if (!expenseEditContainer || !modalUrl) {
                    return;
                }

                expenseEditContainer.innerHTML = loadingEditModal();

                try {
                    const response = await fetch(modalUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Falha ao carregar formulário');
                    }

                    expenseEditContainer.innerHTML = await response.text();
                } catch (error) {
                    expenseEditContainer.innerHTML = errorEditModal(fallbackUrl);
                }
            });

            expenseEditModal?.addEventListener('hidden.bs.modal', () => {
                if (expenseEditContainer) {
                    expenseEditContainer.innerHTML = loadingEditModal();
                }
            });

            paymentModal?.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;

                if (!button || !paymentForm) {
                    return;
                }

                paymentForm.action = button.getAttribute('data-ff-pay-url') || '';

                if (paymentSummary) {
                    const description = button.getAttribute('data-ff-pay-description') || 'despesa';
                    const value = button.getAttribute('data-ff-pay-value') || '';
                    paymentSummary.textContent = `Baixar ${description}${value ? ` no valor de ${value}` : ''}. Informe a conta real para registrar a saída.`;
                }

                if (paymentDate) {
                    paymentDate.value = button.getAttribute('data-ff-pay-date') || paymentDate.value;
                }

                if (paymentAccount) {
                    paymentAccount.value = '';
                    paymentAccount.dispatchEvent(new Event('change'));
                }
            });

            const parseBalance = (value) => {
                const original = String(value || '0').trim();

                if (/^-?\d+(\.\d+)?$/.test(original)) {
                    return Number(original);
                }

                const normalized = original
                    .replace(/\s/g, '')
                    .replace(/\./g, '')
                    .replace(',', '.')
                    .replace(/[^0-9.-]/g, '');

                return Number(normalized) || 0;
            };

            paymentAccount?.addEventListener('change', () => {
                const selected = paymentAccount.options[paymentAccount.selectedIndex];
                const balance = selected?.getAttribute('data-balance') || '';
                const balanceNumber = parseBalance(selected?.getAttribute('data-balance-number') || balance);

                if (paymentWarning) {
                    const messageTarget = paymentWarning.querySelector('span');

                    paymentWarning.hidden = true;
                    paymentWarning.classList.remove('is-negative', 'is-empty');

                    if (balance && balanceNumber <= 0) {
                        paymentWarning.hidden = false;
                        paymentWarning.classList.add(balanceNumber < 0 ? 'is-negative' : 'is-empty');

                        if (messageTarget) {
                            messageTarget.textContent = balanceNumber < 0
                                ? `Atenção: a conta selecionada está com saldo negativo (${balance}). Confirme se esta é a conta correta antes de baixar o pagamento.`
                                : `Atenção: a conta selecionada está sem saldo (${balance}). Confirme se esta é a conta correta antes de baixar o pagamento.`;
                        }
                    }
                }

                if (paymentBalance) {
                    paymentBalance.textContent = balance
                        ? `Saldo atual da conta selecionada: ${balance}.`
                        : 'O saldo será exibido ao selecionar a conta.';
                }
            });

            if (!table || !tbody) {
                return;
            }

            const triggers = Array.from(table.querySelectorAll('[data-ff-sort-trigger]'));
            const rows = Array.from(table.querySelectorAll('tbody tr')).filter((row) => !row.hasAttribute('data-ff-ledger-empty'));
            const filterEmptyRow = table.querySelector('[data-ff-ledger-filter-empty]');
            const dataRows = rows.filter((row) => !row.hasAttribute('data-ff-ledger-filter-empty'));
            let activeSort = {
                column: null,
                direction: null,
            };
            let currentPage = 1;

            const normalize = (value) => String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();

            const getCellValue = (row, column, type) => {
                const cell = row.children[column];

                if (!cell) {
                    return '';
                }

                const value = cell.getAttribute('data-ff-sort-value') || cell.getAttribute('data-order') || cell.textContent || '';

                if (type === 'number') {
                    return Number(String(value).replace(/\./g, '').replace(',', '.')) || 0;
                }

                if (type === 'date') {
                    return String(value || '0000-00-00');
                }

                return normalize(value);
            };

            const rowMatchesSearch = (row) => {
                const searchTerm = normalize(searchInput?.value || '');

                return !searchTerm || normalize(row.textContent).includes(searchTerm);
            };

            const compareValues = (first, second, type) => {
                if (type === 'number') {
                    return first - second;
                }

                if (type === 'date') {
                    return String(first).localeCompare(String(second));
                }

                return String(first).localeCompare(String(second), 'pt-BR', {
                    numeric: true,
                    sensitivity: 'base',
                });
            };

            const updateHeaderState = (trigger = null, direction = null) => {
                triggers.forEach((item) => {
                    const icon = item.querySelector('[data-ff-sort-icon]');
                    item.classList.toggle('is-active', item === trigger);
                    item.dataset.direction = item === trigger ? direction : '';
                    item.setAttribute('aria-sort', item === trigger ? (direction === 'asc' ? 'ascending' : 'descending') : 'none');

                    if (icon) {
                        icon.className = 'bi ' + (item === trigger
                            ? (direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
                            : 'bi-chevron-expand');
                    }
                });
            };

            const sortedRows = (filteredRows) => {
                if (activeSort.column === null) {
                    return filteredRows.slice();
                }

                const trigger = triggers.find((item) => Number(item.dataset.column) === activeSort.column);
                const type = trigger?.dataset.type || 'text';
                const factor = activeSort.direction === 'asc' ? 1 : -1;

                return filteredRows
                    .slice()
                    .sort((firstRow, secondRow) => {
                        const first = getCellValue(firstRow, activeSort.column, type);
                        const second = getCellValue(secondRow, activeSort.column, type);
                        const result = compareValues(first, second, type);

                        if (result !== 0) {
                            return result * factor;
                        }

                        return dataRows.indexOf(firstRow) - dataRows.indexOf(secondRow);
                    });
            };

            const selectedPageSize = () => {
                const selected = pageSizeSelect?.value || '25';

                return selected === 'all' ? Number.MAX_SAFE_INTEGER : Number(selected) || 25;
            };

            const renderRows = () => {
                const filteredRows = dataRows.filter(rowMatchesSearch);
                const orderedRows = sortedRows(filteredRows);
                const pageSize = selectedPageSize();
                const totalPages = Math.max(1, Math.ceil(orderedRows.length / pageSize));

                currentPage = Math.min(currentPage, totalPages);

                const start = (currentPage - 1) * pageSize;
                const end = start + pageSize;

                dataRows.forEach((row) => {
                    row.hidden = true;
                    tbody.appendChild(row);
                });

                orderedRows.forEach((row, index) => {
                    row.hidden = index < start || index >= end;
                    tbody.appendChild(row);
                });

                if (filterEmptyRow) {
                    filterEmptyRow.hidden = orderedRows.length > 0 || dataRows.length === 0;
                    tbody.appendChild(filterEmptyRow);
                }

                if (info) {
                    const firstVisible = orderedRows.length ? start + 1 : 0;
                    const lastVisible = Math.min(end, orderedRows.length);
                    info.textContent = `Mostrando de ${firstVisible} até ${lastVisible} de ${orderedRows.length} registros`;
                }

                if (pageIndicator) {
                    pageIndicator.textContent = String(currentPage);
                }

                if (previousButton) {
                    previousButton.disabled = currentPage <= 1;
                }

                if (nextButton) {
                    nextButton.disabled = currentPage >= totalPages;
                }
            };

            const sortByColumn = (trigger) => {
                const column = Number(trigger.dataset.column);
                const previousDirection = activeSort.column === column ? activeSort.direction : null;
                const direction = previousDirection
                    ? (previousDirection === 'asc' ? 'desc' : 'asc')
                    : (trigger.dataset.defaultDirection || 'asc');

                activeSort = { column, direction };
                updateHeaderState(trigger, direction);
                currentPage = 1;
                renderRows();
            };

            triggers.forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    sortByColumn(trigger);
                });
            });

            pageSizeSelect?.addEventListener('change', () => {
                currentPage = 1;
                renderRows();
            });

            searchInput?.addEventListener('input', () => {
                currentPage = 1;
                renderRows();
            });

            previousButton?.addEventListener('click', () => {
                currentPage = Math.max(1, currentPage - 1);
                renderRows();
            });

            nextButton?.addEventListener('click', () => {
                currentPage += 1;
                renderRows();
            });

            updateHeaderState();
            renderRows();

            if (highlightedRow) {
                highlightedRow.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            }
        });
    </script>
@endpush

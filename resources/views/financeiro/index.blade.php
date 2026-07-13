@extends('layouts.farmfort', [
    'title' => 'FarmFort - '.$title,
    'topbarLabel' => 'Painel',
])

@php
    use App\Support\FarmFormat;

    $fmtMoney = fn ($value) => FarmFormat::money($value);
    $tipoAtual = $filtros['tipo'] ?? 'todos';
    $contaAtual = (int) ($filtros['conta_id'] ?? 0);
    $urlFiltro = function (array $params = [], array $forget = []) {
        $query = request()->query();

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

        <section class="ff-finance-filter-strip">
            <form method="get" class="ff-finance-filter-form">
                <input type="hidden" name="conta_id" value="{{ $contaAtual ?: '' }}">
                <div>
                    <label for="filtroTipoLancamento">Tipo de lançamento</label>
                    <select id="filtroTipoLancamento" name="filtro" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="todos" @selected($tipoAtual === 'todos')>Todos</option>
                        <option value="despesas" @selected($tipoAtual === 'despesas')>Despesas</option>
                        <option value="receitas" @selected($tipoAtual === 'receitas')>Receitas</option>
                        <option value="transferencias" @selected($tipoAtual === 'transferencias')>Transferências</option>
                    </select>
                </div>
                <div>
                    <label for="filtroMesLancamentos">Filtro por mês</label>
                    <input type="month" id="filtroMesLancamentos" name="mes" class="form-control form-control-sm" value="{{ $filtros['mes'] ?? '' }}">
                </div>
                <div>
                    <label for="filtroDataInicio">Data Inicial</label>
                    <input type="date" id="filtroDataInicio" name="data_inicio" class="form-control form-control-sm" value="{{ $filtros['data_inicio'] }}">
                </div>
                <div>
                    <label for="filtroDataFim">Data Final</label>
                    <input type="date" id="filtroDataFim" name="data_fim" class="form-control form-control-sm" value="{{ $filtros['data_fim'] }}">
                </div>
                <button type="submit" class="btn btn-sm btn-farmflow">
                    <i class="bi bi-search"></i> Aplicar período
                </button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ $urlFiltro(['todos' => 1, 'mes' => null, 'data_inicio' => null, 'data_fim' => null]) }}">
                    <i class="bi bi-list-ul"></i> Todos
                </a>
            </form>
        </section>

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
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.contas.index') }}">
                        <i class="bi bi-bank"></i> Bancos
                    </a>
                </div>
                <button class="btn primary" type="button" data-bs-toggle="modal" data-bs-target="#financeiroNovoLancamentoModal">
                    <i class="bi bi-plus-lg"></i> Novo Lançamento
                </button>
            </div>

            <div class="table-wrap ff-finance-ledger-wrap">
                <table class="ff-lancamentos-table">
                    <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Pessoa</th>
                        <th>Safra/Categoria</th>
                        <th>Conta</th>
                        <th>Valor</th>
                        <th>Previsto</th>
                        <th>Status financeiro</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($lancamentos as $row)
                        <tr @class([
                            'ff-row-approval' => $row->needs_approval,
                            'ff-row-rejected' => $row->is_rejected,
                            'ff-row-overdue' => $row->is_overdue,
                            'ff-row-pending' => in_array($row->status, ['pendente', 'vencido'], true),
                        ])>
                            <td data-order="{{ $row->data_sort }}">{{ FarmFormat::date($row->data) }}</td>
                            <td><span class="badge {{ $row->type_tone }}">{{ $row->tipo_label }}</span></td>
                            <td>
                                <strong>{{ $row->descricao }}</strong>
                                @if ($row->descricao_extra)
                                    <small class="d-block">{{ $row->descricao_extra }}</small>
                                @endif
                            </td>
                            <td>
                                {{ $row->pessoa }}
                                @if ($row->pessoa_extra)
                                    <small class="d-block">{{ $row->pessoa_extra }}</small>
                                @endif
                            </td>
                            <td>{{ $row->safra_categoria ?: '-' }}</td>
                            <td>{{ $row->conta }}</td>
                            <td class="{{ $row->value_tone }}">{{ $fmtMoney($row->valor) }}</td>
                            <td>{{ FarmFormat::date($row->previsto) }}</td>
                            <td>
                                <span class="pill {{ $row->status_tone }}">{{ $row->status_label }}</span>
                                @if ($row->status_detail)
                                    <small class="d-block {{ $row->is_rejected ? 'text-danger' : 'text-warning' }}">{{ $row->status_detail }}</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="dropdown ff-row-actions">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-label="Ações">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @if ($row->tipo === 'transferencia')
                                            <li><a class="dropdown-item" href="{{ route('financeiro.contas.index') }}"><i class="bi bi-bank me-2"></i>Ver bancos</a></li>
                                            <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#financeiroTransferenciaModal"><i class="bi bi-arrow-left-right me-2"></i>Nova transferência</button></li>
                                        @else
                                            <li><a class="dropdown-item" href="{{ $row->action_url }}"><i class="bi bi-pencil-square me-2"></i>Editar</a></li>
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
                                                    <form method="post" action="{{ $row->pay_url }}">
                                                        @csrf
                                                        <input type="hidden" name="data_pagamento" value="{{ date('Y-m-d') }}">
                                                        <button class="dropdown-item" type="submit"><i class="bi bi-cash-coin me-2"></i>Confirmar pagamento</button>
                                                    </form>
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
                        <tr><td colspan="10" class="text-center py-4">Nenhum lançamento encontrado para o filtro selecionado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid two mt-4">
            @include('financeiro.partials.agenda')
            @include('financeiro.partials.contas')
        </div>
    </div>

    @include('financeiro.partials.novo-lancamento-modal')
@endsection

@extends('layouts.farmfort', [
    'title' => 'FarmFort - '.$title,
    'topbarLabel' => 'Painel',
])

@php
    use App\Support\FarmFormat;

    $fmtMoney = fn ($value) => FarmFormat::money($value);
    $urlFiltro = fn ($params = []) => route('financeiro.index', array_filter([
        ...request()->query(),
        ...$params,
    ], fn ($value) => $value !== null && $value !== ''));
    $tipoAtual = $filtros['tipo'] ?? 'todos';
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }} · Resumo geral: {{ $periodoLabel }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('financeiro.relatorio-lancamentos.index') }}"><i class="bi bi-download"></i> Gerar relatório</a>
            <a class="btn primary" href="{{ route('financeiro.lancamentos.create') }}"><i class="bi bi-plus-lg"></i> Novo Lançamento</a>
        </div>
    </div>

    <section class="ff-date-filter-panel mb-3">
        <form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-0">
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
                <input type="month" id="filtroMesLancamentos" name="mes" class="form-control form-control-sm" value="{{ $filtros['todos'] ? '' : $filtros['mes'] }}">
            </div>
            <div>
                <label for="filtroDataInicio">Data Inicial</label>
                <input type="date" id="filtroDataInicio" name="data_inicio" class="form-control form-control-sm" value="{{ $filtros['data_inicio'] }}">
            </div>
            <div>
                <label for="filtroDataFim">Data Final</label>
                <input type="date" id="filtroDataFim" name="data_fim" class="form-control form-control-sm" value="{{ $filtros['data_fim'] }}">
            </div>
            <button type="submit" class="btn btn-sm btn-farmflow"><i class="bi bi-search"></i> Aplicar período</button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.index', ['todos' => 1, 'filtro' => $tipoAtual]) }}"><i class="bi bi-list-ul"></i> Todos</a>
        </form>
    </section>

    <section class="stats">
        @foreach ($cards as $card)
            <article class="stat {{ $card['tone'] ?? '' }}">
                <span>{{ $card['label'] }}</span>
                <strong>{{ $card['value'] }}</strong>
            </article>
        @endforeach
    </section>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <span class="text-muted small fw-bold me-1">Filtrar:</span>
        <div class="dropdown">
            <button class="btn btn-sm {{ in_array($tipoAtual, ['todos', 'despesas', 'receitas', 'transferencias'], true) ? 'btn-farmflow' : 'btn-outline-secondary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-list-ul me-1"></i>{{ match($tipoAtual) { 'despesas' => 'Despesas', 'receitas' => 'Receitas', 'transferencias' => 'Transferências', default => 'Lançamentos' } }}
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ $urlFiltro(['filtro' => 'todos']) }}"><i class="bi bi-list-ul me-2"></i>Todos os lançamentos</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ $urlFiltro(['filtro' => 'despesas']) }}"><i class="bi bi-arrow-down-circle me-2"></i>Despesas</a></li>
                <li><a class="dropdown-item" href="{{ $urlFiltro(['filtro' => 'receitas']) }}"><i class="bi bi-arrow-up-circle me-2"></i>Receitas</a></li>
                <li><a class="dropdown-item" href="{{ $urlFiltro(['filtro' => 'transferencias']) }}"><i class="bi bi-arrow-left-right me-2"></i>Transferências</a></li>
            </ul>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm {{ in_array($tipoAtual, ['pagar', 'receber'], true) ? 'btn-farmflow' : 'btn-outline-secondary' }} dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-calendar2-exclamation me-1"></i>{{ $tipoAtual === 'receber' ? 'A receber' : ($tipoAtual === 'pagar' ? 'A pagar' : 'Pendências') }}
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ $urlFiltro(['filtro' => 'pagar']) }}"><i class="bi bi-calendar2-exclamation me-2"></i>A pagar</a></li>
                <li><a class="dropdown-item" href="{{ $urlFiltro(['filtro' => 'receber']) }}"><i class="bi bi-calendar2-check me-2"></i>A receber</a></li>
            </ul>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.despesas.index', ['aprovacao' => 'pendente']) }}"><i class="bi bi-shield-exclamation me-1"></i>Solicitações</a>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h2 class="mb-0"><i class="bi bi-list-ul me-2"></i>Lançamentos ({{ $totalLancamentos }})</h2>
                <a class="btn btn-sm {{ in_array($tipoAtual, ['pagar', 'receber'], true) ? 'btn-farmflow' : 'btn-outline-secondary' }}" href="{{ $urlFiltro(['filtro' => 'pagar']) }}"><i class="bi bi-list-check"></i> Pendentes</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.contas.index') }}"><i class="bi bi-bank"></i> Bancos</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.relatorio-lancamentos.index') }}"><i class="bi bi-download"></i> Gerar relatório</a>
            </div>
            <a class="btn primary" href="{{ route('financeiro.lancamentos.create') }}"><i class="bi bi-plus-lg"></i> Novo Lançamento</a>
        </div>
        <div class="table-wrap">
            <table>
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
                    <th>Ações</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($lancamentos as $row)
                    <tr>
                        <td>{{ FarmFormat::date($row->data) }}</td>
                        <td><span class="badge {{ $row->tipo === 'receita' ? 'bg-success' : ($row->tipo === 'transferencia' ? 'bg-info' : 'bg-danger') }}">{{ $row->tipo_label }}</span></td>
                        <td>{{ $row->descricao }}</td>
                        <td>{{ $row->pessoa }}</td>
                        <td>{{ $row->safra_categoria ?: '-' }}</td>
                        <td>{{ $row->conta }}</td>
                        <td class="{{ $row->tipo === 'receita' ? 'text-success' : ($row->tipo === 'despesa' ? 'text-danger' : '') }}">{{ $fmtMoney($row->valor) }}</td>
                        <td>{{ FarmFormat::date($row->previsto) }}</td>
                        <td>
                            <span class="pill {{ in_array($row->status, ['pago', 'recebido', 'transferido'], true) ? 'success' : 'warning' }}">{{ FarmFormat::statusLabel($row->status) }}</span>
                            @if ($row->status_aprovacao === 'pendente')
                                <small class="d-block text-warning">Aguardando aprovação</small>
                            @endif
                        </td>
                        <td>
                            @if ($row->tipo === 'receita')
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.receitas.edit', $row->id) }}" title="Abrir"><i class="bi bi-three-dots-vertical"></i></a>
                            @elseif ($row->tipo === 'despesa')
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.despesas.edit', $row->id) }}" title="Abrir"><i class="bi bi-three-dots-vertical"></i></a>
                            @else
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('financeiro.contas.index') }}" title="Abrir"><i class="bi bi-three-dots-vertical"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10">Nenhum lançamento encontrado para o filtro selecionado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid two mt-4">
        @include('financeiro.partials.agenda')
        @include('financeiro.partials.contas')
    </div>
@endsection

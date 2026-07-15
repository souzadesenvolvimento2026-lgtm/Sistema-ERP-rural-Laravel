@extends('layouts.farmfort', ['title' => 'FarmFort - Pedidos Fiscais'])

@php
    use App\Support\FarmFormat;
@endphp

@section('content')
    <div class="ff-purchase-page">
        <span class="visually-hidden">Pedidos de compras</span>

        <section class="ff-purchase-filter-card" aria-label="Filtros dos pedidos fiscais">
            <form method="get" action="{{ route('compras.pedidos.index') }}" class="ff-purchase-filter-form">
                <label>
                    <span>Status</span>
                    <select name="status" class="form-select">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>De</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
                </label>

                <label>
                    <span>Até</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
                </label>

                <label class="ff-purchase-filter-supplier">
                    <span>Fornecedor</span>
                    <input type="search" name="supplier" value="{{ $filters['supplier'] }}" class="form-control" placeholder="Nome ou CNPJ">
                </label>

                <div class="ff-purchase-filter-actions">
                    <button type="submit" class="btn primary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a class="btn" href="{{ route('compras.pedidos.index') }}">Limpar</a>
                </div>
            </form>
        </section>

        <section class="panel ff-purchase-hero">
            <div class="ff-purchase-hero-content">
                <span class="ff-purchase-hero-icon" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
                <div>
                    <span>Pedidos</span>
                    <h1>Novo Pedido Fiscal</h1>
                    <p>Cadastre o pedido, adicione itens, vincule notas e aprove quando estiver conferido.</p>
                </div>
            </div>
            <button
                class="btn primary ff-purchase-hero-button"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#pedidoCreateModal"
            >
                <i class="bi bi-plus-lg"></i> Novo Pedido
            </button>
        </section>

        <section class="stats ff-purchase-summary-cards" aria-label="Resumo dos pedidos">
            <div class="stat">
                <span>Pedidos</span>
                <strong>{{ $totais['pedidos'] }}</strong>
            </div>
            <div class="stat warning">
                <span>Pendentes</span>
                <strong>{{ $totais['pendentes'] }}</strong>
            </div>
            <div class="stat success">
                <span>Aprovados/Baixados</span>
                <strong>{{ $totais['aprovados'] }}</strong>
            </div>
        </section>

        <section class="panel ff-purchase-orders-panel">
            <div class="panel-head">
                <h2><i class="bi bi-clipboard-check me-2"></i>Pedidos Fiscais</h2>
                <button class="btn primary" type="button" data-bs-toggle="modal" data-bs-target="#pedidoCreateModal">
                    <i class="bi bi-plus-lg"></i> Novo Pedido
                </button>
            </div>

            <div class="ff-purchase-datatable-toolbar">
                <label>
                    <span>Exibir</span>
                    <select class="form-select form-select-sm" data-ff-purchase-page-size>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Todos</option>
                    </select>
                    <span>resultados por página</span>
                </label>

                <label class="ff-purchase-datatable-search">
                    <span>Pesquisar</span>
                    <input class="form-control form-control-sm" type="search" placeholder="Buscar registros" data-ff-purchase-search>
                </label>
            </div>

            <div class="table-wrap ff-purchase-table-wrap">
                <table class="ff-purchase-orders-table" data-ff-purchase-table>
                    <thead>
                    <tr>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="0" data-type="date" data-default-direction="desc" aria-label="Ordenar por data">
                                <span>Data</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="1" data-type="text" data-default-direction="desc" aria-label="Ordenar por número">
                                <span>Número</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="2" data-type="text" data-default-direction="asc" aria-label="Ordenar por fornecedor">
                                <span>Fornecedor</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="3" data-type="number" data-default-direction="desc" aria-label="Ordenar por total">
                                <span>Total</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="ff-table-sort-trigger" data-ff-sort-trigger data-column="4" data-type="text" data-default-direction="asc" aria-label="Ordenar por status">
                                <span>Status</span><i class="bi bi-chevron-expand" data-ff-sort-icon></i>
                            </button>
                        </th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($pedidos as $pedido)
                        <tr>
                            <td data-ff-sort-value="{{ $pedido->issue_date ?: '0000-00-00' }}">{{ FarmFormat::date($pedido->issue_date) }}</td>
                            <td data-ff-sort-value="{{ $pedido->order_number }}"><strong>{{ $pedido->order_number }}</strong></td>
                            <td data-ff-sort-value="{{ trim(($pedido->supplier_name ?? '').' '.($pedido->supplier_cnpj ?? '')) }}">
                                <strong>{{ $pedido->supplier_name ?: '-' }}</strong>
                                <small class="d-block">{{ $pedido->supplier_cnpj ?: '-' }}</small>
                            </td>
                            <td class="ff-purchase-money" data-ff-sort-value="{{ $pedido->total_value }}">
                                <strong>{{ FarmFormat::money($pedido->total_value) }}</strong>
                            </td>
                            <td data-ff-sort-value="{{ $pedido->status_label }}">
                                <span class="pill {{ $pedido->status_tone }}">{{ $pedido->status_label }}</span>
                            </td>
                            <td class="text-end">
                                <div class="ff-purchase-row-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('compras.pedidos.show', $pedido->id) }}">Abrir</a>
                                    @if ($pedido->can_approve)
                                        <form method="post" action="{{ route('compras.pedidos.approve', $pedido->id) }}" onsubmit="return confirm('Aprovar este pedido e lançar no financeiro/estoque?')">
                                            @csrf
                                            <input type="hidden" name="confirmar_aprovacao" value="1">
                                            <button class="btn btn-sm primary" type="submit">Aprovar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr data-ff-purchase-empty>
                            <td colspan="6" class="text-center py-4">Nenhum pedido encontrado para o filtro selecionado.</td>
                        </tr>
                    @endforelse
                        <tr data-ff-purchase-filter-empty hidden>
                            <td colspan="6" class="text-center py-4">Nenhum pedido encontrado para a busca informada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="ff-purchase-datatable-footer">
                <span data-ff-purchase-info>Mostrando registros.</span>
                <div class="ff-purchase-datatable-pagination">
                    <button class="btn btn-sm" type="button" data-ff-purchase-prev>Anterior</button>
                    <span data-ff-purchase-page>1</span>
                    <button class="btn btn-sm" type="button" data-ff-purchase-next>Próximo</button>
                </div>
            </div>
        </section>
    </div>

    @include('compras.pedidos.partials.create-modal')
@endsection

@push('scripts')
    <script src="{{ asset('js/pedido-form.js') }}?v={{ @filemtime(public_path('js/pedido-form.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.querySelector('[data-ff-purchase-table]');
            const tbody = table?.querySelector('tbody');
            const pageSizeSelect = document.querySelector('[data-ff-purchase-page-size]');
            const searchInput = document.querySelector('[data-ff-purchase-search]');
            const info = document.querySelector('[data-ff-purchase-info]');
            const pageIndicator = document.querySelector('[data-ff-purchase-page]');
            const previousButton = document.querySelector('[data-ff-purchase-prev]');
            const nextButton = document.querySelector('[data-ff-purchase-next]');

            if (!table || !tbody) {
                return;
            }

            const triggers = Array.from(table.querySelectorAll('[data-ff-sort-trigger]'));
            const rows = Array.from(table.querySelectorAll('tbody tr')).filter((row) => !row.hasAttribute('data-ff-purchase-empty'));
            const filterEmptyRow = table.querySelector('[data-ff-purchase-filter-empty]');
            const dataRows = rows.filter((row) => !row.hasAttribute('data-ff-purchase-filter-empty'));
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

                const value = cell.getAttribute('data-ff-sort-value') || cell.textContent || '';

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
        });
    </script>
@endpush

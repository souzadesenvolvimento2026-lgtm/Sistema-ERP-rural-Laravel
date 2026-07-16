@extends('layouts.farmfort', ['title' => 'FarmFort - Auditoria'])

@section('content')
    <div class="ff-audit-page">
        <div class="page-head ff-audit-head">
            <div>
                <h1>Auditoria</h1>
                <p class="subtitle">Consulta dos registros de ações realizadas na propriedade atual.</p>
            </div>
            <span class="ff-audit-scope-badge">
                <i class="bi bi-shield-check"></i> Visão da propriedade
            </span>
        </div>

        @include('partials.stats', ['cards' => $cards])

        <section class="panel ff-audit-filter-panel">
            <div class="panel-head">
                <h2><i class="bi bi-shield-check"></i> Auditoria de usuários</h2>
            </div>
            <div class="panel-body">
                @if ($periodoInvertido)
                    <div class="ff-audit-warning">
                        O período informado estava invertido. O FarmFort ajustou automaticamente a data inicial e final para aplicar o filtro.
                    </div>
                @endif

                <form method="get" action="{{ route('auditoria.index') }}" class="ff-audit-filter-grid">
                    <label class="field">
                        <span>Início</span>
                        <input type="date" name="inicio" value="{{ $filtros['inicio'] }}">
                    </label>

                    <label class="field">
                        <span>Fim</span>
                        <input type="date" name="fim" value="{{ $filtros['fim'] }}">
                    </label>

                    <label class="field ff-audit-filter-wide">
                        <span>Usuário</span>
                        <select name="usuario_id">
                            <option value="">Todos</option>
                            @foreach ($usuarios as $usuario)
                                <option value="{{ $usuario->id }}" @selected($filtros['usuario_id'] === (int) $usuario->id)>
                                    {{ $usuario->nome }} - {{ $usuario->email }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field">
                        <span>Lançamento</span>
                        <select name="lancamento">
                            <option value="">Todos</option>
                            @foreach ($lancamentos as $valor => $label)
                                <option value="{{ $valor }}" @selected($filtros['lancamento'] === $valor)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field">
                        <span>Tipo de despesa</span>
                        <select name="tipo_despesa">
                            <option value="">Todos</option>
                            @foreach ($tiposDespesa as $tipo)
                                <option value="{{ $tipo }}" @selected($filtros['tipo_despesa'] === $tipo)>{{ \Illuminate\Support\Str::headline($tipo) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field">
                        <span>Tabela</span>
                        <select name="tabela">
                            <option value="">Todas</option>
                            @foreach ($tabelas as $tabela)
                                <option value="{{ $tabela }}" @selected($filtros['tabela'] === $tabela)>{{ \Illuminate\Support\Str::headline($tabela) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field">
                        <span>Ação</span>
                        <select name="acao">
                            <option value="">Todas</option>
                            @foreach ($acoes as $acao)
                                <option value="{{ $acao }}" @selected($filtros['acao'] === $acao)>{{ \Illuminate\Support\Str::headline($acao) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field ff-audit-search-field">
                        <span>Buscar</span>
                        <input name="busca" value="{{ $filtros['busca'] }}" placeholder="Nome, e-mail, detalhe, ação ou propriedade">
                    </label>

                    <div class="ff-audit-filter-actions">
                        <button class="btn primary" type="submit"><i class="bi bi-search"></i> Filtrar</button>
                        <a class="btn btn-outline-success" href="{{ route('auditoria.exportar', request()->query()) }}">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </a>
                        <a class="btn" href="{{ route('auditoria.index') }}">Limpar</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel ff-audit-records-panel" data-ff-audit-records>
            <div class="panel-head ff-audit-records-head">
                <h2>
                    <i class="bi bi-clock-history"></i> Últimas ações auditadas
                    <small>Mostrando até {{ number_format($limiteTela, 0, ',', '.') }} registros conforme filtro.</small>
                </h2>
            </div>

            <div class="ff-audit-toolbar">
                <label>
                    Exibir
                    <select data-ff-audit-page-size>
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    resultados por página
                </label>

                <label>
                    Pesquisar
                    <input type="search" data-ff-audit-search placeholder="Buscar registros">
                </label>
            </div>

            <div class="ff-audit-table-wrap">
                <table class="ff-audit-table" aria-label="Últimas ações auditadas">
                    <colgroup>
                        <col class="ff-audit-col-date">
                        <col class="ff-audit-col-user">
                        <col class="ff-audit-col-context">
                        <col class="ff-audit-col-action">
                        <col class="ff-audit-col-origin">
                        <col class="ff-audit-col-details">
                        <col class="ff-audit-col-view">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Usuário</th>
                            <th>Contexto</th>
                            <th>Ação</th>
                            <th>Origem</th>
                            <th>Detalhes</th>
                            <th>Ver</th>
                        </tr>
                    </thead>
                    <tbody data-ff-audit-table-body>
                        @forelse ($logs as $log)
                            <tr data-ff-audit-row data-search="{{ strtolower($log->criado_em_legivel.' '.$log->usuario_nome.' '.$log->usuario_email.' '.$log->propriedade.' '.$log->lancamento.' '.$log->acao_legivel.' '.$log->onde.' '.$log->detalhes_resumo) }}">
                                <td>
                                    <strong>{{ substr($log->criado_em_legivel, 0, 10) }}</strong>
                                    <span>{{ substr($log->criado_em_legivel, 11) }}</span>
                                </td>
                                <td>
                                    <strong>{{ $log->usuario_nome }}</strong>
                                    <span>{{ $log->usuario_email }} · {{ $log->usuario_perfil }}</span>
                                </td>
                                <td>
                                    <strong>{{ $log->propriedade }}</strong>
                                    <span>{{ $log->lancamento }}</span>
                                </td>
                                <td>
                                    <span class="ff-audit-action-badge is-{{ $log->tom }}">
                                        {{ $log->acao_legivel }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $log->onde }}</strong>
                                    <span>{{ $log->tabela_tecnica }} · {{ $log->registro }} · IP {{ $log->ip_cliente }}</span>
                                </td>
                                <td>
                                    <p class="ff-audit-detail-summary">{{ $log->detalhes_resumo }}</p>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm ff-audit-detail-button"
                                        data-audit-detail-url="{{ route('auditoria.detalhes', $log->id) }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#auditDetailModal"
                                    >
                                        Ver
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr data-ff-audit-empty>
                                <td colspan="7" class="muted">Nenhum registro de auditoria encontrado para o filtro selecionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="ff-audit-card-list" data-ff-audit-card-list>
                    @foreach ($logs as $log)
                        <article class="ff-audit-card" data-ff-audit-row data-search="{{ strtolower($log->criado_em_legivel.' '.$log->usuario_nome.' '.$log->usuario_email.' '.$log->propriedade.' '.$log->lancamento.' '.$log->acao_legivel.' '.$log->onde.' '.$log->detalhes_resumo) }}">
                            <div>
                                <strong>{{ $log->criado_em_legivel }}</strong>
                                <span class="ff-audit-action-badge is-{{ $log->tom }}">{{ $log->acao_legivel }}</span>
                            </div>
                            <h3>{{ $log->usuario_nome }}</h3>
                            <p>{{ $log->propriedade }} · {{ $log->lancamento }}</p>
                            <p>{{ $log->detalhes_resumo }}</p>
                            <button
                                type="button"
                                class="btn btn-sm ff-audit-detail-button"
                                data-audit-detail-url="{{ route('auditoria.detalhes', $log->id) }}"
                                data-bs-toggle="modal"
                                data-bs-target="#auditDetailModal"
                            >
                                Ver detalhes
                            </button>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="ff-audit-pagination" data-ff-audit-pagination>
                <span data-ff-audit-page-info>Mostrando 0 registro(s)</span>
                <div>
                    <button type="button" class="btn" data-ff-audit-prev>Anterior</button>
                    <span data-ff-audit-current>1</span>
                    <button type="button" class="btn" data-ff-audit-next>Próximo</button>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="auditDetailModal" tabindex="-1" aria-labelledby="auditDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content ff-modal-content ff-audit-modal">
                <div class="modal-header modal-header-green">
                    <h5 class="modal-title" id="auditDetailModalLabel">
                        <i class="bi bi-shield-check me-2"></i>Detalhes da ação auditada
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="ff-audit-modal-loading" data-audit-loading>Carregando detalhes...</div>
                    <div class="ff-audit-modal-grid d-none" data-audit-content>
                        <div><span>Data e hora</span><strong data-audit-field="criado_em">-</strong></div>
                        <div><span>Usuário</span><strong data-audit-field="usuario_nome">-</strong><small data-audit-field="usuario_email">-</small></div>
                        <div><span>Perfil</span><strong data-audit-field="usuario_perfil">-</strong></div>
                        <div><span>Propriedade</span><strong data-audit-field="propriedade">-</strong></div>
                        <div><span>Lançamento</span><strong data-audit-field="lancamento">-</strong></div>
                        <div><span>Ação</span><strong data-audit-field="acao_legivel">-</strong><small data-audit-field="acao_tecnica">-</small></div>
                        <div><span>Onde</span><strong data-audit-field="onde">-</strong><small data-audit-field="tabela_tecnica">-</small></div>
                        <div><span>Tipo de despesa</span><strong data-audit-field="tipo_despesa">-</strong><small data-audit-field="categoria_despesa">-</small></div>
                        <div><span>Registro</span><strong data-audit-field="registro">-</strong></div>
                        <div><span>IP real</span><strong data-audit-field="ip_cliente">-</strong></div>
                        <div><span>IP proxy</span><strong data-audit-field="ip_proxy">-</strong></div>
                        <div><span>CF-Ray</span><strong data-audit-field="cf_ray">-</strong></div>
                        <div><span>Host</span><strong data-audit-field="host">-</strong></div>
                        <div><span>Rota</span><strong data-audit-field="rota">-</strong><small data-audit-field="metodo">-</small></div>
                        <div class="ff-audit-modal-full"><span>User agent</span><strong data-audit-field="user_agent">-</strong></div>
                        <div class="ff-audit-modal-full"><span>Detalhes completos</span><pre data-audit-field="detalhes">-</pre></div>
                    </div>
                    <div class="ff-audit-modal-error d-none" data-audit-error>Não foi possível carregar os detalhes da auditoria.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const records = document.querySelector('[data-ff-audit-records]');
            if (!records) {
                return;
            }

            const rows = Array.from(records.querySelectorAll('tbody [data-ff-audit-row]'));
            const cards = Array.from(records.querySelectorAll('[data-ff-audit-card-list] [data-ff-audit-row]'));
            const searchInput = records.querySelector('[data-ff-audit-search]');
            const pageSizeInput = records.querySelector('[data-ff-audit-page-size]');
            const pageInfo = records.querySelector('[data-ff-audit-page-info]');
            const currentLabel = records.querySelector('[data-ff-audit-current]');
            const prevButton = records.querySelector('[data-ff-audit-prev]');
            const nextButton = records.querySelector('[data-ff-audit-next]');
            let page = 1;

            const visibleItems = () => rows.filter((row) => row.dataset.filtered !== '1');

            const render = () => {
                const pageSize = Number(pageSizeInput?.value || 25);
                const visible = visibleItems();
                const totalPages = Math.max(1, Math.ceil(visible.length / pageSize));
                page = Math.min(page, totalPages);
                const start = (page - 1) * pageSize;
                const end = start + pageSize;
                const visibleIds = new Set(visible.slice(start, end).map((row) => row.dataset.search + rows.indexOf(row)));

                rows.forEach((row) => {
                    const key = row.dataset.search + rows.indexOf(row);
                    row.hidden = row.dataset.filtered === '1' || !visibleIds.has(key);
                });

                cards.forEach((card, index) => {
                    const row = rows[index];
                    const key = row?.dataset.search + index;
                    card.hidden = !row || row.dataset.filtered === '1' || !visibleIds.has(key);
                });

                if (pageInfo) {
                    const first = visible.length === 0 ? 0 : start + 1;
                    const last = Math.min(end, visible.length);
                    pageInfo.textContent = `Mostrando ${first} a ${last} de ${visible.length} registro(s)`;
                }

                if (currentLabel) {
                    currentLabel.textContent = String(page);
                }

                if (prevButton) {
                    prevButton.disabled = page <= 1;
                }

                if (nextButton) {
                    nextButton.disabled = page >= totalPages;
                }
            };

            const filter = () => {
                const term = (searchInput?.value || '').trim().toLowerCase();
                rows.forEach((row, index) => {
                    const card = cards[index];
                    const found = term === '' || (row.dataset.search || '').includes(term);
                    row.dataset.filtered = found ? '0' : '1';
                    if (card) {
                        card.dataset.filtered = row.dataset.filtered;
                    }
                });
                page = 1;
                render();
            };

            searchInput?.addEventListener('input', filter);
            pageSizeInput?.addEventListener('change', () => {
                page = 1;
                render();
            });
            prevButton?.addEventListener('click', () => {
                page = Math.max(1, page - 1);
                render();
            });
            nextButton?.addEventListener('click', () => {
                page += 1;
                render();
            });
            render();

            const modal = document.getElementById('auditDetailModal');
            if (!modal) {
                return;
            }

            const loading = modal.querySelector('[data-audit-loading]');
            const content = modal.querySelector('[data-audit-content]');
            const error = modal.querySelector('[data-audit-error]');
            const fields = Array.from(modal.querySelectorAll('[data-audit-field]'));

            modal.addEventListener('show.bs.modal', async (event) => {
                const button = event.relatedTarget;
                const url = button?.getAttribute('data-audit-detail-url');
                loading?.classList.remove('d-none');
                content?.classList.add('d-none');
                error?.classList.add('d-none');

                if (!url) {
                    loading?.classList.add('d-none');
                    error?.classList.remove('d-none');
                    return;
                }

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Falha ao carregar auditoria.');
                    }

                    const detail = await response.json();
                    fields.forEach((field) => {
                        const key = field.getAttribute('data-audit-field');
                        field.textContent = detail[key] || '-';
                    });

                    loading?.classList.add('d-none');
                    content?.classList.remove('d-none');
                } catch (exception) {
                    loading?.classList.add('d-none');
                    error?.classList.remove('d-none');
                }
            });
        })();
    </script>
@endpush

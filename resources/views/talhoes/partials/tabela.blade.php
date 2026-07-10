<div class="ff-talhao-table-tools">
    <span class="visually-hidden">Filtros</span>
    <div>
        Exibir
        <select aria-label="Resultados por página">
            <option selected>25</option>
            <option>50</option>
            <option>100</option>
        </select>
        resultados por página
    </div>
    <form method="GET" action="{{ route('talhoes.index') }}" class="ff-talhao-search">
        <input type="hidden" name="status" value="{{ $filtros['status'] ?? 'ativos' }}">
        <label for="talhaoSearch">Pesquisar</label>
        <input id="talhaoSearch" name="search" value="{{ $filtros['search'] ?? '' }}" placeholder="Buscar registros">
    </form>
</div>

<div class="table-wrap ff-talhao-table-wrap">
    <table class="ff-talhao-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Área (ha)</th>
                <th>Geometria</th>
                <th>Centroide</th>
                <th>Despesas</th>
                <th>Total Gasto</th>
                <th>Custo/ha</th>
                <th>Descrição</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        <strong>{{ $row->nome }}</strong>
                        @if ($row->kml_nome !== '-')
                            <br><span class="muted">{{ $row->kml_nome }}</span>
                        @endif
                    </td>
                    <td><strong>{{ \App\Support\FarmFormat::decimal($row->area_raw, 2) }}</strong></td>
                    <td><span class="pill">{{ strtolower($row->geometria) }}</span></td>
                    <td>
                        @if ($row->centroide !== '-')
                            <a href="{{ route('talhoes.mapa') }}" class="ff-talhao-centroid">{{ $row->centroide }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $row->qtd_despesas }}</td>
                    <td><strong>{{ $row->total_despesas }}</strong></td>
                    <td><strong>{{ $row->custo_ha }}</strong></td>
                    <td>{{ $row->descricao }}</td>
                    <td>
                        <div class="ff-talhao-row-actions">
                            <div class="dropdown">
                                <button class="btn btn-xs btn-success-outline dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Exportar talhão">
                                    <i class="bi bi-download"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('talhoes.exportar-talhao', ['talhao' => $row->id, 'formato' => 'kml']) }}">KML</a>
                                    <a class="dropdown-item" href="{{ route('talhoes.exportar-talhao', ['talhao' => $row->id, 'formato' => 'kmz']) }}">KMZ</a>
                                    <a class="dropdown-item" href="{{ route('talhoes.exportar-talhao', ['talhao' => $row->id, 'formato' => 'shp']) }}">SHP</a>
                                </div>
                            </div>
                            <a class="btn btn-xs btn-primary" href="{{ route('talhoes.edit', $row->id) }}" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('talhoes.toggle-status', $row->id) }}">
                                @csrf
                                <button class="btn btn-xs btn-warning" type="submit" title="{{ $row->ativo ? 'Desativar' : 'Reativar' }}">
                                    <i class="bi bi-archive"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Nenhum talhão encontrado.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="ff-talhao-table-footer">
    <span>Mostrando de {{ $rows->isEmpty() ? 0 : 1 }} até {{ $rows->count() }} de {{ $rows->count() }} registros</span>
    <div class="ff-talhao-pagination">
        <button class="btn" type="button" disabled>Anterior</button>
        <button class="btn primary" type="button">1</button>
        <button class="btn" type="button" disabled>Próximo</button>
    </div>
</div>

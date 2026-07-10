@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <a class="btn" href="{{ route('relatorios.index') }}">Relatórios</a>
    </div>

    <section class="panel">
        <form method="GET" action="{{ route('relatorios.safra') }}" class="form-grid panel-body">
            <label>
                Safra
                <select name="safra_id">
                    @foreach ($safras as $item)
                        <option value="{{ $item->id }}" @selected($safraId == $item->id)>{{ $item->descricao }}</option>
                    @endforeach
                </select>
            </label>
            <div class="form-actions"><button class="btn primary" type="submit">Filtrar</button></div>
        </form>
    </section>

    @if ($safra)
        <section class="panel">
            <div class="panel-body">
                <strong>{{ $safra->descricao }}</strong>
                <p class="subtitle">Cultura: {{ $safra->cultura_nome ?: '-' }} | Período: {{ \Illuminate\Support\Carbon::parse($safra->data_inicio)->format('d/m/Y') }} a {{ $safra->data_fim ? \Illuminate\Support\Carbon::parse($safra->data_fim)->format('d/m/Y') : '-' }} | Status: {{ $safra->status }}</p>
            </div>
        </section>
    @endif

    @include('partials.stats', ['cards' => $cards])

    <section class="panel">
        <div class="panel-head"><h2>Despesas por mês</h2></div>
        @include('partials.data-table', ['columns' => ['mes' => 'Mês', 'total' => 'Total'], 'rows' => $mensal])
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Despesas por categoria</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Categoria</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse ($categorias as $categoria)
                        <tr>
                            <td><span class="color-chip" style="background: {{ $categoria->cor }}"></span>{{ $categoria->nome }}</td>
                            <td><strong>R$ {{ number_format($categoria->total, 2, ',', '.') }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">Sem despesas para a safra selecionada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

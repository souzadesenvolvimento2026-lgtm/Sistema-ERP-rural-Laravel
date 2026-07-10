@extends('layouts.farmfort', ['title' => 'FarmFort - Relatórios'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Indicadores e relatórios</h1>
            <p class="subtitle">Análises financeiras, safra, talhões, DRE, fluxo de caixa e comparativos.</p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head"><h2>Relatórios disponíveis</h2></div>
        <div class="panel-body">
            <div class="actions" style="justify-content:flex-start">
                <a class="btn primary" href="{{ route('relatorios.dre') }}">DRE</a>
                <a class="btn" href="{{ route('relatorios.fluxo-caixa') }}">Fluxo de Caixa</a>
                <a class="btn" href="{{ route('relatorios.orcado-realizado') }}">Orçado x Realizado</a>
                <a class="btn" href="{{ route('relatorios.categorias') }}">Categorias</a>
                <a class="btn" href="{{ route('relatorios.safra') }}">Safra</a>
                <a class="btn" href="{{ route('relatorios.talhao') }}">Talhão</a>
                <a class="btn" href="{{ route('relatorios.kpis') }}">KPIs / ROI</a>
                <a class="btn" href="{{ route('relatorios.comparativo-safras.index') }}">Comparativo de Safras</a>
            </div>
        </div>
    </section>
@endsection

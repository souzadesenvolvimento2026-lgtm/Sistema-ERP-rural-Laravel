@extends('layouts.farmfort', ['title' => 'FarmFort - Auditoria'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Auditoria</h1>
            <p class="subtitle">Consulta dos registros de ações realizadas na propriedade atual.</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="{{ route('auditoria.exportar', request()->query()) }}">Exportar CSV</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])

    <section class="panel">
        <div class="panel-head"><h2>Filtros</h2></div>
        <div class="panel-body">
            <form method="get" action="{{ route('auditoria.index') }}" class="form-grid">
                <div class="field">
                    <label>Usuário</label>
                    <select name="usuario_id">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" @selected($filtros['usuario_id'] === (int)$usuario->id)>{{ $usuario->nome }} - {{ $usuario->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Ação</label>
                    <select name="acao">
                        <option value="">Todas</option>
                        @foreach ($acoes as $acao)
                            <option value="{{ $acao }}" @selected($filtros['acao'] === $acao)>{{ $acao }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Tabela</label>
                    <select name="tabela">
                        <option value="">Todas</option>
                        @foreach ($tabelas as $tabela)
                            <option value="{{ $tabela }}" @selected($filtros['tabela'] === $tabela)>{{ $tabela }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Area</label>
                    <select name="lancamento">
                        <option value="">Todas</option>
                        @foreach ($lancamentos as $valor => $label)
                            <option value="{{ $valor }}" @selected($filtros['lancamento'] === $valor)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Tipo da despesa</label>
                    <select name="tipo_despesa">
                        <option value="">Todos</option>
                        @foreach ($tiposDespesa as $tipo)
                            <option value="{{ $tipo }}" @selected($filtros['tipo_despesa'] === $tipo)>{{ ucfirst($tipo) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Inicio</label>
                    <input type="date" name="inicio" value="{{ $filtros['inicio'] }}">
                </div>
                <div class="field">
                    <label>Fim</label>
                    <input type="date" name="fim" value="{{ $filtros['fim'] }}">
                </div>
                <div class="field wide">
                    <label>Busca</label>
                    <input name="busca" value="{{ $filtros['busca'] }}" placeholder="Nome, e-mail, detalhe ou ação">
                </div>
                <div class="field actions">
                    <button class="btn primary" type="submit">Filtrar</button>
                    <a class="btn" href="{{ route('auditoria.index') }}">Limpar</a>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Registros</h2></div>
        @include('partials.data-table', ['columns' => $columns, 'rows' => $logs])
    </section>
@endsection

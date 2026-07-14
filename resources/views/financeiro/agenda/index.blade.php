@extends('layouts.farmfort', ['title' => 'FarmFort - Agenda financeira'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Agenda financeira</h1>
            <p class="subtitle">Pagamentos e recebimentos pendentes da propriedade atual.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('financeiro.agenda.index') }}">Todos</a>
            <a class="btn" href="{{ route('financeiro.agenda.index', ['fp' => 'boleto']) }}">Boletos</a>
            <a class="btn" href="{{ route('financeiro.agenda.index', ['alerta' => 'boletos_vencendo']) }}">Boletos vencendo</a>
            <a class="btn" href="{{ route('financeiro.contas.index', [], false) }}">Contas bancarias</a>
        </div>
    </div>

    @include('financeiro.agenda.partials.resumo')

    @if (($filtros['alerta'] ?? '') === 'boletos_vencendo')
        <section class="panel">
            <strong>Filtrando boletos que geraram o alerta.</strong>
        </section>
    @elseif (($filtros['forma_pagamento'] ?? '') === 'boleto')
        <section class="panel">
            <strong>Filtrando boletos pendentes.</strong>
        </section>
    @endif

    @if ($totais['boletos'] > 0)
        <section class="panel">
            <strong>Atencao: existem boletos proximos do vencimento.</strong>
        </section>
    @endif

    @include('financeiro.agenda.partials.eventos')
@endsection

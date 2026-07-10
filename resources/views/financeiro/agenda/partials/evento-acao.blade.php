@if ($evento->origem === 'receita')
    <form method="POST" action="{{ route('financeiro.agenda.receber') }}" class="inline-form">
        @csrf
        <input type="hidden" name="id" value="{{ $evento->id }}">
        <input type="date" name="data_recebimento" value="{{ now()->format('Y-m-d') }}">
        <select name="conta_id">
            <option value="">Conta</option>
            @foreach ($contas as $conta)
                <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Receber</button>
    </form>
@else
    <form method="POST" action="{{ route('financeiro.agenda.pagar') }}" class="inline-form">
        @csrf
        <input type="hidden" name="id" value="{{ $evento->id }}">
        <input type="date" name="data_pagamento" value="{{ now()->format('Y-m-d') }}">
        <select name="conta_id">
            <option value="">Conta</option>
            @foreach ($contas as $conta)
                <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Pagar</button>
    </form>
@endif

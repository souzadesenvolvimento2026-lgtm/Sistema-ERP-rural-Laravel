@if ($evento->can_execute)
    <form method="POST" action="{{ $evento->action_route }}" class="inline-form">
        @csrf
        <input type="hidden" name="id" value="{{ $evento->id }}">
        <input type="date" name="{{ $evento->action_date_field }}" value="{{ $evento->action_date }}">
        <select name="conta_id">
            <option value="">Conta</option>
            @foreach ($contas as $conta)
                <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">{{ $evento->action_label }}</button>
    </form>
@endif

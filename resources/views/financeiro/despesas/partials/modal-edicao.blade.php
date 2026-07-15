@php
    $modalOnly = (bool) ($modalOnly ?? false);
    $voltarUrl = $voltarUrl ?? route('financeiro.index', [
        'filtro' => 'despesas',
        'todos' => 1,
        'lancamento_id' => $despesa,
    ]);
@endphp

<form
    method="post"
    action="{{ route('financeiro.despesas.update', $despesa) }}"
    class="modal-content ff-expense-edit-content"
    data-ff-expense-edit-form
>
    @csrf
    @method('PUT')

    <div class="modal-header modal-header-green ff-expense-edit-header">
        <h5 class="modal-title" id="financeiroEditarDespesaModalLabel">
            <i class="bi bi-pencil-square"></i>
            Editar Despesa
            <span class="visually-hidden">Editar despesa</span>
        </h5>

        @if ($modalOnly)
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        @else
            <a class="btn-close" href="{{ $voltarUrl }}" aria-label="Fechar"></a>
        @endif
    </div>

    <div class="modal-body ff-expense-edit-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Confira os dados informados.</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('financeiro.lancamentos.partials.dados-principais', ['modoEdicaoDespesa' => true])
    </div>

    <div class="modal-footer ff-modal-footer-split ff-expense-edit-footer">
        @if ($modalOnly)
            <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
        @else
            <a class="btn" href="{{ $voltarUrl }}">Cancelar</a>
        @endif

        <button class="btn primary" type="submit">
            <i class="bi bi-save"></i>
            Salvar Alterações
        </button>
    </div>
</form>

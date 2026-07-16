@php
    $formMethod = $formMethod ?? 'POST';
    $submitLabel = $submitLabel ?? 'Salvar';
    $modalTitle = $modalTitle ?? 'Nova Safra';
@endphp

<form method="POST" action="{{ $formAction }}" class="modal-content ff-safra-modal-content" data-safra-form>
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="modal-header modal-header-green">
        <h5 class="modal-title">
            <i class="bi bi-calendar3 me-2"></i>{{ $modalTitle }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
    </div>

    <div class="modal-body">
        @include('safras.partials.form-fields')
    </div>

    <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn primary">
            <i class="bi bi-save"></i>{{ $submitLabel }}
        </button>
    </div>
</form>

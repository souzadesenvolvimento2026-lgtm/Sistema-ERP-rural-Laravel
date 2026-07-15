<div class="modal fade ff-patrimony-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="post" action="{{ $action }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            @if (($method ?? 'post') !== 'post')
                @method($method)
            @endif

            <div class="modal-header modal-header-green">
                <h5 class="modal-title">{{ $modalTitle }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                @include('patrimonio.partials.dados-patrimonio', [
                    'patrimonio' => $patrimonio,
                    'tipos' => $tipos,
                ])
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary">{{ $submitLabel }}</button>
            </div>
        </form>
    </div>
</div>

@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn active" href="{{ route('safras.index') }}"><i class="bi bi-calendar3"></i> Safra</a>
            <a class="btn" href="{{ route('orcamento.index') }}"><i class="bi bi-clipboard-data"></i> Planejamento</a>
            <a class="btn primary" href="{{ route('safras.create') }}" data-safra-create><i class="bi bi-plus-lg"></i> Nova Safra</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('safras.partials.filtros')
    @include('safras.partials.tabela')

    <div class="modal fade" id="safraModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered ff-safra-modal-dialog" data-safra-modal-content>
            @include('safras.partials.modal-content', [
                'safra' => null,
                'modalTitle' => 'Nova Safra',
                'formAction' => route('safras.store'),
                'formMethod' => 'POST',
                'submitLabel' => 'Salvar',
            ])
        </div>
    </div>
@endsection

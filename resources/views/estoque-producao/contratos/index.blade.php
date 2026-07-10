@extends('layouts.farmfort', ['title' => 'FarmFort - Contratos'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Contratos e Entregas</h1>
            <p class="subtitle">Contratos de compra, venda, depósito, armazenagem e suas entregas.</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="#form-contrato"><i class="bi bi-plus-lg"></i> Novo contrato</a>
            <a class="btn" href="#form-entrega-contrato"><i class="bi bi-truck"></i> Nova entrega</a>
        </div>
    </div>

    <section class="stats">
        <div class="stat"><span>Contratos</span><strong>{{ $totais['contratos'] }}</strong></div>
        <div class="stat"><span>Abertos/parciais</span><strong>{{ $totais['abertos'] }}</strong></div>
        <div class="stat"><span>Valor contratado</span><strong>R$ {{ number_format($totais['valor_contratado'], 2, ',', '.') }}</strong></div>
        <div class="stat"><span>Valor entregue</span><strong>R$ {{ number_format($totais['valor_entregue'], 2, ',', '.') }}</strong></div>
    </section>

    @include('estoque-producao.contratos.partials.form-contrato')
    @include('estoque-producao.contratos.partials.form-entrega')
    @include('estoque-producao.contratos.partials.tabela')
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const contratoSelect = document.querySelector('[data-contrato-entrega-select]');
            const unidadeInput = document.querySelector('[data-contrato-entrega-unidade]');
            const entregaForm = document.getElementById('form-entrega-contrato');

            if (!contratoSelect || !unidadeInput || !entregaForm) return;

            document.querySelectorAll('[data-contrato-entrega]').forEach((button) => {
                button.addEventListener('click', () => {
                    contratoSelect.value = button.dataset.contratoId || '';
                    unidadeInput.value = button.dataset.unidade || 'sc';
                    entregaForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    contratoSelect.focus();
                });
            });

            contratoSelect.addEventListener('change', () => {
                const option = contratoSelect.selectedOptions[0];
                if (option?.dataset.unidade) {
                    unidadeInput.value = option.dataset.unidade;
                }
            });
        });
    </script>
@endpush

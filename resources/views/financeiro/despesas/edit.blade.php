@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Despesa'])

@section('content')
    <div class="ff-expense-edit-standalone">
        <div class="ff-expense-edit-context" aria-hidden="true">
            <div class="page-head">
                <div>
                    <h1>Lançamentos financeiros</h1>
                    <p class="subtitle">Edição de despesa aberta em janela flutuante.</p>
                </div>
                <a class="btn" href="{{ route('financeiro.index', ['filtro' => 'despesas', 'todos' => 1, 'lancamento_id' => $despesa]) }}">Voltar</a>
            </div>

            <section class="panel ff-expense-edit-context-panel">
                <div class="panel-head">
                    <h2>Lançamentos</h2>
                </div>
                <div class="panel-body">
                    <p class="subtitle mb-0">Atualize os dados da despesa sem sair do fluxo financeiro.</p>
                </div>
            </section>
        </div>

        <div class="modal-backdrop fade show"></div>
        <div class="modal d-block ff-expense-edit-page-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="financeiroEditarDespesaModalLabel">
            <div class="modal-dialog modal-dialog-centered ff-expense-edit-dialog">
                @include('financeiro.despesas.partials.modal-edicao', [
                    'voltarUrl' => route('financeiro.index', ['filtro' => 'despesas', 'todos' => 1, 'lancamento_id' => $despesa]),
                ])
            </div>
        </div>
    </div>
@endsection

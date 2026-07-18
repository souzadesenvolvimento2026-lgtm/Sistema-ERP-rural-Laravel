@extends('layouts.farmfort', ['title' => 'FarmFort - Fornecedores'])

@section('content')
    <div class="ff-purchase-page">
        @include('compras.partials.tabs', ['activeCompraTab' => 'fornecedores'])

        @include('partials.filter-panel', [
            'action' => route('compras.fornecedores.index'),
            'clearUrl' => route('compras.fornecedores.index'),
            'fields' => [
                [
                    'type' => 'select',
                    'name' => 'status',
                    'label' => 'Status',
                    'value' => $filters['status'] ?? 'ativos',
                    'options' => $statusOptions,
                    'columns' => 3,
                ],
                [
                    'type' => 'search',
                    'name' => 'search',
                    'label' => 'Buscar',
                    'value' => $filters['search'] ?? '',
                    'placeholder' => 'Nome, CNPJ/CPF, e-mail ou telefone',
                    'columns' => 8,
                ],
            ],
            'actionsColumns' => 3,
        ])

        <section class="stats ff-purchase-summary-cards" aria-label="Resumo de fornecedores">
            <div class="stat success">
                <span>Fornecedores ativos</span>
                <strong>{{ $totais['ativos'] }}</strong>
            </div>
            <div class="stat">
                <span>Total listado</span>
                <strong>{{ $totais['total'] }}</strong>
            </div>
        </section>

        <section class="panel ff-supplier-form-panel">
            <div class="panel-head">
                <h2><i class="bi bi-building me-2"></i>Novo fornecedor</h2>
            </div>

            <form method="post" action="{{ route('compras.fornecedores.store') }}">
                @csrf

                <div class="ff-supplier-form-grid">
                    <label class="ff-supplier-field ff-supplier-field-wide">
                        <span>Nome *</span>
                        <input name="nome" value="{{ old('nome') }}" required maxlength="160">
                    </label>

                    <label class="ff-supplier-field">
                        <span>CNPJ/CPF</span>
                        <input name="documento" value="{{ old('documento') }}" maxlength="20" inputmode="numeric">
                    </label>

                    <label class="ff-supplier-field">
                        <span>Telefone</span>
                        <input name="telefone" value="{{ old('telefone') }}" maxlength="30">
                    </label>

                    <label class="ff-supplier-field ff-supplier-field-wide">
                        <span>E-mail</span>
                        <input type="email" name="email" value="{{ old('email') }}" maxlength="160">
                    </label>

                    <label class="ff-supplier-field ff-supplier-field-full">
                        <span>Observações</span>
                        <textarea name="observacoes" maxlength="1000">{{ old('observacoes') }}</textarea>
                    </label>
                </div>

                <div class="ff-supplier-form-actions">
                    <button class="btn primary" type="submit">
                        <i class="bi bi-check2-square"></i> Cadastrar fornecedor
                    </button>
                </div>
            </form>
        </section>

        <section class="panel ff-purchase-orders-panel ff-suppliers-panel">
            <div class="panel-head">
                <h2><i class="bi bi-list-ul me-2"></i>Fornecedores cadastrados</h2>
            </div>

            <div class="table-wrap ff-suppliers-table-wrap">
                <table class="ff-suppliers-table">
                    <thead>
                    <tr>
                        <th>Fornecedor</th>
                        <th>CNPJ/CPF</th>
                        <th>Telefone</th>
                        <th>E-mail</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($fornecedores as $fornecedor)
                        <tr>
                            <td>
                                <strong>{{ $fornecedor->nome }}</strong>
                                <small>Cadastrado em {{ \App\Support\FarmFormat::date($fornecedor->created_at ?? null) }}</small>
                            </td>
                            <td>{{ $fornecedor->documento_formatado ?: '-' }}</td>
                            <td>{{ $fornecedor->telefone }}</td>
                            <td>{{ $fornecedor->email }}</td>
                            <td>
                                <span class="pill {{ $fornecedor->status_tone }}">{{ $fornecedor->status_label }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">Nenhum fornecedor encontrado para o filtro selecionado.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

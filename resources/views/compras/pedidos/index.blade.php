@extends('layouts.farmfort', ['title' => 'FarmFort - Pedidos Fiscais'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Pedidos Fiscais</h1>
            <p class="subtitle">Cadastre o pedido, adicione itens, vincule notas e aprove quando estiver conferido.</p>
            <span class="visually-hidden">Pedidos de compras</span>
        </div>
        <a class="btn primary" href="{{ route('compras.pedidos.create') }}"><i class="bi bi-plus-lg"></i> Novo Pedido</a>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div>
                <span class="text-muted small fw-bold">PEDIDOS</span>
                <h2 class="mb-0">Novo Pedido Fiscal</h2>
                <p class="subtitle mb-0">Cadastre o pedido, adicione itens, vincule notas e aprove quando estiver conferido.</p>
            </div>
            <a class="btn primary" href="{{ route('compras.pedidos.create') }}"><i class="bi bi-plus-lg"></i> Novo Pedido</a>
        </div>
    </section>

    <section class="stats" aria-label="Resumo dos pedidos">
        <div class="stat"><span>Pedidos</span><strong>{{ $totais['pedidos'] }}</strong></div>
        <div class="stat warning"><span>Pendentes</span><strong>{{ $totais['pendentes'] }}</strong></div>
        <div class="stat success"><span>Aprovados/Baixados</span><strong>{{ $totais['aprovados'] }}</strong></div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2><i class="bi bi-clipboard-check me-2"></i>Pedidos Fiscais</h2>
            <span class="badge">{{ $totais['pedidos'] }} pedido(s)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Número</th>
                        <th>Fornecedor</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pedidos as $pedido)
                        <tr>
                            <td>{{ \App\Support\FarmFormat::date($pedido->issue_date) }}</td>
                            <td><strong>{{ $pedido->order_number }}</strong></td>
                            <td>
                                {{ $pedido->supplier_name ?: '-' }}
                                <small class="d-block text-muted">{{ $pedido->supplier_cnpj ?: '-' }}</small>
                            </td>
                            <td><strong>{{ \App\Support\FarmFormat::money($pedido->total_value) }}</strong></td>
                            <td><span class="pill {{ $pedido->status_tone }}">{{ $pedido->status_label }}</span></td>
                            <td>
                                <div class="actions" style="justify-content:flex-start">
                                    <a class="btn btn-sm" href="{{ route('compras.pedidos.show', $pedido->id) }}">Abrir</a>
                                    @if ($pedido->can_approve)
                                        <form method="post" action="{{ route('compras.pedidos.approve', $pedido->id) }}" onsubmit="return confirm('Aprovar este pedido e lançar no financeiro/estoque?')">
                                            @csrf
                                            <input type="hidden" name="confirmar_aprovacao" value="1">
                                            <button class="btn btn-sm primary" type="submit">Aprovar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Nenhum pedido encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

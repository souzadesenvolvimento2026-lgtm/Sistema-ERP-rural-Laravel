<section class="panel">
    <div class="panel-head"><h2>Agenda de pagamentos e recebimentos</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th>Pessoa</th>
                    <th>Categoria</th>
                    <th>Forma</th>
                    <th>Conta</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($eventos as $evento)
                    <tr>
                        <td>{{ $evento->data_evento ? \Illuminate\Support\Carbon::parse($evento->data_evento)->format('d/m/Y') : '-' }}</td>
                        <td><span class="pill {{ $evento->origem === 'receita' ? 'success' : 'danger' }}">{{ $evento->origem === 'receita' ? 'Receber' : 'Pagar' }}</span></td>
                        <td><strong>{{ $evento->titulo }}</strong></td>
                        <td>{{ $evento->pessoa ?: '-' }}</td>
                        <td>{{ $evento->categoria ?: '-' }}</td>
                        <td>{{ $evento->forma_pagamento ?: '-' }}</td>
                        <td>{{ $evento->conta_nome ?: '-' }}</td>
                        <td><strong>R$ {{ number_format($evento->valor, 2, ',', '.') }}</strong></td>
                        <td><span class="pill {{ in_array($evento->status, ['pago', 'recebido'], true) ? 'success' : 'warning' }}">{{ \App\Support\FarmFormat::statusLabel($evento->status) }}</span></td>
                        <td>
                            @include('financeiro.agenda.partials.evento-acao', ['evento' => $evento])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Nenhum pagamento ou recebimento pendente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

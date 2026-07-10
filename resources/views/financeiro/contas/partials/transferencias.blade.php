<section class="panel">
    <div class="panel-head"><h2>Ultimas transferencias</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Valor</th>
                    <th>Descricao</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transferencias as $transferencia)
                    <tr>
                        <td>{{ \App\Support\FarmFormat::date($transferencia->data_transferencia) }}</td>
                        <td>{{ $transferencia->origem_nome }}</td>
                        <td>{{ $transferencia->destino_nome }}</td>
                        <td><strong>R$ {{ number_format($transferencia->valor, 2, ',', '.') }}</strong></td>
                        <td>{{ $transferencia->descricao ?: '-' }}</td>
                        <td>{{ $transferencia->usuario_nome ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Nenhuma transferencia registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

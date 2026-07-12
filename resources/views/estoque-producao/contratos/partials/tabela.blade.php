<section class="panel">
    <div class="panel-head"><h2>Contratos de produção</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Número</th>
                    <th>Tipo</th>
                    <th>Contraparte</th>
                    <th>Produto</th>
                    <th>Contratado</th>
                    <th>Entregue</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contratos as $contrato)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($contrato->data_contrato)->format('d/m/Y') }}</td>
                        <td><strong>{{ $contrato->numero }}</strong><br><span class="muted">{{ $contrato->safra_nome ?: '-' }}</span></td>
                        <td>{{ $tipos[$contrato->tipo] ?? $contrato->tipo }}</td>
                        <td>{{ $contrato->contraparte ?: '-' }}</td>
                        <td>{{ $contrato->produto ?: '-' }}</td>
                        <td>{{ number_format($contrato->quantidade, 2, ',', '.') }} {{ $contrato->unidade }}</td>
                        <td>
                            <strong>{{ number_format($contrato->entregue, 2, ',', '.') }} {{ $contrato->unidade }}</strong>
                            <div class="progress-line">
                                <span style="width: {{ $contrato->percentual_entregue }}%; background: {{ $contrato->percentual_entregue >= 100 ? '#35c49a' : '#f6c34a' }}"></span>
                            </div>
                            <span class="muted">{{ number_format($contrato->percentual_entregue, 1, ',', '.') }}%</span>
                        </td>
                        <td>R$ {{ number_format($contrato->valor_total, 2, ',', '.') }}</td>
                        <td>{{ $contrato->status }}</td>
                        <td>
                            @if ($contrato->permite_entrega)
                                <button
                                    class="btn primary"
                                    type="button"
                                    data-contrato-entrega
                                    data-contrato-id="{{ $contrato->id }}"
                                    data-unidade="{{ $contrato->unidade }}"
                                >Entrega</button>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Nenhum contrato cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

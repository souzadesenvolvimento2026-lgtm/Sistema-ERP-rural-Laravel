<section class="panel ff-stock-history-panel">
    <div class="panel-head">
        <h2>
            <i class="bi bi-box-arrow-up-right me-2"></i>
            Últimas baixas do estoque
        </h2>
        <span class="badge">{{ $saidasRecentes->count() }} baixa(s)</span>
    </div>

    <div class="ff-stock-history-wrap">
        <table class="ff-stock-history-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Destino</th>
                    <th>Quantidade</th>
                    <th>Valor estimado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($saidasRecentes as $saida)
                    @php
                        $vinculos = array_filter([
                            $saida->safra !== '-' ? 'Safra: '.$saida->safra : null,
                            $saida->talhao !== '-' ? 'Talhão: '.$saida->talhao : null,
                            $saida->patrimonio !== '-' ? 'Patrimônio: '.$saida->patrimonio : null,
                        ]);
                    @endphp
                    <tr>
                        <td>{{ $saida->data }}</td>
                        <td>
                            <strong>{{ $saida->produto }}</strong>
                            @if ($saida->observacoes !== '-')
                                <br>
                                <span class="muted">{{ $saida->observacoes }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="pill success">{{ $saida->destino }}</span>
                            @if ($vinculos !== [])
                                <div class="ff-stock-destination-tags">
                                    @foreach ($vinculos as $vinculo)
                                        <span>{{ $vinculo }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td><strong>{{ $saida->quantidade }}</strong></td>
                        <td>{{ $saida->valor }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">
                            Nenhuma baixa de estoque registrada para esta propriedade.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

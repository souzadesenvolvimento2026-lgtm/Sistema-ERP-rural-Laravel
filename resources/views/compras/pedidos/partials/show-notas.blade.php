<section class="panel">
    <div class="panel-head">
        <h2>Notas fiscais vinculadas</h2>
        <span class="badge">{{ $linkedInvoiceCount }} nota(s)</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Emissão</th>
                    <th>Fornecedor</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Vínculo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linkedInvoices as $nota)
                    <tr>
                        <td><strong>{{ $nota->invoice_number ?: '-' }}</strong>{{ $nota->series ? ' / Série '.$nota->series : '' }}</td>
                        <td>{{ $nota->issue_date ? \Illuminate\Support\Carbon::parse($nota->issue_date)->format('d/m/Y') : '-' }}</td>
                        <td>{{ $nota->issuer_name ?: '-' }}<br><span class="muted">{{ $nota->issuer_cnpj ?: '-' }}</span></td>
                        <td><strong>R$ {{ number_format((float)$nota->total_value, 2, ',', '.') }}</strong></td>
                        <td><span class="pill {{ $nota->status_tone }}">{{ $nota->status_label }}</span></td>
                        <td>{{ $nota->match_status ?: '-' }}</td>
                        <td>
                            @if ($nota->can_unlink_invoice)
                                <form method="post" action="{{ route('compras.pedidos.notas.unlink', ['pedido' => $order->id, 'nota' => $nota->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit">Remover</button>
                                </form>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Nenhuma nota fiscal vinculada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($invoiceComparison)
        <div class="stats" style="margin-top:14px">
            <div class="stat"><span>Itens conferidos</span><strong>{{ $invoiceComparison['match_count'] }}</strong></div>
            <div class="stat"><span>Divergências</span><strong>{{ $invoiceComparison['divergence_count'] }}</strong></div>
            <div class="stat"><span>Faltam na nota</span><strong>{{ $invoiceComparison['missing_in_invoice_count'] }}</strong></div>
            <div class="stat"><span>Sobram na nota</span><strong>{{ $invoiceComparison['missing_in_order_count'] }}</strong></div>
        </div>

        @if ($invoiceComparison['has_divergences'])
            <div class="panel-body">
                @if ($invoiceComparison['divergences'])
                    <h3>Itens divergentes</h3>
                    <ul>
                        @foreach ($invoiceComparison['divergences'] as $item)
                            <li><strong>{{ $item['order']['description'] }}</strong>: {{ implode(', ', $item['issues']) }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($invoiceComparison['missing_in_invoice'])
                    <h3>Não encontrados na nota</h3>
                    <ul>
                        @foreach ($invoiceComparison['missing_in_invoice'] as $item)
                            <li>{{ $item['description'] }} - {{ number_format($item['quantity'], 4, ',', '.') }} {{ $item['unit'] }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($invoiceComparison['missing_in_order'])
                    <h3>Não encontrados no pedido</h3>
                    <ul>
                        @foreach ($invoiceComparison['missing_in_order'] as $item)
                            <li>{{ $item['description'] }} - {{ number_format($item['quantity'], 4, ',', '.') }} {{ $item['unit'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    @endif

    @if ($invoiceLinkPreview)
        <div class="panel-body" id="comparacao-nf">
            <h3>Conferência da nota fiscal</h3>
            <p class="muted">
                NF {{ $previewInvoice['invoice_number'] ?? '-' }}{{ !empty($previewInvoice['series']) ? ' / Série '.$previewInvoice['series'] : '' }}
                - {{ $previewInvoice['issuer_name'] ?? '-' }}
                - R$ {{ number_format((float)($previewInvoice['total_value'] ?? 0), 2, ',', '.') }}
            </p>
            <div class="stats">
                <div class="stat"><span>Itens conferidos</span><strong>{{ $previewComparison['match_count'] ?? 0 }}</strong></div>
                <div class="stat"><span>Divergências</span><strong>{{ $previewComparison['divergence_count'] }}</strong></div>
                <div class="stat"><span>Faltam na nota</span><strong>{{ $previewComparison['missing_in_invoice_count'] }}</strong></div>
                <div class="stat"><span>Sobram na nota</span><strong>{{ $previewComparison['missing_in_order_count'] }}</strong></div>
            </div>

            @if (!empty($previewComparison['divergences']))
                <h3>Itens divergentes</h3>
                <ul>
                    @foreach ($previewComparison['divergences'] as $item)
                        <li><strong>{{ $item['order']['description'] }}</strong>: {{ implode(', ', $item['issues']) }}</li>
                    @endforeach
                </ul>
            @endif
            @if (!empty($previewComparison['missing_in_invoice']))
                <h3>Não encontrados na nota</h3>
                <ul>
                    @foreach ($previewComparison['missing_in_invoice'] as $item)
                        <li>{{ $item['description'] }} - {{ number_format($item['quantity'], 4, ',', '.') }} {{ $item['unit'] }}</li>
                    @endforeach
                </ul>
            @endif
            @if (!empty($previewComparison['missing_in_order']))
                <h3>Não encontrados no pedido</h3>
                <ul>
                    @foreach ($previewComparison['missing_in_order'] as $item)
                        <li>{{ $item['description'] }} - {{ number_format($item['quantity'], 4, ',', '.') }} {{ $item['unit'] }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="actions">
                <form method="post" action="{{ route('compras.pedidos.notas.preview.cancel', $order->id) }}">
                    @csrf
                    <button class="btn" type="submit">Cancelar comparação</button>
                </form>
                <form method="post" action="{{ route('compras.pedidos.notas.confirm', $order->id) }}">
                    @csrf
                    <button class="btn primary" type="submit" @disabled(! $order->can_confirm_invoice_link)>Confirmar vínculo</button>
                </form>
            </div>
        </div>
    @endif

    @if ($order->can_link_invoice)
        <form method="post" action="{{ route('compras.pedidos.notas.link', $order->id) }}" class="form-grid" style="margin-top:14px">
            @csrf
            <label class="field wide">
                <span>Selecionar nota fiscal existente</span>
                <select name="invoice_id" required>
                    <option value="">Selecione</option>
                    @foreach ($availableInvoices as $nota)
                        <option value="{{ $nota->id }}">
                            {{ $nota->invoice_number ?: 'Sem número' }}{{ $nota->series ? ' / Série '.$nota->series : '' }}
                            - {{ $nota->issuer_name ?: '-' }}
                            - R$ {{ number_format((float)$nota->total_value, 2, ',', '.') }}
                        </option>
                    @endforeach
                </select>
            </label>
            <div class="actions full">
                <button class="btn primary" type="submit" @disabled(! $hasAvailableInvoices)>Comparar nota</button>
                <a class="btn" href="{{ route('fiscal.notas.create') }}">Importar NF-e no fiscal</a>
            </div>
        </form>

        <form method="post" action="{{ route('compras.pedidos.notas.import', $order->id) }}" enctype="multipart/form-data" class="form-grid" style="margin-top:14px">
            @csrf
            <label class="field wide">
                <span>Importar XML para comparar com este pedido</span>
                <input type="file" name="xml" accept=".xml,text/xml,application/xml" required>
            </label>
            <div class="actions full">
                <button class="btn" type="submit">Processar XML e comparar</button>
            </div>
        </form>
    @endif
</section>

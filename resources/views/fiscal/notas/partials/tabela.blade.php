<section class="panel">
    <div class="panel-head">
        <h2>Notas fiscais importadas</h2>
        <span class="badge">{{ $rows->count() }} nota(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Numero/serie</th>
                    <th>Fornecedor</th>
                    <th>CNPJ</th>
                    <th>Emissao</th>
                    <th>Itens</th>
                    <th>Pedidos</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->number }}</td>
                        <td>{{ $row->issuer_name }}</td>
                        <td>{{ $row->issuer_cnpj }}</td>
                        <td>{{ $row->issue_date }}</td>
                        <td>{{ $row->item_count }}</td>
                        <td>{{ $row->linked_orders }}</td>
                        <td><strong>{{ $row->total }}</strong></td>
                        <td><span class="pill {{ $row->status_tone }}">{{ $row->status }}</span></td>
                        <td>
                            <div class="actions" style="justify-content:flex-start">
                                <a class="btn" href="{{ route('fiscal.notas.show', $row->id) }}">Detalhes</a>
                                @if ($row->tem_xml)
                                    <a class="btn" href="{{ route('fiscal.notas.xml', $row->id) }}">XML</a>
                                @endif
                                <a class="btn" href="{{ $row->consolidated_url }}">Consolidado</a>
                                @if ($row->can_approve)
                                    <form method="post" action="{{ route('fiscal.notas.approve', $row->id) }}">
                                        @csrf
                                        <button class="btn primary" type="submit">Aprovar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Nenhuma nota fiscal encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Registros fiscais aprovados</h2>
        <span class="badge">{{ $rows->count() }} registro(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Número/documento</th>
                    <th>Fornecedor/emitente</th>
                    <th>CNPJ</th>
                    <th>Data</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Origem/vínculo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><span class="pill {{ $row->type === 'pedido' ? '' : 'success' }}">{{ $row->type_label }}</span></td>
                        <td>{{ $row->document_number ?: '-' }}</td>
                        <td>{{ $row->supplier_name ?: '-' }}</td>
                        <td>{{ $row->supplier_cnpj ?: '-' }}</td>
                        <td>{{ $row->date }}</td>
                        <td><strong>{{ $row->total }}</strong></td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->origin }}</td>
                        <td><a class="btn" href="{{ $row->detail_url }}">Visualizar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Nenhum registro fiscal aprovado encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

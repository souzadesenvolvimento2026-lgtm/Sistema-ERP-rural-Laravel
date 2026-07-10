<section class="panel">
    <div class="panel-head">
        <h2>Historico de entradas</h2>
        <span class="badge">{{ $entradas->count() }} entrada(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Numero/serie</th>
                    <th>Fornecedor</th>
                    <th>CNPJ</th>
                    <th>Entrada</th>
                    <th>Categoria</th>
                    <th>Safra</th>
                    <th>Itens</th>
                    <th>Parcelas</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entradas as $entrada)
                    <tr>
                        <td><strong>{{ $entrada->numero }}</strong></td>
                        <td>{{ $entrada->fornecedor }}</td>
                        <td>{{ $entrada->fornecedor_doc }}</td>
                        <td>{{ $entrada->data_entrada }}</td>
                        <td>{{ $entrada->categoria }}</td>
                        <td>{{ $entrada->safra }}</td>
                        <td>{{ $entrada->itens }}</td>
                        <td>{{ $entrada->parcelas }}</td>
                        <td><strong>{{ $entrada->valor }}</strong></td>
                        <td><span class="pill {{ $entrada->status_key === 'aprovada' ? 'success' : '' }}">{{ $entrada->status }}</span></td>
                        <td><a class="btn sm" href="{{ route('fiscal.entrada-nf.show', ['entrada' => $entrada->id]) }}">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">Nenhuma entrada de NF encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

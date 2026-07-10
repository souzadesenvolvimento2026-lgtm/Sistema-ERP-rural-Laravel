<section class="panel">
    <div class="panel-head">
        <h2>Lancamentos</h2>
        <span class="badge">{{ $linhas->count() }} registro(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descricao</th>
                    <th>Pessoa</th>
                    <th>Categoria</th>
                    <th>Safra</th>
                    <th>Conta</th>
                    <th>Valor</th>
                    <th>Vencimento/Recebimento</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $linha)
                    <tr>
                        <td>{{ $linha->data }}</td>
                        <td><span class="pill {{ $linha->tipo === 'Receita' ? 'success' : ($linha->tipo === 'Despesa' ? 'danger' : '') }}">{{ $linha->tipo }}</span></td>
                        <td>{{ $linha->descricao }}</td>
                        <td>{{ $linha->pessoa }}</td>
                        <td>{{ $linha->categoria }}</td>
                        <td>{{ $linha->safra }}</td>
                        <td>{{ $linha->conta }}</td>
                        <td><strong>{{ $linha->valor }}</strong></td>
                        <td>{{ $linha->vencimento }}</td>
                        <td>{{ $linha->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Nenhum lancamento encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

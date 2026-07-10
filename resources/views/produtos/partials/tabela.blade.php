<section class="panel">
    <div class="panel-head">
        <h2>Produtos</h2>
        <span class="badge">{{ $rows->count() }} produto(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Codigos</th>
                    <th>Categoria</th>
                    <th>Unidade</th>
                    <th>Fiscal</th>
                    <th>Estoque</th>
                    <th>Entradas NF</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->descricao }}</strong>
                            @if ($row->marca !== '-')
                                <br><span class="muted">{{ $row->marca }}</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $row->codigo_interno }}</strong>
                            <br><span class="muted">Fornecedor: {{ $row->codigo_fornecedor }}</span>
                        </td>
                        <td>{{ $row->categoria }}</td>
                        <td>{{ $row->unidade }}</td>
                        <td>
                            <span class="pill {{ $row->fiscal_completo ? 'success' : 'warning' }}">{{ $row->fiscal_status }}</span>
                            <br><span class="muted">NCM {{ $row->ncm }}</span>
                        </td>
                        <td>
                            <strong>{{ $row->saldo_estoque }}</strong>
                            <br><span class="muted">{{ $row->valor_estoque }} em {{ $row->movimentos_estoque }} mov.</span>
                        </td>
                        <td>
                            <strong>{{ $row->quantidade_nf }}</strong>
                            <br><span class="muted">{{ $row->valor_nf }} em {{ $row->itens_nf }} item(ns)</span>
                        </td>
                        <td><span class="pill {{ $row->ativo ? 'success' : '' }}">{{ $row->status }}</span></td>
                        <td>
                            <div class="actions">
                                <a class="btn small" href="{{ route('produtos.edit', $row->id) }}">Editar</a>
                                <form method="post" action="{{ route('produtos.toggle-status', $row->id) }}">
                                    @csrf
                                    <button class="btn small" type="submit">{{ $row->ativo ? 'Inativar' : 'Ativar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Nenhum produto cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

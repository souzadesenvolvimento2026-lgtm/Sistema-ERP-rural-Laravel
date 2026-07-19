<section class="panel">
    <div class="panel-head">
        <h2>Produtos armazenados</h2>
        <span class="badge">{{ $rows->count() }} produto(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Unidade</th>
                    <th>Saldo atual</th>
                    <th>Custo médio</th>
                    <th>Valor total</th>
                    <th>Situação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->descricao }}</strong>
                            @if ($row->codigo_interno !== '-' || $row->codigo_fornecedor !== '-')
                                <br><span class="muted">Interno: {{ $row->codigo_interno }} | Fornecedor: {{ $row->codigo_fornecedor }}</span>
                            @endif
                            @if ($row->marca !== '-')
                                <br><span class="muted">Fabricante: {{ $row->marca }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $row->categoria }}
                            <br>
                            <span class="pill {{ $row->fiscal_completo ? 'success' : 'warning' }}">{{ $row->fiscal_status }}</span>
                            @if ($row->ncm !== '-')
                                <br><span class="muted">NCM: {{ $row->ncm }}</span>
                            @endif
                        </td>
                        <td>{{ $row->unidade }}</td>
                        <td>
                            <strong>{{ $row->saldo_estoque }}</strong>
                            <br><span class="muted">{{ $row->movimentos_estoque }} movimento(s)</span>
                        </td>
                        <td>{{ $row->custo_medio }}</td>
                        <td><strong>{{ $row->valor_estoque }}</strong></td>
                        <td>
                            <span class="pill {{ $row->situacao_tone }}">{{ $row->situacao }}</span>
                            <br><span class="muted">{{ $row->status }}</span>
                        </td>
                        <td>
                            <div class="actions">
                                @if ($row->ativo && $row->saldo_estoque_raw > 0)
                                    <button
                                        class="btn small primary"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#produtoBaixaModal"
                                        data-produto-action="{{ route('produtos.movimentos.store', $row->id) }}"
                                        data-produto-nome="{{ $row->descricao }}"
                                        data-produto-saldo="{{ $row->saldo_estoque }}"
                                        data-produto-unidade="{{ $row->unidade_codigo }}"
                                        aria-label="Dar baixa no estoque de {{ $row->descricao }}"
                                    >
                                        Dar baixa
                                    </button>
                                @endif
                                <a class="btn small" href="{{ route('produtos.edit', $row->id) }}" aria-label="Editar produto {{ $row->descricao }}">Editar</a>
                                <form method="post" action="{{ route('produtos.toggle-status', $row->id) }}">
                                    @csrf
                                    <button
                                        class="btn small {{ $row->ativo ? 'danger' : 'primary' }}"
                                        type="submit"
                                        aria-label="{{ $row->ativo ? 'Inativar' : 'Ativar' }} produto {{ $row->descricao }}"
                                    >
                                        {{ $row->ativo ? 'Inativar' : 'Ativar' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted">Nenhum produto encontrado para os filtros selecionados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

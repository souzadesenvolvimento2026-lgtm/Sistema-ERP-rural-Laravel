<section class="panel">
    <div class="panel-head">
        <h2>Receitas financeiras</h2>
        <span class="badge">{{ $rows->count() }} receita(s)</span>
    </div>
    @if ($rows->where('aprovacao_key', 'pendente')->where('status_key', '!=', 'recebido')->count() > 0)
        <div class="actions" style="justify-content:flex-start;margin-bottom:12px">
            <button class="btn primary" type="submit" form="receitas-aprovar-lote">Aprovar selecionadas</button>
            <span class="muted">Marque as receitas pendentes na tabela.</span>
        </div>
    @endif
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Selecionar</th>
                    <th>Venda</th>
                    <th>Descricao</th>
                    <th>Comprador</th>
                    <th>Categoria</th>
                    <th>Safra</th>
                    <th>Qtd.</th>
                    <th>Valor unit.</th>
                    <th>Total</th>
                    <th>Recebimento</th>
                    <th>Status</th>
                    <th>Aprovacao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            @if ($row->aprovacao_key === 'pendente' && $row->status_key !== 'recebido')
                                <input form="receitas-aprovar-lote" type="checkbox" name="receitas[]" value="{{ $row->id }}">
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td>{{ $row->data_venda }}</td>
                        <td>
                            <strong>{{ $row->descricao }}</strong>
                            @if ($row->produtor !== '-')
                                <br><span class="muted">{{ $row->produtor }}</span>
                            @endif
                        </td>
                        <td>{{ $row->comprador }}</td>
                        <td>{{ $row->categoria }}</td>
                        <td>{{ $row->safra }}</td>
                        <td>{{ $row->quantidade }}</td>
                        <td>{{ $row->preco_unitario }}</td>
                        <td><strong>{{ $row->valor }}</strong></td>
                        <td>{{ $row->recebimento }}</td>
                        <td><span class="pill {{ $row->status_key === 'recebido' ? 'success' : 'warning' }}">{{ $row->status }}</span></td>
                        <td>
                            <span class="pill {{ $row->aprovacao_key === 'aprovada' ? 'success' : ($row->aprovacao_key === 'reprovada' ? 'danger' : 'warning') }}">{{ $row->aprovacao }}</span>
                            @if ($row->motivo_reprovacao !== '-')
                                <br><span class="muted">{{ $row->motivo_reprovacao }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions" style="justify-content:flex-start">
                                <a class="btn" href="{{ route('financeiro.receitas.edit', $row->id) }}">Editar</a>
                                <a class="btn" href="{{ route('financeiro.receitas.duplicate', $row->id) }}">Duplicar</a>
                                @if ($row->aprovacao_key === 'pendente')
                                    <form method="post" action="{{ route('financeiro.receitas.approve', $row->id) }}">
                                        @csrf
                                        <button class="btn primary" type="submit">Aprovar</button>
                                    </form>
                                    <form method="post" action="{{ route('financeiro.receitas.reject', $row->id) }}" class="form-grid" style="grid-template-columns:minmax(140px,1fr) auto;gap:6px">
                                        @csrf
                                        <input name="motivo_reprovacao" maxlength="255" placeholder="Motivo">
                                        <button class="btn danger" type="submit">Reprovar</button>
                                    </form>
                                @endif
                                @if ($row->status_key === 'pendente' && $row->aprovacao_key === 'aprovada')
                                    <form method="post" action="{{ route('financeiro.receitas.receive', $row->id) }}">
                                        @csrf
                                        <input type="hidden" name="data_recebimento" value="{{ now()->toDateString() }}">
                                        <button class="btn" type="submit">Receber</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('financeiro.receitas.cancel', $row->id) }}">
                                    @csrf
                                    <button class="btn danger" type="submit">Cancelar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="muted">Nenhuma receita encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <form id="receitas-aprovar-lote" method="post" action="{{ route('financeiro.receitas.approve-batch') }}">
        @csrf
    </form>
</section>

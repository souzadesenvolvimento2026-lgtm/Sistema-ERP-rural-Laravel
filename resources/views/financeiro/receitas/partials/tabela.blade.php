<section class="panel">
    <div class="panel-head">
        <h2>Receitas financeiras</h2>
        <span class="badge">{{ $rows->count() }} receita(s)</span>
    </div>
    @if ($can_approve_batch)
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
                            @if ($row->can_select_for_batch)
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
                        <td><span class="pill {{ $row->status_tone }}">{{ $row->status }}</span></td>
                        <td>
                            <span class="pill {{ $row->aprovacao_tone }}">{{ $row->aprovacao }}</span>
                            @if ($row->motivo_reprovacao !== '-')
                                <br><span class="muted">{{ $row->motivo_reprovacao }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions" style="justify-content:flex-start">
                                <a class="btn" href="{{ route('financeiro.receitas.edit', $row->id) }}">Editar</a>
                                <a class="btn" href="{{ route('financeiro.receitas.duplicate', $row->id) }}">Duplicar</a>
                                @if ($row->can_approve)
                                    <form method="post" action="{{ route('financeiro.receitas.approve', $row->id) }}">
                                        @csrf
                                        <button class="btn primary" type="submit">Aprovar</button>
                                    </form>
                                @endif
                                @if ($row->can_reject)
                                    <form method="post" action="{{ route('financeiro.receitas.reject', $row->id) }}" class="form-grid" style="grid-template-columns:minmax(140px,1fr) auto;gap:6px">
                                        @csrf
                                        <input name="motivo_reprovacao" maxlength="255" placeholder="Motivo">
                                        <button class="btn danger" type="submit">Reprovar</button>
                                    </form>
                                @endif
                                @if ($row->can_receive)
                                    <form method="post" action="{{ route('financeiro.receitas.receive', $row->id) }}">
                                        @csrf
                                        <input type="hidden" name="data_recebimento" value="{{ now()->toDateString() }}">
                                        <button class="btn" type="submit">Receber</button>
                                    </form>
                                @endif
                                @if ($row->can_cancel)
                                    <form method="post" action="{{ route('financeiro.receitas.cancel', $row->id) }}">
                                        @csrf
                                        <button class="btn danger" type="submit">Cancelar</button>
                                    </form>
                                @endif
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

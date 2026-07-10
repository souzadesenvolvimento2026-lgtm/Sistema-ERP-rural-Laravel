<section class="panel">
    <div class="panel-head">
        <h2>Despesas financeiras</h2>
        <span class="badge">{{ $rows->count() }} despesa(s)</span>
    </div>
    @if ($rows->where('aprovacao_key', 'pendente')->where('status_key', '!=', 'pago')->count() > 0)
        <div class="actions" style="justify-content:flex-start;margin-bottom:12px">
            <button class="btn primary" type="submit" form="despesas-aprovar-lote">Aprovar selecionadas</button>
            <span class="muted">Marque as despesas pendentes na tabela.</span>
        </div>
    @endif
    <div class="table-wrap">
        <table id="despesas-table">
            <thead>
                <tr>
                    <th>Selecionar</th>
                    <th>Lancamento</th>
                    <th>Descricao</th>
                    <th>Fornecedor</th>
                    <th>Categoria</th>
                    <th>Safra/Talhao</th>
                    <th>Qtd.</th>
                    <th>Total</th>
                    <th>Vencimento</th>
                    <th>Pagamento</th>
                    <th>Status</th>
                    <th>Aprovacao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            @if ($row->aprovacao_key === 'pendente' && $row->status_key !== 'pago')
                                <input form="despesas-aprovar-lote" type="checkbox" name="despesas[]" value="{{ $row->id }}">
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td>{{ $row->data_lancamento }}</td>
                        <td>
                            <strong>{{ $row->descricao }}</strong>
                            <br><span class="muted">Parcela {{ $row->parcela }} | NF {{ $row->nota_fiscal }}</span>
                        </td>
                        <td>{{ $row->fornecedor }}</td>
                        <td>{{ $row->categoria }}</td>
                        <td>
                            {{ $row->safra }}
                            @if ($row->talhao !== '-')
                                <br><span class="muted">{{ $row->talhao }}</span>
                            @endif
                        </td>
                        <td>{{ $row->quantidade }}</td>
                        <td><strong>{{ $row->valor }}</strong></td>
                        <td>{{ $row->vencimento }}</td>
                        <td>{{ $row->pagamento }}</td>
                        <td><span class="pill {{ $row->status_key === 'pago' ? 'success' : ($row->status_key === 'vencido' ? 'danger' : 'warning') }}">{{ $row->status }}</span></td>
                        <td>
                            <span class="pill {{ $row->aprovacao_key === 'aprovada' ? 'success' : ($row->aprovacao_key === 'reprovada' ? 'danger' : 'warning') }}">{{ $row->aprovacao }}</span>
                            @if ($row->motivo_reprovacao !== '-')
                                <br><span class="muted">{{ $row->motivo_reprovacao }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions" style="justify-content:flex-start">
                                <a class="btn" href="{{ route('financeiro.despesas.edit', $row->id) }}">Editar</a>
                                <a class="btn" href="{{ route('financeiro.despesas.duplicate', $row->id) }}">Duplicar</a>
                                @if ($row->aprovacao_key === 'pendente')
                                    <form method="post" action="{{ route('financeiro.despesas.approve', $row->id) }}">
                                        @csrf
                                        <button class="btn primary" type="submit">Aprovar</button>
                                    </form>
                                    <form method="post" action="{{ route('financeiro.despesas.reject', $row->id) }}" class="form-grid" style="grid-template-columns:minmax(140px,1fr) auto;gap:6px">
                                        @csrf
                                        <input name="motivo_reprovacao" maxlength="255" placeholder="Motivo">
                                        <button class="btn danger" type="submit">Reprovar</button>
                                    </form>
                                @endif
                                @if (in_array($row->status_key, ['pendente', 'vencido'], true) && $row->aprovacao_key === 'aprovada')
                                    <form method="post" action="{{ route('financeiro.despesas.pay', $row->id) }}">
                                        @csrf
                                        <input type="hidden" name="data_pagamento" value="{{ now()->toDateString() }}">
                                        <button class="btn" type="submit">Pagar</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('financeiro.despesas.cancel', $row->id) }}">
                                    @csrf
                                    <button class="btn danger" type="submit">Cancelar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="muted">Nenhuma despesa encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <form id="despesas-aprovar-lote" method="post" action="{{ route('financeiro.despesas.approve-batch') }}">
        @csrf
    </form>
</section>

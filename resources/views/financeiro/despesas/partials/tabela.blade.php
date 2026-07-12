<section class="panel">
    <div class="panel-head">
        <h2>Despesas financeiras</h2>
        <span class="badge">{{ $rows->count() }} despesa(s)</span>
    </div>
    @if ($can_approve_batch)
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
                            @if ($row->can_select_for_batch)
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
                        <td><span class="pill {{ $row->status_tone }}">{{ $row->status }}</span></td>
                        <td>
                            <span class="pill {{ $row->aprovacao_tone }}">{{ $row->aprovacao }}</span>
                            @if ($row->motivo_reprovacao !== '-')
                                <br><span class="muted">{{ $row->motivo_reprovacao }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions" style="justify-content:flex-start">
                                <a class="btn" href="{{ route('financeiro.despesas.edit', $row->id) }}">Editar</a>
                                <a class="btn" href="{{ route('financeiro.despesas.duplicate', $row->id) }}">Duplicar</a>
                                @if ($row->can_approve)
                                    <form method="post" action="{{ route('financeiro.despesas.approve', $row->id) }}">
                                        @csrf
                                        <button class="btn primary" type="submit">Aprovar</button>
                                    </form>
                                @endif
                                @if ($row->can_reject)
                                    <form method="post" action="{{ route('financeiro.despesas.reject', $row->id) }}" class="form-grid" style="grid-template-columns:minmax(140px,1fr) auto;gap:6px">
                                        @csrf
                                        <input name="motivo_reprovacao" maxlength="255" placeholder="Motivo">
                                        <button class="btn danger" type="submit">Reprovar</button>
                                    </form>
                                @endif
                                @if ($row->can_pay)
                                    <form method="post" action="{{ route('financeiro.despesas.pay', $row->id) }}">
                                        @csrf
                                        <input type="hidden" name="data_pagamento" value="{{ now()->toDateString() }}">
                                        <button class="btn" type="submit">Pagar</button>
                                    </form>
                                @endif
                                @if ($row->can_cancel)
                                    <form method="post" action="{{ route('financeiro.despesas.cancel', $row->id) }}">
                                        @csrf
                                        <button class="btn danger" type="submit">Cancelar</button>
                                    </form>
                                @endif
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

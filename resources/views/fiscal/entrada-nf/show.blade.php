@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">Conferencia dos itens, parcelas e envio para contas a pagar.</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('fiscal.entrada-nf.index') }}">Voltar</a>
            @if ($podeConcluir)
                <form method="post" action="{{ route('fiscal.entrada-nf.concluir', ['entrada' => $entrada->id]) }}">
                    @csrf
                    <button class="btn primary" type="submit">Concluir para financeiro</button>
                </form>
            @else
                <span class="pill success">Financeiro confirmado</span>
            @endif
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])

    <section class="panel">
        <div class="panel-head">
            <h2>Dados da entrada</h2>
            <span class="pill {{ $entrada->status_key === 'concluida' ? 'success' : '' }}">{{ $entrada->status }}</span>
        </div>
        <div class="grid cols-3">
            <div><span class="muted">Numero/serie</span><strong>{{ $entrada->numero }}</strong></div>
            <div><span class="muted">Fornecedor</span><strong>{{ $entrada->fornecedor }}</strong></div>
            <div><span class="muted">CNPJ</span><strong>{{ $entrada->fornecedor_doc }}</strong></div>
            <div><span class="muted">Emissao</span><strong>{{ $entrada->data_emissao }}</strong></div>
            <div><span class="muted">Entrada</span><strong>{{ $entrada->data_entrada }}</strong></div>
            <div><span class="muted">Categoria</span><strong>{{ $entrada->categoria }}</strong></div>
            <div><span class="muted">Safra</span><strong>{{ $entrada->safra }}</strong></div>
            <div><span class="muted">Conta</span><strong>{{ $entrada->conta }}</strong></div>
            <div><span class="muted">Patrimonio</span><strong>{{ $entrada->patrimonio }}</strong></div>
            <div><span class="muted">Valor total</span><strong>{{ $entrada->valor_total }}</strong></div>
            <div><span class="muted">Valor produtos</span><strong>{{ $entrada->valor_produtos }}</strong></div>
            <div><span class="muted">Valor financeiro</span><strong>{{ $entrada->valor_financeiro }}</strong></div>
        </div>
    </section>

    @if ($podeConcluir)
        <section class="panel">
            <div class="panel-head">
                <h2>Adicionar item</h2>
                <span class="badge">Produto da NF</span>
            </div>
            <form method="post" action="{{ route('fiscal.entrada-nf.itens.store', ['entrada' => $entrada->id]) }}">
                @csrf
                <div class="grid cols-3">
                    <div class="field">
                        <label>Produto cadastrado</label>
                        <select name="produto_id">
                            <option value="">Cadastrar novo produto</option>
                            @foreach ($produtos as $produto)
                                <option value="{{ $produto->id }}">{{ $produto->descricao_generica }}{{ $produto->ncm ? ' - NCM '.$produto->ncm : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Descricao original da NF</label><input name="descricao_nf"></div>
                    <div class="field"><label>Descricao generica</label><input name="descricao_generica"></div>
                    <div class="field"><label>Unidade</label><input name="unidade" value="un"></div>
                    <div class="field"><label>Quantidade</label><input name="quantidade" inputmode="decimal" value="1"></div>
                    <div class="field"><label>Valor unitario</label><input name="valor_unitario" inputmode="decimal"></div>
                    <div class="field"><label>Valor total</label><input name="valor_total" inputmode="decimal"></div>
                    <div class="field"><label>Total liquido</label><input name="total_liquido" inputmode="decimal"></div>
                    <div class="field">
                        <label>Categoria</label>
                        <select name="categoria_id">
                            <option value="">Usar categoria da capa</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}">{{ $categoria->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Safra</label>
                        <select name="safra_id">
                            <option value="">Usar safra da capa</option>
                            @foreach ($safras as $safra)
                                <option value="{{ $safra->id }}">{{ $safra->descricao }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>NCM</label><input name="ncm"></div>
                    <div class="field"><label>CST ICMS</label><input name="cst_icms"></div>
                    <div class="field"><label>CST PIS</label><input name="cst_pis"></div>
                    <div class="field"><label>CST COFINS</label><input name="cst_cofins"></div>
                    <div class="field"><label>Centro de custo</label><input name="centro_custo"></div>
                    <div class="field"><label>Fazenda/unidade</label><input name="fazenda_unidade"></div>
                </div>
                <div class="actions">
                    <button class="btn primary" type="submit">Adicionar item</button>
                </div>
            </form>
        </section>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Itens da NF</h2>
            <span class="badge">{{ $itens->count() }} item(ns)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Descricao</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Safra</th>
                        <th>Qtd.</th>
                        <th>Un.</th>
                        <th>Valor unit.</th>
                        <th>Total liquido</th>
                        <th>Fiscal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($itens as $item)
                        <tr>
                            <td><strong>{{ $item->descricao }}</strong></td>
                            <td>{{ $item->produto }}</td>
                            <td>{{ $item->categoria }}</td>
                            <td>{{ $item->safra }}</td>
                            <td>{{ $item->quantidade }}</td>
                            <td>{{ $item->unidade }}</td>
                            <td>{{ $item->valor_unitario }}</td>
                            <td><strong>{{ $item->total_liquido }}</strong></td>
                            <td><span class="pill {{ $item->fiscal_ok ? 'success' : '' }}">{{ $item->fiscal_ok ? 'OK' : 'Pendente' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="muted">Nenhum item vinculado a esta entrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>Parcelas financeiras</h2>
            @if ($podeConcluir)
                <form method="post" action="{{ route('fiscal.entrada-nf.parcelas.gerar', ['entrada' => $entrada->id]) }}" class="actions">
                    @csrf
                    <input name="parcelas_qtd" type="number" min="1" max="120" value="1" style="max-width: 90px">
                    <input name="primeiro_vencimento" type="date" value="{{ now()->toDateString() }}">
                    <button class="btn sm" type="submit">Gerar parcelas</button>
                </form>
            @else
                <span class="badge">{{ $parcelas->count() }} parcela(s)</span>
            @endif
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Parcela</th>
                        <th>Vencimento</th>
                        <th>Valor</th>
                        <th>Forma</th>
                        <th>Conta</th>
                        <th>Status</th>
                        <th>Despesa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($parcelas as $parcela)
                        <tr>
                            <td>{{ $parcela->numero }}</td>
                            <td>{{ $parcela->vencimento }}</td>
                            <td><strong>{{ $parcela->valor }}</strong></td>
                            <td>{{ $parcela->forma }}</td>
                            <td>{{ $parcela->conta }}</td>
                            <td><span class="pill {{ $parcela->status_key === 'confirmada' ? 'success' : '' }}">{{ $parcela->status }}</span></td>
                            <td>{{ $parcela->despesa_id ? '#'.$parcela->despesa_id : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">Nenhuma parcela financeira vinculada a esta entrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

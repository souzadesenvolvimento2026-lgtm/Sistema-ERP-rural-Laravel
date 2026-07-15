@php
    $lancamento = $lancamento ?? null;
    $modoEdicaoDespesa = (bool) ($modoEdicaoDespesa ?? false);
    $tipoAtual = old('tipo', $lancamento->tipo ?? ($tipoSelecionado ?? 'despesa'));
    $valorCampo = fn (string $campo, mixed $padrao = '') => old($campo, $lancamento && property_exists($lancamento, $campo) ? $lancamento->{$campo} : $padrao);
    $formaPagamentoAtual = $valorCampo('forma_pagamento', 'pix');
    $baixadoAtual = (string) $valorCampo('baixado', '0');
@endphp

@if ($modoEdicaoDespesa)
    <div class="ff-expense-edit-form">
        <input type="hidden" name="tipo" value="despesa">
@else
    <section class="panel">
        <div class="panel-head"><h2>Dados principais</h2></div>
        <div class="panel-body">
@endif
        <div class="form-grid">
            @unless ($modoEdicaoDespesa)
                <div class="field">
                    <label>Tipo</label>
                    <select name="tipo" required>
                        <option value="despesa" @selected($tipoAtual === 'despesa')>Despesa</option>
                        <option value="receita" @selected($tipoAtual === 'receita')>Receita</option>
                    </select>
                </div>
            @endunless

            <div class="field wide">
                <label>Descrição</label>
                <input name="descricao" value="{{ $valorCampo('descricao') }}" required>
            </div>
            <div class="field">
                <label>Pessoa / fornecedor</label>
                <input name="pessoa" value="{{ $valorCampo('pessoa') }}">
            </div>
            <div class="field">
                <label>Comprador cadastrado</label>
                <select name="comprador_id">
                    <option value="">Usar pessoa informada</option>
                    @foreach (($compradores ?? collect()) as $comprador)
                        <option value="{{ $comprador->id }}" @selected((int) $valorCampo('comprador_id') === (int) $comprador->id)>
                            {{ $comprador->nome }}{{ $comprador->documento ? ' - '.$comprador->documento : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Categoria</label>
                <select name="categoria_id">
                    <option value="">Sem categoria</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected((int) $valorCampo('categoria_id') === (int) $categoria->id)>{{ $categoria->nome }} ({{ $categoria->tipo }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Subcategoria</label>
                <select name="subcategoria_id">
                    <option value="">Sem subcategoria</option>
                    @foreach (($subcategorias ?? collect()) as $subcategoria)
                        <option value="{{ $subcategoria->id }}" @selected((int) $valorCampo('subcategoria_id') === (int) $subcategoria->id)>
                            {{ $subcategoria->nome }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>{{ $modoEdicaoDespesa ? 'Conta debitada' : 'Conta' }}</label>
                <select name="conta_id">
                    <option value="">Não informada</option>
                    @foreach ($contas as $conta)
                        <option value="{{ $conta->id }}" @selected((int) $valorCampo('conta_id') === (int) $conta->id)>
                            {{ $conta->nome }}{{ $conta->banco ? ' - '.$conta->banco : '' }}{{ isset($conta->saldo) ? ' | Saldo '.$conta->saldo : '' }}
                        </option>
                    @endforeach
                </select>
                <small>Obrigatória quando marcar a despesa como paga.</small>
            </div>
            <div class="field">
                <label>Safra</label>
                <select name="safra_id">
                    <option value="">Nenhuma</option>
                    @foreach (($safras ?? collect()) as $safra)
                        <option value="{{ $safra->id }}" @selected((int) $valorCampo('safra_id') === (int) $safra->id)>{{ $safra->descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Talhão</label>
                <select name="talhao_id">
                    <option value="">Geral / Todos</option>
                    @foreach (($talhoes ?? collect()) as $talhao)
                        <option value="{{ $talhao->id }}" @selected((int) $valorCampo('talhao_id') === (int) $talhao->id)>{{ $talhao->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Produtor</label>
                <select name="produtor_id">
                    <option value="">Não informar</option>
                    @foreach (($produtores ?? collect()) as $produtor)
                        <option value="{{ $produtor->id }}" @selected((int) $valorCampo('produtor_id') === (int) $produtor->id)>{{ $produtor->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Quantidade</label>
                <input name="quantidade" inputmode="decimal" value="{{ $valorCampo('quantidade') }}" placeholder="Ex: 120,50">
            </div>
            <div class="field">
                <label>Unidade</label>
                <input name="unidade" value="{{ $valorCampo('unidade', 'sc') }}" placeholder="sc, kg, ton">
            </div>
            <div class="field">
                <label>Valor unitário</label>
                <input name="preco_unitario" inputmode="decimal" value="{{ $valorCampo('preco_unitario') }}" placeholder="0,00">
            </div>
            <div class="field">
                <label>Valor Total (R$)</label>
                <input name="valor_total" inputmode="decimal" value="{{ $valorCampo('valor_total') }}" placeholder="Calculado para receita se vazio">
            </div>
            <div class="field">
                <label>Data</label>
                <input type="date" name="data_lancamento" value="{{ $valorCampo('data_lancamento', date('Y-m-d')) }}" required>
            </div>
            <div class="field">
                <label>Vencimento</label>
                <input type="date" name="data_vencimento" value="{{ $valorCampo('data_vencimento') }}">
            </div>
            <div class="field">
                <label>Número de parcelas</label>
                <input type="number" name="numero_parcelas" min="1" max="36" value="{{ $valorCampo('numero_parcelas', 1) }}">
            </div>
            <div class="field">
                <label>Forma de pagamento</label>
                <select name="forma_pagamento">
                    <option value="pix" @selected($formaPagamentoAtual === 'pix')>Pix</option>
                    <option value="boleto" @selected($formaPagamentoAtual === 'boleto')>Boleto</option>
                    <option value="transferencia" @selected($formaPagamentoAtual === 'transferencia')>Transferência</option>
                    <option value="dinheiro" @selected($formaPagamentoAtual === 'dinheiro')>Dinheiro</option>
                    <option value="cheque" @selected($formaPagamentoAtual === 'cheque')>Cheque</option>
                    <option value="cartao" @selected($formaPagamentoAtual === 'cartao')>Cartão</option>
                </select>
            </div>
            <div class="field">
                <label>Baixado</label>
                <select name="baixado">
                    <option value="0" @selected($baixadoAtual === '0')>Não</option>
                    <option value="1" @selected($baixadoAtual === '1')>Sim</option>
                </select>
            </div>
            <div class="field full">
                <label>Observações</label>
                <textarea name="observacoes">{{ $valorCampo('observacoes') }}</textarea>
            </div>
        </div>
@if ($modoEdicaoDespesa)
    </div>
@else
        </div>
    </section>
@endif

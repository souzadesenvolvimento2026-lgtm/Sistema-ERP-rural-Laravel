@php
    $asCollection = fn ($items) => $items instanceof \Illuminate\Support\Collection ? $items : collect($items ?? []);
    $opcoesLancamento = $lancamentoForm ?? [];
    $categoriasLancamento = $asCollection($opcoesLancamento['categorias'] ?? []);
    $subcategoriasLancamento = $asCollection($opcoesLancamento['subcategorias'] ?? []);
    $contasLancamento = $asCollection($opcoesLancamento['contas'] ?? []);
    $compradoresLancamento = $asCollection($opcoesLancamento['compradores'] ?? []);
    $safrasLancamento = $asCollection($opcoesLancamento['safras'] ?? []);
    $talhoesLancamento = $asCollection($opcoesLancamento['talhoes'] ?? []);
    $produtoresLancamento = $asCollection($opcoesLancamento['produtores'] ?? []);
    $patrimoniosLancamento = $asCollection($opcoesLancamento['patrimonios'] ?? []);
    $contasTransferencia = $asCollection($contas ?? []);
    $safraPadraoId = old('safra_id', optional($safrasLancamento->first())->id);
    $formasPagamento = [
        'pix' => 'PIX',
        'boleto' => 'Boleto',
        'transferencia' => 'Transferência',
        'dinheiro' => 'Dinheiro',
        'cheque' => 'Cheque',
        'cartao' => 'Cartão',
    ];
@endphp

<div class="modal fade ff-financial-launch-modal" id="financeiroNovoLancamentoModal" tabindex="-1" aria-labelledby="financeiroNovoLancamentoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-financial-launch-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-green">
                <h5 class="modal-title" id="financeiroNovoLancamentoModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Novo Lançamento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <p class="ff-financial-launch-help">
                    Escolha o tipo de lançamento que deseja registrar no FarmFort.
                </p>

                <div class="ff-financial-launch-options">
                    <button class="ff-financial-launch-option ff-financial-launch-option-expense" type="button" data-ff-financial-open="#financeiroDespesaModal">
                        <span class="ff-financial-launch-icon" aria-hidden="true">
                            <i class="bi bi-arrow-down-circle"></i>
                        </span>
                        <span class="ff-financial-launch-text">
                            <strong>Despesa</strong>
                            <small>Lançar compra, custo operacional, parcela ou despesa da safra.</small>
                        </span>
                        <i class="bi bi-chevron-right ff-financial-launch-chevron" aria-hidden="true"></i>
                    </button>

                    <button class="ff-financial-launch-option ff-financial-launch-option-income" type="button" data-ff-financial-open="#financeiroReceitaModal">
                        <span class="ff-financial-launch-icon" aria-hidden="true">
                            <i class="bi bi-arrow-up-circle"></i>
                        </span>
                        <span class="ff-financial-launch-text">
                            <strong>Receita</strong>
                            <small>Lançar venda, recebimento ou entrada financeira da propriedade.</small>
                        </span>
                        <i class="bi bi-chevron-right ff-financial-launch-chevron" aria-hidden="true"></i>
                    </button>

                    <button class="ff-financial-launch-option ff-financial-launch-option-transfer" type="button" data-ff-financial-open="#financeiroTransferenciaModal">
                        <span class="ff-financial-launch-icon" aria-hidden="true">
                            <i class="bi bi-arrow-left-right"></i>
                        </span>
                        <span class="ff-financial-launch-text">
                            <strong>Transferência</strong>
                            <small>Mover valor entre contas bancárias ou caixa interno da propriedade.</small>
                        </span>
                        <i class="bi bi-chevron-right ff-financial-launch-chevron" aria-hidden="true"></i>
                    </button>

                    <button class="ff-financial-launch-option ff-financial-launch-option-invoice" type="button" data-ff-financial-open="#financeiroXmlModal">
                        <span class="ff-financial-launch-icon" aria-hidden="true">
                            <i class="bi bi-filetype-xml"></i>
                        </span>
                        <span class="ff-financial-launch-text">
                            <strong>XML / NF</strong>
                            <small>Dar entrada por XML de NF recebida do fornecedor.</small>
                        </span>
                        <i class="bi bi-chevron-right ff-financial-launch-chevron" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade ff-financial-form-modal" id="financeiroDespesaModal" tabindex="-1" aria-labelledby="financeiroDespesaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-financial-form-dialog">
        <form method="POST" action="{{ route('financeiro.lancamentos.store') }}" enctype="multipart/form-data" class="modal-content ff-financial-form-content">
            @csrf
            <input type="hidden" name="tipo" value="despesa">
            <input type="hidden" name="return_route" value="financeiro.index">

            <div class="modal-header modal-header-green ff-financial-form-header">
                <button type="button" class="ff-financial-back-button" data-ff-financial-back aria-label="Voltar">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="ff-financial-form-title">
                    <span>DESPESAS</span>
                    <h5 class="modal-title" id="financeiroDespesaModalLabel">
                        <i class="bi bi-arrow-down-circle"></i> Nova Despesa
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-financial-form-grid">
                    <label class="ff-financial-field ff-span-2">
                        <span>Descrição *</span>
                        <input name="descricao" value="{{ old('descricao') }}" maxlength="255" required placeholder="Ex: Herbicida Roundup">
                    </label>

                    <label class="ff-financial-field">
                        <span>Data *</span>
                        <input type="date" name="data_lancamento" value="{{ old('data_lancamento', date('Y-m-d')) }}" required>
                    </label>

                    <label class="ff-financial-field">
                        <span>Categoria *</span>
                        <select name="categoria_id" required data-ff-category-select>
                            <option value="">Selecione...</option>
                            @foreach ($categoriasLancamento as $categoria)
                                <option value="{{ $categoria->id }}" @selected(old('categoria_id') == $categoria->id)>
                                    {{ $categoria->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Subcategoria</span>
                        <select name="subcategoria_id" data-ff-subcategory-select>
                            <option value="">Selecione...</option>
                            @foreach ($subcategoriasLancamento as $subcategoria)
                                <option value="{{ $subcategoria->id }}" data-parent="{{ $subcategoria->categoria_pai_id }}" @selected(old('subcategoria_id') == $subcategoria->id)>
                                    {{ $subcategoria->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Safra</span>
                        <select name="safra_id">
                            <option value="">Nenhuma</option>
                            @foreach ($safrasLancamento as $safra)
                                <option value="{{ $safra->id }}" @selected((string) $safraPadraoId === (string) $safra->id)>
                                    {{ $safra->descricao }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Patrimônio</span>
                        <select name="maquina_id">
                            <option value="">Não vincular</option>
                            @foreach ($patrimoniosLancamento as $patrimonio)
                                <option value="{{ $patrimonio->id }}" @selected(old('maquina_id') == $patrimonio->id)>
                                    {{ $patrimonio->nome }}{{ $patrimonio->marca_modelo ? ' - '.$patrimonio->marca_modelo : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small>Use quando a despesa for de máquina/equipamento.</small>
                    </label>

                    <label class="ff-financial-field">
                        <span>Produtor</span>
                        <select name="produtor_id">
                            <option value="">Não informar</option>
                            @foreach ($produtoresLancamento as $produtor)
                                <option value="{{ $produtor->id }}" @selected(old('produtor_id') == $produtor->id)>
                                    {{ $produtor->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Talhão</span>
                        <select name="talhao_id">
                            <option value="">Geral / Todos</option>
                            @foreach ($talhoesLancamento as $talhao)
                                <option value="{{ $talhao->id }}" @selected(old('talhao_id') == $talhao->id)>
                                    {{ $talhao->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Fornecedor</span>
                        <input name="pessoa" value="{{ old('pessoa') }}" maxlength="150" placeholder="Nome do fornecedor">
                    </label>

                    <label class="ff-financial-field ff-quantity-field">
                        <span>Quantidade</span>
                        <input name="quantidade" value="{{ old('quantidade') }}" inputmode="decimal" placeholder="0">
                    </label>

                    <label class="ff-financial-field ff-quantity-field">
                        <span>Unidade</span>
                        <select name="unidade">
                            <option value="">Não informar</option>
                            <option value="sc" @selected(old('unidade') === 'sc')>Sacas</option>
                            <option value="L" @selected(old('unidade') === 'L')>Litro</option>
                            <option value="kg" @selected(old('unidade') === 'kg')>Kg</option>
                            <option value="t" @selected(old('unidade') === 't')>Tonelada</option>
                            <option value="un" @selected(old('unidade') === 'un')>Unidade</option>
                        </select>
                    </label>

                    <label class="ff-financial-field ff-quantity-field">
                        <span>Valor Unitário (R$)</span>
                        <input name="preco_unitario" value="{{ old('preco_unitario') }}" inputmode="decimal" placeholder="0,00">
                    </label>

                    <label class="ff-financial-field">
                        <span>Valor Total (R$) *</span>
                        <input name="valor_total" value="{{ old('valor_total') }}" inputmode="decimal" required placeholder="0,00">
                    </label>

                    <label class="ff-financial-field">
                        <span>Nº Parcelas</span>
                        <input type="number" name="numero_parcelas" min="1" max="36" value="{{ old('numero_parcelas', 1) }}">
                    </label>

                    <label class="ff-financial-field">
                        <span>Vencimento</span>
                        <input type="date" name="data_vencimento" value="{{ old('data_vencimento') }}">
                    </label>

                    <label class="ff-financial-field">
                        <span>Forma de Pagamento</span>
                        <select name="forma_pagamento">
                            @foreach ($formasPagamento as $valor => $label)
                                <option value="{{ $valor }}" @selected(old('forma_pagamento', 'pix') === $valor)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Conta debitada</span>
                        <select name="conta_id">
                            <option value="">Não informar</option>
                            @foreach ($contasLancamento as $conta)
                                <option value="{{ $conta->id }}" @selected(old('conta_id') == $conta->id)>
                                    {{ $conta->nome }}{{ $conta->banco ? ' - '.$conta->banco : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Nota Fiscal</span>
                        <input name="nota_fiscal" value="{{ old('nota_fiscal') }}" placeholder="Nº da NF">
                    </label>

                    <label class="ff-financial-field">
                        <span>Comprovante</span>
                        <input type="file" name="comprovante">
                    </label>

                    <label class="ff-financial-field">
                        <span>Status</span>
                        <select name="baixado">
                            <option value="0" @selected(old('baixado', '0') === '0')>Pendente</option>
                            <option value="1" @selected(old('baixado') === '1')>Pago</option>
                        </select>
                    </label>

                    <label class="ff-financial-field ff-span-3">
                        <span>Observações</span>
                        <textarea name="observacoes" rows="3" placeholder="Informações adicionais...">{{ old('observacoes') }}</textarea>
                    </label>
                </div>
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="bi bi-shield-check"></i> Salvar Despesa
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade ff-financial-form-modal" id="financeiroReceitaModal" tabindex="-1" aria-labelledby="financeiroReceitaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-financial-form-dialog">
        <form method="POST" action="{{ route('financeiro.lancamentos.store') }}" class="modal-content ff-financial-form-content">
            @csrf
            <input type="hidden" name="tipo" value="receita">
            <input type="hidden" name="return_route" value="financeiro.index">

            <div class="modal-header modal-header-green ff-financial-form-header">
                <button type="button" class="ff-financial-back-button" data-ff-financial-back aria-label="Voltar">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="ff-financial-form-title">
                    <h5 class="modal-title" id="financeiroReceitaModalLabel">
                        <i class="bi bi-plus-circle"></i> Nova Receita
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-financial-form-grid">
                    <label class="ff-financial-field ff-span-2">
                        <span>Descrição *</span>
                        <input name="descricao" value="{{ old('descricao') }}" maxlength="255" required placeholder="Ex: Venda Soja Safra 24/25">
                    </label>

                    <label class="ff-financial-field">
                        <span>Data da Venda *</span>
                        <input type="date" name="data_lancamento" value="{{ old('data_lancamento', date('Y-m-d')) }}" required>
                    </label>

                    <div class="ff-financial-field ff-span-3">
                        <span>Tipo de receita</span>
                        <div class="ff-financial-segmented">
                            <label>
                                <input type="radio" name="tipo_receita" value="graos" checked data-ff-income-type>
                                <span><i class="bi bi-basket"></i> Receita de Grãos</span>
                            </label>
                            <label>
                                <input type="radio" name="tipo_receita" value="outras" data-ff-income-type>
                                <span><i class="bi bi-receipt"></i> Outras Receitas</span>
                            </label>
                        </div>
                    </div>

                    <label class="ff-financial-field">
                        <span class="ff-financial-label-action">
                            Comprador
                            <button type="button" data-ff-financial-open="#financeiroCompradorModal">Cadastrar</button>
                        </span>
                        <select name="comprador_id">
                            <option value="">Selecione...</option>
                            @foreach ($compradoresLancamento as $comprador)
                                <option value="{{ $comprador->id }}" @selected(old('comprador_id') == $comprador->id)>
                                    {{ $comprador->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Categoria</span>
                        <select name="categoria_id" data-ff-category-select>
                            <option value="">Sem categoria</option>
                            @foreach ($categoriasLancamento as $categoria)
                                <option value="{{ $categoria->id }}" @selected(old('categoria_id') == $categoria->id)>
                                    {{ $categoria->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Subcategoria</span>
                        <select name="subcategoria_id" data-ff-subcategory-select>
                            <option value="">Sem subcategoria</option>
                            @foreach ($subcategoriasLancamento as $subcategoria)
                                <option value="{{ $subcategoria->id }}" data-parent="{{ $subcategoria->categoria_pai_id }}" @selected(old('subcategoria_id') == $subcategoria->id)>
                                    {{ $subcategoria->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Comprador manual</span>
                        <input name="pessoa" value="{{ old('pessoa') }}" maxlength="150" placeholder="Nome do comprador">
                    </label>

                    <label class="ff-financial-field">
                        <span>Produtor</span>
                        <select name="produtor_id">
                            <option value="">Não informar</option>
                            @foreach ($produtoresLancamento as $produtor)
                                <option value="{{ $produtor->id }}" @selected(old('produtor_id') == $produtor->id)>
                                    {{ $produtor->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Safra</span>
                        <select name="safra_id">
                            <option value="">Nenhuma</option>
                            @foreach ($safrasLancamento as $safra)
                                <option value="{{ $safra->id }}" @selected((string) $safraPadraoId === (string) $safra->id)>
                                    {{ $safra->descricao }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field">
                        <span>Conta creditada</span>
                        <select name="conta_id">
                            <option value="">Não informar</option>
                            @foreach ($contasLancamento as $conta)
                                <option value="{{ $conta->id }}" @selected(old('conta_id') == $conta->id)>
                                    {{ $conta->nome }}{{ $conta->banco ? ' - '.$conta->banco : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-financial-field" data-ff-grain-field>
                        <span>Sacas vendidas</span>
                        <input name="quantidade" value="{{ old('quantidade', '0') }}" inputmode="decimal">
                    </label>

                    <label class="ff-financial-field" data-ff-grain-field>
                        <span>Unidade</span>
                        <input name="unidade" value="{{ old('unidade', 'sc') }}" maxlength="30">
                    </label>

                    <label class="ff-financial-field" data-ff-grain-field>
                        <span>Valor por saca</span>
                        <input name="preco_unitario" value="{{ old('preco_unitario') }}" inputmode="decimal" placeholder="0,00">
                    </label>

                    <label class="ff-financial-field">
                        <span>Valor Total *</span>
                        <input name="valor_total" value="{{ old('valor_total') }}" inputmode="decimal" required placeholder="0,00">
                        <small>Calculado automaticamente pelas sacas quando vazio.</small>
                    </label>

                    <label class="ff-financial-field">
                        <span>Data Recebimento</span>
                        <input type="date" name="data_recebimento" value="{{ old('data_recebimento') }}">
                    </label>

                    <label class="ff-financial-field">
                        <span>Status</span>
                        <select name="baixado">
                            <option value="0" @selected(old('baixado', '0') === '0')>Pendente</option>
                            <option value="1" @selected(old('baixado') === '1')>Recebido</option>
                        </select>
                    </label>

                    <label class="ff-financial-field ff-span-3">
                        <span>Observações</span>
                        <textarea name="observacoes" rows="3">{{ old('observacoes') }}</textarea>
                    </label>
                </div>
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="bi bi-shield-check"></i> Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade ff-financial-form-modal" id="financeiroCompradorModal" tabindex="-1" aria-labelledby="financeiroCompradorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-financial-buyer-dialog">
        <form method="POST" action="{{ route('financeiro.receitas.compradores.store') }}" class="modal-content ff-financial-form-content">
            @csrf
            <input type="hidden" name="return_route" value="financeiro.index">

            <div class="modal-header modal-header-green ff-financial-form-header">
                <button type="button" class="ff-financial-back-button" data-ff-financial-open="#financeiroReceitaModal" aria-label="Voltar">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="ff-financial-form-title">
                    <h5 class="modal-title" id="financeiroCompradorModalLabel">
                        <i class="bi bi-building-add"></i> Cadastrar comprador
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-financial-form-grid">
                    <label class="ff-financial-field ff-span-2">
                        <span>Nome do comprador *</span>
                        <input name="nome" maxlength="150" required placeholder="Ex: Bunge">
                    </label>

                    <label class="ff-financial-field">
                        <span>Documento</span>
                        <input name="documento" maxlength="30" placeholder="Opcional">
                    </label>
                </div>
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn" data-ff-financial-open="#financeiroReceitaModal">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="bi bi-shield-check"></i> Salvar comprador
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade ff-financial-form-modal" id="financeiroTransferenciaModal" tabindex="-1" aria-labelledby="financeiroTransferenciaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-financial-transfer-dialog">
        <form method="POST" action="{{ route('financeiro.contas.transfer', [], false) }}" class="modal-content ff-financial-form-content">
            @csrf
            <input type="hidden" name="return_route" value="financeiro.index">

            <div class="modal-header modal-header-green ff-financial-form-header">
                <button type="button" class="ff-financial-back-button" data-ff-financial-back aria-label="Voltar">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="ff-financial-form-title">
                    <h5 class="modal-title" id="financeiroTransferenciaModalLabel">Nova Transferência</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-financial-form-grid ff-transfer-grid">
                    <label class="ff-financial-field">
                        <span>Conta de Origem *</span>
                        <select name="origem" required data-ff-transfer-origin>
                            <option value="">Selecione...</option>
                            @foreach ($contasTransferencia as $conta)
                                <option value="{{ $conta->id }}" @selected(old('origem') == $conta->id)>
                                    {{ $conta->nome }} - {{ $conta->saldo ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        <small>Saldo disponível da conta selecionada.</small>
                    </label>

                    <label class="ff-financial-field">
                        <span>Conta de Destino *</span>
                        <select name="destino" required data-ff-transfer-destination>
                            <option value="">Selecione...</option>
                            @foreach ($contasTransferencia as $conta)
                                <option value="{{ $conta->id }}" @selected(old('destino') == $conta->id)>
                                    {{ $conta->nome }} - {{ $conta->saldo ?? '' }}
                                </option>
                            @endforeach
                        </select>
                        <small>Saldo atual da conta selecionada.</small>
                    </label>

                    <div class="ff-financial-field ff-span-2">
                        <button type="button" class="btn btn-outline-secondary ff-transfer-invert" data-ff-transfer-invert>
                            <i class="bi bi-arrow-left-right"></i> Inverter origem e destino
                        </button>
                    </div>

                    <label class="ff-financial-field">
                        <span>Valor (R$) *</span>
                        <input name="valor" value="{{ old('valor') }}" inputmode="decimal" required placeholder="0,00">
                    </label>

                    <label class="ff-financial-field">
                        <span>Data *</span>
                        <input type="date" name="data_transferencia" value="{{ old('data_transferencia', date('Y-m-d')) }}" required>
                    </label>

                    <label class="ff-financial-field ff-span-2">
                        <span>Descrição</span>
                        <input name="descricao" value="{{ old('descricao') }}" maxlength="255" placeholder="Motivo da transferência">
                    </label>

                    <div class="ff-transfer-help ff-span-2">
                        Informe o valor para visualizar a baixa na origem e o crédito no destino.
                    </div>
                </div>
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="bi bi-arrow-left-right"></i> Registrar transferência
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade ff-financial-form-modal" id="financeiroXmlModal" tabindex="-1" aria-labelledby="financeiroXmlModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-financial-transfer-dialog">
        <form method="POST" action="{{ route('fiscal.notas.store') }}" enctype="multipart/form-data" class="modal-content ff-financial-form-content">
            @csrf

            <div class="modal-header modal-header-green ff-financial-form-header">
                <button type="button" class="ff-financial-back-button" data-ff-financial-back aria-label="Voltar">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="ff-financial-form-title">
                    <h5 class="modal-title" id="financeiroXmlModalLabel">
                        <i class="bi bi-filetype-xml"></i> XML / NF
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-financial-form-grid">
                    <label class="ff-financial-field ff-span-3">
                        <span>XML da NF-e *</span>
                        <input type="file" name="xml" accept=".xml,text/xml,application/xml" required>
                        <small>O FarmFort fará a leitura do XML e abrirá a conferência antes de confirmar o lançamento fiscal.</small>
                    </label>
                </div>
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="bi bi-upload"></i> Processar XML
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.bootstrap) return;

            const pickerEl = document.getElementById('financeiroNovoLancamentoModal');
            const picker = pickerEl ? bootstrap.Modal.getOrCreateInstance(pickerEl) : null;

            document.querySelectorAll('[data-ff-financial-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    const targetEl = document.querySelector(button.dataset.ffFinancialOpen || '');
                    if (!targetEl) return;

                    const target = bootstrap.Modal.getOrCreateInstance(targetEl);
                    const showTarget = () => target.show();
                    const currentEl = button.closest('.modal.show');

                    if (currentEl && currentEl !== targetEl) {
                        currentEl.addEventListener('hidden.bs.modal', showTarget, { once: true });
                        bootstrap.Modal.getOrCreateInstance(currentEl).hide();
                    } else if (pickerEl?.classList.contains('show') && picker) {
                        pickerEl.addEventListener('hidden.bs.modal', showTarget, { once: true });
                        picker.hide();
                    } else {
                        showTarget();
                    }
                });
            });

            document.querySelectorAll('[data-ff-financial-back]').forEach((button) => {
                button.addEventListener('click', () => {
                    const currentEl = button.closest('.modal');
                    if (!currentEl || !picker) return;

                    const current = bootstrap.Modal.getOrCreateInstance(currentEl);
                    currentEl.addEventListener('hidden.bs.modal', () => picker.show(), { once: true });
                    current.hide();
                });
            });

            const invertButton = document.querySelector('[data-ff-transfer-invert]');
            const origem = document.querySelector('[data-ff-transfer-origin]');
            const destino = document.querySelector('[data-ff-transfer-destination]');

            invertButton?.addEventListener('click', () => {
                if (!origem || !destino) return;
                const origemValue = origem.value;
                origem.value = destino.value;
                destino.value = origemValue;
            });

            const parseMoney = (value) => {
                const normalized = String(value || '')
                    .replace(/\s+/g, '')
                    .replace(/\./g, '')
                    .replace(',', '.');

                const parsed = Number.parseFloat(normalized);
                return Number.isFinite(parsed) ? parsed : 0;
            };

            const formatMoney = (value) => value.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const filterSubcategories = (categorySelect) => {
                const form = categorySelect.closest('form');
                const subcategorySelect = form?.querySelector('[data-ff-subcategory-select]');
                if (!subcategorySelect) return;

                const categoryId = categorySelect.value || '';
                Array.from(subcategorySelect.options).forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    const visible = option.dataset.parent === categoryId;
                    option.hidden = !visible;
                    if (!visible && option.selected) {
                        subcategorySelect.value = '';
                    }
                });
            };

            document.querySelectorAll('[data-ff-category-select]').forEach((categorySelect) => {
                filterSubcategories(categorySelect);
                categorySelect.addEventListener('change', () => filterSubcategories(categorySelect));
            });

            const calculateFormTotal = (form) => {
                const quantity = parseMoney(form.querySelector('[name="quantidade"]')?.value);
                const unitValue = parseMoney(form.querySelector('[name="preco_unitario"]')?.value);
                const total = form.querySelector('[name="valor_total"]');

                if (total && quantity > 0 && unitValue > 0) {
                    total.value = formatMoney(quantity * unitValue);
                }
            };

            document.querySelectorAll('.ff-financial-form-modal form').forEach((form) => {
                form.querySelectorAll('[name="quantidade"], [name="preco_unitario"]').forEach((input) => {
                    input.addEventListener('input', () => calculateFormTotal(form));
                });
            });

            const updateIncomeType = () => {
                const modal = document.getElementById('financeiroReceitaModal');
                const type = modal?.querySelector('[data-ff-income-type]:checked')?.value || 'graos';
                const isGrain = type === 'graos';

                modal?.querySelectorAll('[data-ff-grain-field]').forEach((field) => {
                    field.hidden = !isGrain;
                });

                if (!isGrain && modal) {
                    const quantity = modal.querySelector('[name="quantidade"]');
                    const unitValue = modal.querySelector('[name="preco_unitario"]');
                    const unit = modal.querySelector('[name="unidade"]');
                    if (quantity) quantity.value = '';
                    if (unitValue) unitValue.value = '';
                    if (unit) unit.value = '';
                } else if (modal) {
                    const unit = modal.querySelector('[name="unidade"]');
                    if (unit && !unit.value) unit.value = 'sc';
                }
            };

            document.querySelectorAll('[data-ff-income-type]').forEach((input) => {
                input.addEventListener('change', updateIncomeType);
            });
            updateIncomeType();
        });
    </script>
@endpush

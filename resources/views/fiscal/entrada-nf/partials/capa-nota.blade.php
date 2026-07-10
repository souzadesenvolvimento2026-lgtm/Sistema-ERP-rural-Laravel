<section class="panel">
    <div class="panel-head"><h2>Capa da nota</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field"><label>Número</label><input name="numero" required></div>
            <div class="field"><label>Série</label><input name="serie"></div>
            <div class="field"><label>Chave de acesso</label><input name="chave_acesso"></div>
            <div class="field"><label>Emissão</label><input type="date" name="data_emissao" value="{{ date('Y-m-d') }}" required></div>
            <div class="field"><label>Entrada</label><input type="date" name="data_entrada" value="{{ date('Y-m-d') }}" required></div>
            <div class="field"><label>Vencimento</label><input type="date" name="data_vencimento"></div>
            <div class="field wide"><label>Fornecedor</label><input name="fornecedor" required></div>
            <div class="field"><label>CNPJ/CPF</label><input name="fornecedor_doc"></div>
            <div class="field"><label>Valor total</label><input name="valor_total" inputmode="decimal" required></div>
            <div class="field"><label>Valor produtos</label><input name="valor_produtos" inputmode="decimal"></div>
            <div class="field"><label>Frete</label><input name="valor_frete" inputmode="decimal"></div>
            <div class="field"><label>Desconto</label><input name="valor_desconto" inputmode="decimal"></div>
            <div class="field"><label>Impostos</label><input name="valor_impostos" inputmode="decimal"></div>
            <div class="field"><label>Valor financeiro</label><input name="valor_financeiro_final" inputmode="decimal"></div>
            <div class="field">
                <label>Forma pagamento</label>
                <select name="forma_pagamento" required>
                    <option value="boleto">Boleto</option>
                    <option value="pix">Pix</option>
                    <option value="transferencia">Transferência</option>
                    <option value="dinheiro">Dinheiro</option>
                    <option value="cheque">Cheque</option>
                    <option value="cartao">Cartão</option>
                </select>
            </div>
            <div class="field">
                <label>Conta</label>
                <select name="conta_id">
                    <option value="">Não informada</option>
                    @foreach ($contas as $conta)
                        <option value="{{ $conta->id }}">{{ $conta->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Categoria</label>
                <select name="categoria_id">
                    <option value="">Sem categoria</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}">{{ $categoria->nome }} ({{ $categoria->tipo }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Safra</label>
                <select name="safra_id">
                    <option value="">Sem safra</option>
                    @foreach ($safras as $safra)
                        <option value="{{ $safra->id }}">{{ $safra->descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label>Centro de custo</label><input name="centro_custo"></div>
            <div class="field"><label>Fazenda/unidade</label><input name="fazenda_unidade"></div>
            <div class="field full"><label>Observações da nota</label><textarea name="observacoes_nota"></textarea></div>
            <div class="field full"><label>Observações financeiras</label><textarea name="observacoes_financeiras"></textarea></div>
            <div class="field">
                <label>Classificar como patrimonio</label>
                <select name="classificar_patrimonio">
                    <option value="0">Nao</option>
                    <option value="1">Sim</option>
                </select>
            </div>
            <div class="field wide">
                <label>Nome do patrimonio</label>
                <input name="patrimonio_nome" placeholder="Ex: Trator, implemento, veiculo">
            </div>
            <div class="field">
                <label>Tipo do patrimonio</label>
                <select name="patrimonio_tipo">
                    <option value="implemento">Implemento</option>
                    <option value="trator">Trator</option>
                    <option value="colheitadeira">Colheitadeira</option>
                    <option value="plantadeira">Plantadeira</option>
                    <option value="pulverizador">Pulverizador</option>
                    <option value="caminhao">Caminhao</option>
                    <option value="outro">Outro</option>
                </select>
            </div>
            <div class="field"><label>Tipo outro</label><input name="patrimonio_tipo_outro"></div>
            <div class="field">
                <label>Controla horimetro</label>
                <select name="patrimonio_controla_horimetro">
                    <option value="0">Nao</option>
                    <option value="1">Sim</option>
                </select>
            </div>
            <div class="field">
                <label>Controla odometro</label>
                <select name="patrimonio_controla_odometro">
                    <option value="0">Nao</option>
                    <option value="1">Sim</option>
                </select>
            </div>
        </div>
    </div>
</section>

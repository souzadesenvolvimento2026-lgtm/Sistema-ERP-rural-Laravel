<div class="modal fade ff-patrimony-modal" id="patrimonyLaunchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="post" action="{{ route('patrimonio.lancamentos.store', $patrimonio->id) }}" enctype="multipart/form-data" class="modal-content">
            @csrf

            <div class="modal-header modal-header-green">
                <h5 class="modal-title">Lançamento do patrimônio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-patrimony-form-grid">
                    <label class="ff-patrimony-field">
                        <span>Tipo *</span>
                        <select class="form-select" name="tipo" required>
                            @foreach ($tiposLancamento as $value => $label)
                                <option value="{{ $value }}" @selected(old('tipo') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Data *</span>
                        <input class="form-control" type="date" name="data_lancamento" value="{{ old('data_lancamento', date('Y-m-d')) }}" required>
                    </label>

                    <label class="ff-patrimony-field ff-patrimony-field-wide">
                        <span>Descrição *</span>
                        <input class="form-control" name="descricao" value="{{ old('descricao') }}" required>
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Fornecedor</span>
                        <input class="form-control" name="fornecedor" value="{{ old('fornecedor') }}">
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Safra</span>
                        <select class="form-select" name="safra_id">
                            <option value="">Sem safra</option>
                            @foreach ($safras as $safra)
                                <option value="{{ $safra->id }}" @selected((string) old('safra_id') === (string) $safra->id)>{{ $safra->descricao }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Talhão</span>
                        <select class="form-select" name="talhao_id">
                            <option value="">Sem talhão</option>
                            @foreach ($talhoes as $talhao)
                                <option value="{{ $talhao->id }}" @selected((string) old('talhao_id') === (string) $talhao->id)>{{ $talhao->nome }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Quantidade</span>
                        <input class="form-control" name="quantidade" inputmode="decimal" value="{{ old('quantidade') }}">
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Unidade</span>
                        <input class="form-control" name="unidade" value="{{ old('unidade') }}">
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Valor unitário</span>
                        <input class="form-control" name="valor_unitario" inputmode="decimal" value="{{ old('valor_unitario') }}">
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Valor total</span>
                        <input class="form-control" name="valor_total" inputmode="decimal" value="{{ old('valor_total') }}">
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Horímetro</span>
                        <input class="form-control" name="horimetro" inputmode="decimal" value="{{ old('horimetro') }}">
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Odômetro</span>
                        <input class="form-control" name="odometro" inputmode="decimal" value="{{ old('odometro') }}">
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Próxima revisão</span>
                        <input class="form-control" name="proxima_revisao_horas" inputmode="decimal" value="{{ old('proxima_revisao_horas') }}">
                    </label>

                    <label class="ff-patrimony-field">
                        <span>Comprovante</span>
                        <input class="form-control" type="file" name="comprovante" accept=".pdf,.jpg,.jpeg,.png">
                    </label>

                    <label class="ff-patrimony-field ff-patrimony-field-full">
                        <span>Observações</span>
                        <textarea class="form-control" name="observacoes" rows="3">{{ old('observacoes') }}</textarea>
                    </label>
                </div>
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn primary" type="submit">Salvar</button>
            </div>
        </form>
    </div>
</div>

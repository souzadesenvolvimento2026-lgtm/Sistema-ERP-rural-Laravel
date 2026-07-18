<div class="modal fade ff-stock-use-modal" id="produtoBaixaModal" tabindex="-1" aria-labelledby="produtoBaixaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="post" action="#" class="modal-content" data-produto-baixa-form>
            @csrf

            <div class="modal-header modal-header-green">
                <h5 class="modal-title" id="produtoBaixaModalLabel">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Dar baixa no estoque
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-stock-use-summary">
                    <span>Produto</span>
                    <strong data-produto-baixa-nome>Selecione um produto</strong>
                    <small>Saldo disponível: <b data-produto-baixa-saldo>-</b></small>
                </div>

                <div class="ff-stock-use-grid">
                    <label class="field">
                        <span>Destino da baixa *</span>
                        <select name="destino_tipo" class="form-select" data-produto-baixa-destino required>
                            <option value="safra">Uso direto em safra/talhão</option>
                            <option value="patrimonio">Uso em patrimônio</option>
                            <option value="ajuste">Perda, devolução ou ajuste</option>
                        </select>
                    </label>

                    <label class="field">
                        <span>Quantidade *</span>
                        <input class="form-control" name="quantidade" inputmode="decimal" placeholder="Ex: 10,5" required>
                    </label>

                    <label class="field">
                        <span>Data da baixa *</span>
                        <input class="form-control" type="date" name="data_movimento" value="{{ date('Y-m-d') }}" required>
                    </label>

                    <label class="field" data-produto-baixa-safra>
                        <span data-produto-baixa-safra-label>Safra *</span>
                        <select name="safra_id" class="form-select">
                            <option value="">Sem safra / uso geral da fazenda</option>
                            @foreach ($safras as $safra)
                                <option value="{{ $safra->id }}">{{ $safra->descricao }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field" data-produto-baixa-patrimonio hidden>
                        <span>Patrimônio *</span>
                        <select name="maquina_id" class="form-select">
                            <option value="">Selecione o patrimônio</option>
                            @foreach ($patrimonios as $patrimonio)
                                <option value="{{ $patrimonio->id }}">{{ $patrimonio->nome }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field" data-produto-baixa-talhao>
                        <span>Talhão</span>
                        <select name="talhao_id" class="form-select">
                            <option value="">Geral / todos</option>
                            @foreach ($talhoes as $talhao)
                                <option value="{{ $talhao->id }}">{{ $talhao->nome }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field ff-stock-use-field-wide" data-produto-baixa-motivo hidden>
                        <span>Motivo *</span>
                        <input class="form-control" name="motivo" maxlength="120" placeholder="Ex: perda, devolução ao fornecedor, ajuste de inventário">
                    </label>

                    <label class="field ff-stock-use-field-full" data-produto-baixa-justificativa-sem-safra hidden>
                        <span>Justificativa sem safra *</span>
                        <textarea
                            class="form-control"
                            name="justificativa_sem_safra"
                            rows="2"
                            maxlength="500"
                            placeholder="Explique por que esta baixa é um uso geral da fazenda e não está vinculada a uma safra."
                        ></textarea>
                    </label>

                    <label class="field ff-stock-use-field-full">
                        <span>Observações</span>
                        <textarea class="form-control" name="observacoes" rows="3" placeholder="Informações para rastreabilidade da safra, manutenção ou ajuste."></textarea>
                    </label>
                </div>

                <div class="ff-stock-use-note">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        Se o destino for patrimônio, o FarmFort também registra um lançamento operacional no patrimônio.
                        A safra pode ficar em branco quando for uso geral da fazenda, desde que a justificativa seja registrada.
                    </span>
                </div>
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="bi bi-check2-circle"></i> Registrar baixa
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('produtoBaixaModal');
            if (!modal) return;

            const form = modal.querySelector('[data-produto-baixa-form]');
            const destino = modal.querySelector('[data-produto-baixa-destino]');
            const nome = modal.querySelector('[data-produto-baixa-nome]');
            const saldo = modal.querySelector('[data-produto-baixa-saldo]');
            const safraGroup = modal.querySelector('[data-produto-baixa-safra]');
            const patrimonioGroup = modal.querySelector('[data-produto-baixa-patrimonio]');
            const talhaoGroup = modal.querySelector('[data-produto-baixa-talhao]');
            const motivoGroup = modal.querySelector('[data-produto-baixa-motivo]');
            const justificativaSemSafraGroup = modal.querySelector('[data-produto-baixa-justificativa-sem-safra]');
            const safraLabel = modal.querySelector('[data-produto-baixa-safra-label]');
            const safraSelect = safraGroup?.querySelector('select');
            const patrimonioSelect = patrimonioGroup?.querySelector('select');
            const motivoInput = motivoGroup?.querySelector('input');
            const justificativaSemSafraInput = justificativaSemSafraGroup?.querySelector('textarea');

            function toggleDestino() {
                const value = destino.value;
                const usaSafra = value === 'safra' || value === 'patrimonio';
                const usaPatrimonio = value === 'patrimonio';
                const usaMotivo = value === 'ajuste';
                const patrimonioSemSafra = usaPatrimonio && !safraSelect?.value;

                safraGroup.hidden = !usaSafra;
                talhaoGroup.hidden = !usaSafra;
                patrimonioGroup.hidden = !usaPatrimonio;
                motivoGroup.hidden = !usaMotivo;
                justificativaSemSafraGroup.hidden = !patrimonioSemSafra;

                if (safraLabel) safraLabel.textContent = usaPatrimonio ? 'Safra (opcional)' : 'Safra *';
                if (safraSelect) safraSelect.required = value === 'safra';
                if (patrimonioSelect) patrimonioSelect.required = usaPatrimonio;
                if (motivoInput) motivoInput.required = usaMotivo;
                if (justificativaSemSafraInput) {
                    justificativaSemSafraInput.required = patrimonioSemSafra;
                    if (!patrimonioSemSafra) {
                        justificativaSemSafraInput.value = '';
                    }
                }
            }

            modal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                if (!button || !form) return;

                form.reset();
                form.action = button.dataset.produtoAction || '#';
                nome.textContent = button.dataset.produtoNome || 'Produto selecionado';
                saldo.textContent = button.dataset.produtoSaldo || '-';

                if (destino) {
                    destino.value = 'safra';
                    toggleDestino();
                }
            });

            destino?.addEventListener('change', toggleDestino);
            safraSelect?.addEventListener('change', toggleDestino);
            toggleDestino();
        });
    </script>
@endpush

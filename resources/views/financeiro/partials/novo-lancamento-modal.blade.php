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
                    <a class="ff-financial-launch-option ff-financial-launch-option-expense" href="{{ route('financeiro.lancamentos.create', ['tipo' => 'despesa']) }}">
                        <span class="ff-financial-launch-icon" aria-hidden="true">
                            <i class="bi bi-arrow-down-circle"></i>
                        </span>
                        <span class="ff-financial-launch-text">
                            <strong>Despesa</strong>
                            <small>Lançar compra, custo operacional, parcela ou despesa da safra.</small>
                        </span>
                        <i class="bi bi-chevron-right ff-financial-launch-chevron" aria-hidden="true"></i>
                    </a>

                    <a class="ff-financial-launch-option ff-financial-launch-option-income" href="{{ route('financeiro.lancamentos.create', ['tipo' => 'receita']) }}">
                        <span class="ff-financial-launch-icon" aria-hidden="true">
                            <i class="bi bi-arrow-up-circle"></i>
                        </span>
                        <span class="ff-financial-launch-text">
                            <strong>Receita</strong>
                            <small>Lançar venda, recebimento ou entrada financeira da propriedade.</small>
                        </span>
                        <i class="bi bi-chevron-right ff-financial-launch-chevron" aria-hidden="true"></i>
                    </a>

                    <a class="ff-financial-launch-option ff-financial-launch-option-transfer" href="{{ route('financeiro.contas.index', ['transferencia' => 1]) }}">
                        <span class="ff-financial-launch-icon" aria-hidden="true">
                            <i class="bi bi-arrow-left-right"></i>
                        </span>
                        <span class="ff-financial-launch-text">
                            <strong>Transferência</strong>
                            <small>Mover valor entre contas bancárias ou caixa interno da propriedade.</small>
                        </span>
                        <i class="bi bi-chevron-right ff-financial-launch-chevron" aria-hidden="true"></i>
                    </a>

                    <a class="ff-financial-launch-option ff-financial-launch-option-invoice" href="{{ route('fiscal.notas.create') }}">
                        <span class="ff-financial-launch-icon" aria-hidden="true">
                            <i class="bi bi-filetype-xml"></i>
                        </span>
                        <span class="ff-financial-launch-text">
                            <strong>XML / NF</strong>
                            <small>Dar entrada por XML de NF recebida do fornecedor.</small>
                        </span>
                        <i class="bi bi-chevron-right ff-financial-launch-chevron" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

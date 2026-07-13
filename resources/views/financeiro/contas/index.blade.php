@extends('layouts.farmfort', ['title' => 'FarmFort - Contas Bancárias'])

@php
    $tiposConta = [
        'conta_corrente' => 'conta corrente',
        'conta_poupanca' => 'conta poupança',
        'caixa_interno' => 'caixa interno',
        'investimento' => 'investimento',
    ];

    $money = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.');
    $moneyInput = fn ($valor) => number_format((float) $valor, 2, ',', '.');
    $saldoClass = fn ($valor) => (float) $valor < 0 ? 'is-negative' : 'is-positive';

    $contasPayload = $contasAtivas->map(function ($conta) use ($moneyInput) {
        return [
            'id' => (int) $conta->id,
            'nome' => (string) $conta->nome,
            'tipo' => (string) $conta->tipo,
            'banco' => (string) ($conta->banco ?? ''),
            'agencia' => (string) ($conta->agencia ?? ''),
            'numero_conta' => (string) ($conta->numero_conta ?? ''),
            'saldo_inicial' => $moneyInput($conta->saldo_inicial ?? 0),
            'saldo_atual' => (float) ($conta->saldo_atual ?? 0),
            'update_url' => route('financeiro.contas.update', $conta->id),
        ];
    })->values();

    $transferenciasPayload = $transferencias->map(function ($transferencia) use ($moneyInput) {
        return [
            'id' => (int) $transferencia->id,
            'origem' => (int) $transferencia->conta_origem_id,
            'destino' => (int) $transferencia->conta_destino_id,
            'valor' => $moneyInput($transferencia->valor ?? 0),
            'data_transferencia' => (string) $transferencia->data_transferencia,
            'descricao' => (string) ($transferencia->descricao ?? ''),
            'update_url' => route('financeiro.contas.transfer.update', $transferencia->id),
        ];
    })->values();

    $transferenciaEditandoPayload = isset($transferenciaEditando) ? [
        'id' => (int) $transferenciaEditando->id,
        'origem' => (int) $transferenciaEditando->conta_origem_id,
        'destino' => (int) $transferenciaEditando->conta_destino_id,
        'valor' => $moneyInput($transferenciaEditando->valor ?? 0),
        'data_transferencia' => (string) $transferenciaEditando->data_transferencia,
        'descricao' => (string) ($transferenciaEditando->descricao ?? ''),
        'update_url' => route('financeiro.contas.transfer.update', $transferenciaEditando->id),
    ] : null;

    $oldAccount = [
        'id' => old('conta_id'),
        'nome' => old('nome'),
        'tipo' => old('tipo'),
        'banco' => old('banco'),
        'agencia' => old('agencia'),
        'numero_conta' => old('numero_conta'),
        'saldo_inicial' => old('saldo_inicial'),
    ];

    $oldTransfer = [
        'id' => old('transferencia_id'),
        'origem' => old('origem'),
        'destino' => old('destino'),
        'valor' => old('valor'),
        'data_transferencia' => old('data_transferencia'),
        'descricao' => old('descricao'),
    ];
@endphp

@section('content')
    <div class="ff-bank-page">
        <div class="ff-bank-toolbar">
            <div>
                <h1>Contas Bancárias</h1>
                <p class="subtitle">Gerencie contas bancárias, caixas internos e transferências da propriedade.</p>
            </div>
            @if ($canManageFinance)
                <div class="ff-bank-toolbar-actions">
                    <button class="btn" type="button" data-bank-open-transfer @disabled($contasAtivas->count() < 2)>
                        <i class="bi bi-arrow-left-right"></i> Transferência
                    </button>
                    <button class="btn primary" type="button" data-bank-open-account>
                        <i class="bi bi-plus-lg"></i> Nova Conta
                    </button>
                </div>
            @endif
        </div>

        <section class="ff-bank-total-card">
            <span>Saldo Total em Contas</span>
            <strong class="{{ $saldoClass($totais['saldo_atual']) }}">{{ $money($totais['saldo_atual']) }}</strong>
            <small>{{ $totais['ativas'] }} conta(s) ativa(s)</small>
        </section>

        <div class="ff-bank-grid">
            <section class="panel ff-bank-panel ff-bank-accounts-panel">
                <div class="panel-head">
                    <h2><i class="bi bi-bank"></i> Contas e Caixas</h2>
                    @if ($canManageFinance)
                        <div class="ff-bank-panel-actions">
                            <button class="btn small" type="button" data-bank-open-transfer @disabled($contasAtivas->count() < 2)>
                                <i class="bi bi-arrow-left-right"></i> Transferência
                            </button>
                            <button class="btn primary small" type="button" data-bank-open-account>
                                <i class="bi bi-plus-lg"></i> Nova Conta
                            </button>
                        </div>
                    @endif
                </div>

                <div class="table-wrap ff-bank-table-wrap">
                    <table class="ff-bank-table">
                        <thead>
                            <tr>
                                <th>Conta</th>
                                <th>Tipo</th>
                                <th>Banco</th>
                                <th>Saldo Inicial</th>
                                <th>Saldo Atual</th>
                                @if ($canManageFinance)
                                    <th>Ações</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contasAtivas as $contaBancaria)
                                <tr>
                                    <td>
                                        <strong>{{ $contaBancaria->nome }}</strong>
                                        @if ($contaBancaria->agencia || $contaBancaria->numero_conta)
                                            <small>{{ trim(($contaBancaria->agencia ?: '').' '.($contaBancaria->numero_conta ?: '')) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $tiposConta[$contaBancaria->tipo] ?? \App\Support\FarmFormat::statusLabel($contaBancaria->tipo) }}</td>
                                    <td>{{ $contaBancaria->banco ?: '-' }}</td>
                                    <td>{{ $money($contaBancaria->saldo_inicial) }}</td>
                                    <td><strong class="{{ $saldoClass($contaBancaria->saldo_atual) }}">{{ $money($contaBancaria->saldo_atual) }}</strong></td>
                                    @if ($canManageFinance)
                                        <td>
                                            <div class="ff-bank-row-actions">
                                                <a class="ff-icon-action is-edit" href="{{ route('financeiro.contas.edit', $contaBancaria->id) }}" data-bank-edit-account="{{ $contaBancaria->id }}" title="Editar conta" aria-label="Editar conta {{ $contaBancaria->nome }}">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button class="ff-icon-action is-transfer" type="button" data-bank-transfer-origin="{{ $contaBancaria->id }}" @disabled($contasAtivas->count() < 2) title="Transferir desta conta" aria-label="Transferir desta conta {{ $contaBancaria->nome }}">
                                                    <i class="bi bi-arrow-left-right"></i>
                                                </button>
                                                <form method="POST" action="{{ route('financeiro.contas.toggle-status', $contaBancaria->id) }}" onsubmit="return confirm('Desativar esta conta? Ela deixará de aparecer nas contas ativas.');">
                                                    @csrf
                                                    <button class="ff-icon-action is-danger" type="submit" title="Desativar conta" aria-label="Desativar conta {{ $contaBancaria->nome }}">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManageFinance ? 6 : 5 }}" class="muted">Nenhuma conta ativa cadastrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel ff-bank-panel ff-bank-transfers-panel">
                <div class="panel-head">
                    <h2><i class="bi bi-arrow-left-right"></i> Transferências Recentes</h2>
                    @if ($canManageFinance)
                        <button class="btn small" type="button" data-bank-open-transfer @disabled($contasAtivas->count() < 2)>
                            <i class="bi bi-plus-lg"></i> Nova
                        </button>
                    @endif
                </div>

                <div class="table-wrap ff-bank-table-wrap">
                    <table class="ff-bank-table ff-bank-transfer-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Origem → Destino</th>
                                <th>Valor</th>
                                @if ($canManageFinance)
                                    <th>Ações</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transferencias as $transferencia)
                                <tr>
                                    <td>{{ \App\Support\FarmFormat::date($transferencia->data_transferencia) }}</td>
                                    <td>
                                        <strong>{{ $transferencia->origem_nome }} → {{ $transferencia->destino_nome }}</strong>
                                        <small>{{ $transferencia->descricao ?: 'Transferência entre contas' }}</small>
                                        <small>Registrada por {{ $transferencia->usuario_nome ?: 'usuário não informado' }}</small>
                                    </td>
                                    <td><strong class="is-positive">{{ $money($transferencia->valor) }}</strong></td>
                                    @if ($canManageFinance)
                                        <td>
                                            <a class="ff-icon-action is-edit" href="{{ route('financeiro.contas.transfer.edit', $transferencia->id) }}" data-bank-edit-transfer="{{ $transferencia->id }}" title="Editar transferência" aria-label="Editar transferência">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManageFinance ? 4 : 3 }}" class="muted">Nenhuma transferência registrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    @if ($canManageFinance)
        <div class="modal fade ff-bank-modal" id="bankAccountModal" tabindex="-1" aria-labelledby="bankAccountModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered ff-bank-account-dialog">
                <form method="POST" action="{{ route('financeiro.contas.store') }}" class="modal-content" data-bank-account-form>
                    @csrf
                    <input type="hidden" name="_method" value="PUT" data-bank-account-method disabled>
                    <input type="hidden" name="bank_form" value="account">
                    <input type="hidden" name="conta_id" data-bank-account-id>

                    <div class="modal-header modal-header-green">
                        <h5 class="modal-title" id="bankAccountModalLabel"><i class="bi bi-bank me-2"></i><span data-bank-account-title>Nova Conta</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="ff-bank-form-grid">
                            <label class="ff-field span-2">
                                <span>Nome *</span>
                                <input class="form-control" name="nome" required maxlength="100" placeholder="Ex: Banco Brasil Rui">
                            </label>

                            <label class="ff-field">
                                <span>Tipo *</span>
                                <select class="form-select" name="tipo" required>
                                    @foreach ($tiposConta as $value => $label)
                                        <option value="{{ $value }}">{{ ucfirst($label) }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="ff-field">
                                <span>Banco</span>
                                <input class="form-control" name="banco" maxlength="80" placeholder="Ex: Banco do Brasil">
                            </label>

                            <label class="ff-field">
                                <span>Agência</span>
                                <input class="form-control" name="agencia" maxlength="20">
                            </label>

                            <label class="ff-field">
                                <span>Número da conta</span>
                                <input class="form-control" name="numero_conta" maxlength="30">
                            </label>

                            <label class="ff-field span-2">
                                <span>Saldo inicial</span>
                                <input class="form-control" name="saldo_inicial" inputmode="decimal" value="0,00">
                            </label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn primary"><i class="bi bi-check2-square"></i> <span data-bank-account-submit>Salvar Conta</span></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade ff-bank-modal" id="bankTransferModal" tabindex="-1" aria-labelledby="bankTransferModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered ff-bank-transfer-dialog">
                <form method="POST" action="{{ route('financeiro.contas.transfer') }}" class="modal-content" data-bank-transfer-form>
                    @csrf
                    <input type="hidden" name="_method" value="PUT" data-bank-transfer-method disabled>
                    <input type="hidden" name="bank_form" value="transfer">
                    <input type="hidden" name="transferencia_id" data-bank-transfer-id>
                    <input type="hidden" name="return_route" value="financeiro.contas.index">

                    <div class="modal-header modal-header-green">
                        <h5 class="modal-title" id="bankTransferModalLabel"><i class="bi bi-arrow-left-right me-2"></i><span data-bank-transfer-title>Nova Transferência</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body">
                        @if ($contasAtivas->count() < 2)
                            <div class="alert alert-warning mb-3">Cadastre pelo menos duas contas ativas para registrar transferências.</div>
                        @endif

                        <div class="ff-bank-form-grid">
                            <label class="ff-field">
                                <span>Conta de Origem *</span>
                                <select class="form-select" name="origem" required data-transfer-origin>
                                    <option value="">Selecione...</option>
                                    @foreach ($contasAtivas as $contaTransferencia)
                                        <option value="{{ $contaTransferencia->id }}" data-saldo="{{ (float) $contaTransferencia->saldo_atual }}">
                                            {{ $contaTransferencia->nome }} - {{ $money($contaTransferencia->saldo_atual) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small data-transfer-origin-balance>Selecione a origem para ver o saldo disponível.</small>
                            </label>

                            <label class="ff-field">
                                <span>Conta de Destino *</span>
                                <select class="form-select" name="destino" required data-transfer-destino>
                                    <option value="">Selecione...</option>
                                    @foreach ($contasAtivas as $contaTransferencia)
                                        <option value="{{ $contaTransferencia->id }}">{{ $contaTransferencia->nome }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="span-2">
                                <button class="btn ff-bank-invert-button" type="button" data-bank-invert-transfer>
                                    <i class="bi bi-arrow-left-right"></i> Inverter origem e destino
                                </button>
                            </div>

                            <label class="ff-field">
                                <span>Valor (R$) *</span>
                                <input class="form-control" name="valor" inputmode="decimal" required placeholder="0,00">
                            </label>

                            <label class="ff-field">
                                <span>Data *</span>
                                <input class="form-control" type="date" name="data_transferencia" required value="{{ date('Y-m-d') }}">
                            </label>

                            <label class="ff-field span-2">
                                <span>Descrição</span>
                                <input class="form-control" name="descricao" maxlength="255" placeholder="Motivo da transferência">
                            </label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn primary" @disabled($contasAtivas->count() < 2)><i class="bi bi-arrow-left-right"></i> <span data-bank-transfer-submit>Registrar Transferência</span></button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@if ($canManageFinance)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const accounts = @json($contasPayload);
                const transfers = @json($transferenciasPayload);
                const accountStoreUrl = @json(route('financeiro.contas.store'));
                const transferStoreUrl = @json(route('financeiro.contas.transfer'));
                const oldForm = @json(old('bank_form'));
                const oldAccount = @json($oldAccount);
                const oldTransfer = @json($oldTransfer);
                const directTransferId = @json(isset($transferenciaEditando) ? (int) $transferenciaEditando->id : null);
                const directTransfer = @json($transferenciaEditandoPayload);

                const accountModalEl = document.getElementById('bankAccountModal');
                const transferModalEl = document.getElementById('bankTransferModal');
                const accountModal = accountModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(accountModalEl) : null;
                const transferModal = transferModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(transferModalEl) : null;
                const accountForm = document.querySelector('[data-bank-account-form]');
                const transferForm = document.querySelector('[data-bank-transfer-form]');

                function showModal(modal, el) {
                    if (modal) {
                        modal.show();
                        return;
                    }

                    if (!el) return;
                    el.style.display = 'block';
                    el.classList.add('show');
                    el.removeAttribute('aria-hidden');
                    document.body.classList.add('modal-open');
                }

                function setField(form, name, value) {
                    const field = form?.querySelector(`[name="${name}"]`);
                    if (field) field.value = value ?? '';
                }

                function methodInput(form, selector, enabled) {
                    const input = form?.querySelector(selector);
                    if (!input) return;
                    input.disabled = !enabled;
                }

                function accountById(id) {
                    return accounts.find((account) => Number(account.id) === Number(id));
                }

                function transferById(id) {
                    return transfers.find((transfer) => Number(transfer.id) === Number(id));
                }

                function openAccountModal(id = null, seed = null) {
                    if (!accountForm) return;

                    const existing = id ? accountById(id) : null;
                    const account = seed ? { ...(existing || {}), ...seed } : existing;
                    accountForm.reset();
                    accountForm.action = account?.id && account?.update_url ? account.update_url : accountStoreUrl;
                    methodInput(accountForm, '[data-bank-account-method]', Boolean(account?.id));
                    setField(accountForm, 'conta_id', account?.id || '');
                    setField(accountForm, 'nome', account?.nome || '');
                    setField(accountForm, 'tipo', account?.tipo || 'conta_corrente');
                    setField(accountForm, 'banco', account?.banco || '');
                    setField(accountForm, 'agencia', account?.agencia || '');
                    setField(accountForm, 'numero_conta', account?.numero_conta || '');
                    setField(accountForm, 'saldo_inicial', account?.saldo_inicial || '0,00');

                    accountModalEl.querySelector('[data-bank-account-title]').textContent = account?.id ? 'Editar Conta' : 'Nova Conta';
                    accountModalEl.querySelector('[data-bank-account-submit]').textContent = account?.id ? 'Salvar Alterações' : 'Salvar Conta';
                    showModal(accountModal, accountModalEl);
                }

                function updateOriginBalanceHint() {
                    const origin = transferForm?.querySelector('[data-transfer-origin]');
                    const hint = transferForm?.querySelector('[data-transfer-origin-balance]');
                    if (!origin || !hint) return;

                    const selected = origin.selectedOptions[0];
                    const saldo = selected?.dataset?.saldo;
                    hint.textContent = saldo === undefined || saldo === ''
                        ? 'Selecione a origem para ver o saldo disponível.'
                        : `Saldo disponível: ${Number(saldo).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`;
                }

                function openTransferModal(id = null, seed = null) {
                    if (!transferForm) return;

                    const existing = id ? transferById(id) : null;
                    const transfer = seed ? { ...(existing || {}), ...seed } : existing;
                    transferForm.reset();
                    transferForm.action = transfer?.id && transfer?.update_url ? transfer.update_url : transferStoreUrl;
                    methodInput(transferForm, '[data-bank-transfer-method]', Boolean(transfer?.id));
                    setField(transferForm, 'transferencia_id', transfer?.id || '');
                    setField(transferForm, 'origem', transfer?.origem || '');
                    setField(transferForm, 'destino', transfer?.destino || '');
                    setField(transferForm, 'valor', transfer?.valor || '');
                    setField(transferForm, 'data_transferencia', transfer?.data_transferencia || new Date().toISOString().slice(0, 10));
                    setField(transferForm, 'descricao', transfer?.descricao || '');

                    transferModalEl.querySelector('[data-bank-transfer-title]').textContent = transfer?.id ? 'Editar Transferência' : 'Nova Transferência';
                    transferModalEl.querySelector('[data-bank-transfer-submit]').textContent = transfer?.id ? 'Salvar Transferência' : 'Registrar Transferência';
                    updateOriginBalanceHint();
                    showModal(transferModal, transferModalEl);
                }

                document.querySelectorAll('[data-bank-open-account]').forEach((button) => {
                    button.addEventListener('click', () => openAccountModal());
                });

                document.querySelectorAll('[data-bank-open-transfer]').forEach((button) => {
                    button.addEventListener('click', () => openTransferModal());
                });

                document.querySelectorAll('[data-bank-edit-account]').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        openAccountModal(button.dataset.bankEditAccount);
                    });
                });

                document.querySelectorAll('[data-bank-transfer-origin]').forEach((button) => {
                    button.addEventListener('click', () => openTransferModal(null, { origem: button.dataset.bankTransferOrigin }));
                });

                document.querySelectorAll('[data-bank-edit-transfer]').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        openTransferModal(button.dataset.bankEditTransfer);
                    });
                });

                transferForm?.querySelector('[data-transfer-origin]')?.addEventListener('change', updateOriginBalanceHint);

                transferForm?.querySelector('[data-bank-invert-transfer]')?.addEventListener('click', () => {
                    const origin = transferForm.querySelector('[data-transfer-origin]');
                    const destino = transferForm.querySelector('[data-transfer-destino]');
                    const currentOrigin = origin.value;
                    origin.value = destino.value;
                    destino.value = currentOrigin;
                    updateOriginBalanceHint();
                });

                if (oldForm === 'account') {
                    openAccountModal(oldAccount.id || null, oldAccount);
                } else if (oldForm === 'transfer') {
                    openTransferModal(oldTransfer.id || null, oldTransfer);
                } else if (directTransferId) {
                    openTransferModal(directTransferId, directTransfer);
                }
            });
        </script>
    @endpush
@endif

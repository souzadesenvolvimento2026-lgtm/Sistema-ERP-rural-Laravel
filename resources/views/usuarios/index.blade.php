@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="ff-users-page">
        <section class="panel ff-users-heading-panel">
            <div class="panel-head">
                <h2><i class="bi bi-people"></i> {{ $panelTitle ?? 'Usuários da propriedade' }}</h2>
                <div class="actions">
                    <a class="btn outline-info" href="{{ route('auditoria.index', ['lancamento' => 'usuarios']) }}">
                        <i class="bi bi-shield-check"></i> Auditoria
                    </a>
                    <button type="button" class="btn primary" data-bs-toggle="modal" data-bs-target="#usuarioModal" data-user-create>
                        <i class="bi bi-plus-lg"></i> Novo Usuário
                    </button>
                </div>
            </div>
        </section>

        <section class="panel ff-users-table-panel">
            <div class="panel-head">
                <h2><i class="bi bi-person-lines-fill"></i> {{ $tableTitle ?? 'Usuários e permissões por fazenda' }}</h2>
            </div>

            @include('usuarios.partials.tabela')
        </section>
    </div>

    <div class="modal fade ff-user-modal" id="usuarioModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered ff-user-modal-dialog">
            <form method="post" action="{{ route('usuarios.store') }}" class="modal-content" data-user-form>
                @csrf
                <input type="hidden" name="_method" value="PUT" data-user-method disabled>
                <div class="modal-header">
                    <h5 class="modal-title" data-user-modal-title><i class="bi bi-person-plus me-2"></i>Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="ff-user-modal-grid">
                        <label class="field wide">
                            <span>Nome *</span>
                            <input name="nome" data-user-field="nome" required autocomplete="name">
                        </label>

                        <label class="field wide">
                            <span>E-mail *</span>
                            <input type="email" name="email" data-user-field="email" required autocomplete="email">
                        </label>

                        <label class="field">
                            <span>Perfil *</span>
                            <select name="perfil" data-user-field="perfil" required>
                                @foreach ($perfis as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="field">
                            <span data-user-password-label>Senha *</span>
                            <input type="password" name="senha" data-user-field="senha" autocomplete="new-password" required>
                        </label>

                        <label class="field">
                            <span data-user-password-confirmation-label>Confirmar senha *</span>
                            <input type="password" name="senha_confirmation" data-user-field="senha_confirmation" autocomplete="new-password" required>
                        </label>
                    </div>
                    <p class="ff-user-modal-help">
                        A senha nunca é exibida nem gravada em auditoria. Em edição, preencha somente se quiser alterar.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn primary"><i class="bi bi-check2-square"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('usuarioModal');
            if (!modal) return;

            var form = modal.querySelector('[data-user-form]');
            var method = modal.querySelector('[data-user-method]');
            var title = modal.querySelector('[data-user-modal-title]');
            var password = modal.querySelector('[data-user-field="senha"]');
            var passwordConfirmation = modal.querySelector('[data-user-field="senha_confirmation"]');
            var passwordLabel = modal.querySelector('[data-user-password-label]');
            var passwordConfirmationLabel = modal.querySelector('[data-user-password-confirmation-label]');
            var createAction = @js(route('usuarios.store'));
            var defaultProfile = @js(array_key_first($perfis));

            function setField(name, value) {
                var field = modal.querySelector('[data-user-field="' + name + '"]');
                if (field) field.value = value || '';
            }

            function setCreateMode() {
                form.action = createAction;
                if (method) method.disabled = true;
                if (title) title.innerHTML = '<i class="bi bi-person-plus me-2"></i>Novo Usuário';
                setField('nome', '');
                setField('email', '');
                setField('perfil', defaultProfile || 'visualizador');
                setField('senha', '');
                setField('senha_confirmation', '');
                if (password) password.required = true;
                if (passwordConfirmation) passwordConfirmation.required = true;
                if (passwordLabel) passwordLabel.textContent = 'Senha *';
                if (passwordConfirmationLabel) passwordConfirmationLabel.textContent = 'Confirmar senha *';
            }

            function setEditMode(button) {
                var user = {};
                try {
                    user = JSON.parse(button.getAttribute('data-user') || '{}');
                } catch (e) {
                    user = {};
                }

                form.action = button.getAttribute('data-action') || createAction;
                if (method) method.disabled = false;
                if (title) title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Usuário';
                setField('nome', user.nome || '');
                setField('email', user.email || '');
                setField('perfil', user.perfil_key || defaultProfile || 'visualizador');
                setField('senha', '');
                setField('senha_confirmation', '');
                if (password) password.required = false;
                if (passwordConfirmation) passwordConfirmation.required = false;
                if (passwordLabel) passwordLabel.textContent = 'Nova senha opcional';
                if (passwordConfirmationLabel) passwordConfirmationLabel.textContent = 'Confirmar nova senha';
            }

            document.querySelectorAll('[data-user-create]').forEach(function (button) {
                button.addEventListener('click', setCreateMode);
            });

            document.querySelectorAll('[data-user-edit]').forEach(function (button) {
                button.addEventListener('click', function () {
                    setEditMode(button);
                });
            });

            modal.addEventListener('hidden.bs.modal', setCreateMode);
            setCreateMode();
        });
    </script>
@endpush

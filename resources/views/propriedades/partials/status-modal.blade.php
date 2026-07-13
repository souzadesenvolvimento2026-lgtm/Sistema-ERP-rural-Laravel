<div class="modal fade ff-property-modal" id="propertyStatusModal-{{ $row->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-property-status-dialog">
        <form method="POST" action="{{ route('propriedades.toggle-status', $row->id) }}" class="modal-content ff-property-modal-content">
            @csrf
            <div class="modal-header {{ $row->ativo ? 'ff-modal-header-danger' : 'modal-header-green' }}">
                <h5 class="modal-title">
                    <i class="bi {{ $row->ativo ? 'bi-lock-fill' : 'bi-unlock-fill' }} me-2"></i>
                    {{ $row->ativo ? 'Desativar propriedade' : 'Reativar propriedade' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                @if ($row->ativo)
                    <div class="ff-property-danger-note">
                        <strong>{{ $row->nome }}</strong> será desativada. Dados, usuários, grupos, histórico financeiro, fiscal, talhões e safras serão preservados.
                        Usuários vinculados não acessarão esta propriedade enquanto ela estiver inativa.
                    </div>
                @else
                    <div class="ff-property-note">
                        <strong>{{ $row->nome }}</strong> será reativada e voltará a aparecer para usuários vinculados com permissão.
                    </div>
                @endif

                <label class="ff-property-field mt-3">
                    <span>Senha do administrador / gerência do sistema *</span>
                    <input type="password" name="admin_password" required autocomplete="current-password">
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn {{ $row->ativo ? 'danger' : 'primary' }}">
                    <i class="bi {{ $row->ativo ? 'bi-lock-fill' : 'bi-unlock-fill' }}"></i>
                    {{ $row->ativo ? 'Desativar' : 'Reativar' }}
                </button>
            </div>
        </form>
    </div>
</div>

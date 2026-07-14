<div class="table-wrap ff-users-table-wrap">
    <table class="ff-users-table datatable" data-default-order='[[0,"asc"]]'>
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Perfil</th>
                <th>Acesso efetivo</th>
                <th>Vínculo direto</th>
                <th>Grupos</th>
                <th>Último acesso</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $userPayload = [
                        'id' => $row->id,
                        'nome' => $row->nome,
                        'email' => $row->email,
                        'perfil_key' => $row->perfil_key,
                    ];
                @endphp
                <tr>
                    <td><strong>{{ $row->nome }}</strong></td>
                    <td>{{ $row->email }}</td>
                    <td><span class="pill">{{ $row->perfil }}</span></td>
                    <td>{{ $row->acesso_efetivo }}</td>
                    <td>{{ $row->vinculo_direto }}</td>
                    <td>{{ $row->grupos }}</td>
                    <td>{{ $row->ultimo_acesso }}</td>
                    <td><span class="pill {{ $row->ativo ? 'success' : 'danger' }}">{{ $row->status }}</span></td>
                    <td>
                        <div class="ff-user-row-actions">
                            <button
                                type="button"
                                class="btn icon small ff-user-action-edit"
                                title="Editar usuário"
                                data-bs-toggle="modal"
                                data-bs-target="#usuarioModal"
                                data-user-edit
                                data-action="{{ route('usuarios.update', $row->id) }}"
                                data-user='@json($userPayload)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" action="{{ route('usuarios.toggle-status', $row->id) }}">
                                @csrf
                                <button class="btn icon small {{ $row->ativo ? 'danger' : 'primary' }}" type="submit" title="{{ $row->ativo ? 'Desativar usuário' : 'Ativar usuário' }}">
                                    <i class="bi {{ $row->ativo ? 'bi-x' : 'bi-check2' }}"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">{{ $emptyMessage ?? 'Nenhum usuário encontrado para a propriedade atual.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

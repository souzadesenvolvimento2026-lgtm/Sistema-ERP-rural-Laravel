<section class="panel">
    <div class="panel-head">
        <h2>{{ $tableTitle ?? 'Usuários da propriedade' }}</h2>
        <span class="badge">{{ $rows->count() }} usuário(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Último acesso</th>
                    <th>Criado em</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><strong>{{ $row->nome }}</strong></td>
                        <td>{{ $row->email }}</td>
                        <td><span class="pill">{{ $row->perfil }}</span></td>
                        <td>{{ $row->ultimo_acesso }}</td>
                        <td>{{ $row->criado_em }}</td>
                        <td><span class="pill {{ $row->ativo ? 'success' : '' }}">{{ $row->status }}</span></td>
                        <td>
                            <div class="actions">
                                <a class="btn small" href="{{ route('usuarios.edit', $row->id) }}">Editar</a>
                                <form method="post" action="{{ route('usuarios.toggle-status', $row->id) }}">
                                    @csrf
                                    <button class="btn small" type="submit">{{ $row->ativo ? 'Inativar' : 'Ativar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ $emptyMessage ?? 'Nenhum usuário encontrado para a propriedade atual.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-head"><h2>Grupos cadastrados</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Fazendas</th>
                    <th>Aprovador</th>
                    <th>Usuários</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($grupos as $grupo)
                    <tr>
                        <td><strong>{{ $grupo->nome }}</strong><br><span class="muted">{{ $grupo->descricao ?: '-' }}</span></td>
                        <td><strong>{{ $grupo->qtd_propriedades }} fazenda(s)</strong><br><span class="muted">{{ $grupo->propriedades_nomes ?: '-' }}</span></td>
                        <td>{{ $grupo->aprovador_nome ?: 'Padrão admin' }}</td>
                        <td>{{ $grupo->qtd_usuarios }}</td>
                        <td>
                            <details>
                                <summary class="btn">Editar</summary>
                                <form method="POST" action="{{ route('propriedades.grupos.update', $grupo->id) }}" class="form-grid compact-form">
                                    @csrf
                                    @method('PUT')
                                    @include('propriedades.grupos.partials.form-fields', ['grupo' => $grupo])
                                    <div class="form-actions">
                                        <button class="btn primary" type="submit">Atualizar</button>
                                    </div>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('propriedades.grupos.destroy', $grupo->id) }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit">Desativar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Nenhum grupo cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

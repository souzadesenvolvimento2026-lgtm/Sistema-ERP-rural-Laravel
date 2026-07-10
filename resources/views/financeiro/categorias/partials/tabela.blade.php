<section class="panel">
    <div class="panel-head"><h2>Categorias cadastradas</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Principal/Subcategoria</th>
                    <th>Tipo</th>
                    <th>Cor</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categorias as $categoria)
                    <tr>
                        <td><strong>{{ $categoria->nome }}</strong></td>
                        <td>{{ $categoria->categoria_pai_nome ? 'Subcategoria de '.$categoria->categoria_pai_nome : 'Categoria principal' }}</td>
                        <td>{{ ucfirst($categoria->tipo) }}</td>
                        <td><span class="color-chip" style="background: {{ $categoria->cor }}"></span>{{ $categoria->cor }}</td>
                        <td><span class="status {{ $categoria->ativo ? 'open' : '' }}">{{ $categoria->ativo ? 'Ativa' : 'Inativa' }}</span></td>
                        <td>
                            <details>
                                <summary class="btn">Editar</summary>
                                <form method="POST" action="{{ route('financeiro.categorias.update', $categoria->id) }}" class="form-grid compact-form">
                                    @csrf
                                    @method('PUT')
                                    @include('financeiro.categorias.partials.form-fields', ['categoria' => $categoria])
                                    <div class="form-actions">
                                        <button class="btn primary" type="submit">Atualizar</button>
                                    </div>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('financeiro.categorias.destroy', $categoria->id) }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

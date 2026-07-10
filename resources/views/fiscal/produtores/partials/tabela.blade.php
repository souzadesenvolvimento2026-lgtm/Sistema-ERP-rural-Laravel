<section class="panel">
    <div class="panel-head"><h2>Produtores da propriedade</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF/CNPJ</th>
                    <th>Participação</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($produtores as $produtor)
                    <tr>
                        <td><strong>{{ $produtor->nome }}</strong></td>
                        <td>{{ $produtor->documento ?: '-' }}</td>
                        <td>{{ $produtor->participacao_percentual !== null ? number_format($produtor->participacao_percentual, 2, ',', '.') . '%' : '-' }}</td>
                        <td><span class="status {{ $produtor->ativo ? 'open' : '' }}">{{ $produtor->ativo ? 'Ativo' : 'Inativo' }}</span></td>
                        <td>
                            <div class="inline-actions">
                                <details>
                                    <summary class="btn small">Editar</summary>
                                    <form method="POST" action="{{ route('fiscal.produtores.update', $produtor->id) }}" class="form-grid compact-form">
                                        @csrf
                                        @method('PUT')
                                        @include('fiscal.produtores.partials.form-fields', ['produtor' => $produtor])
                                        <div class="form-actions">
                                            <button class="btn primary" type="submit">Atualizar</button>
                                        </div>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('fiscal.produtores.toggle', $produtor->id) }}">
                                    @csrf
                                    <button class="btn small" type="submit">{{ $produtor->ativo ? 'Inativar' : 'Ativar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Nenhum produtor cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

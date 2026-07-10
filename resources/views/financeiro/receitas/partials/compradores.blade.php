<section class="panel">
    <div class="panel-head">
        <h2>Compradores</h2>
        <span class="badge">{{ $compradores->count() }} ativo(s)</span>
    </div>
    <form method="POST" action="{{ route('financeiro.receitas.compradores.store') }}" class="form-grid panel-body">
        @csrf

        <label>
            Nome do comprador *
            <input name="nome" value="{{ old('nome') }}" maxlength="150" required>
        </label>

        <label>
            Documento
            <input name="documento" value="{{ old('documento') }}" maxlength="30">
        </label>

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar comprador</button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Documento</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($compradores as $comprador)
                    <tr>
                        <td><strong>{{ $comprador->nome }}</strong></td>
                        <td>{{ $comprador->documento ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">Nenhum comprador cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

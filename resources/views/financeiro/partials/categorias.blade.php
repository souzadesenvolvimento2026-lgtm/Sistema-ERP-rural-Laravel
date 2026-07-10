<section class="panel">
    <div class="panel-head">
        <h2>Custos por categoria</h2>
        <a class="btn" href="{{ route('financeiro.analise-despesas.index') }}">Analise</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categorias as $categoria)
                    <tr>
                        <td>{{ $categoria->nome }}</td>
                        <td><strong>{{ $categoria->total }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">Sem custos lancados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

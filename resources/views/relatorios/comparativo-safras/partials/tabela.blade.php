<section class="panel">
    <div class="panel-head"><h2>Comparativo</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    @foreach ($safras as $safra)
                        <th>{{ $safra->descricao }} ({{ $modo === 'sacas_ha' ? 'sc/ha' : 'R$/ha' }})</th>
                    @endforeach
                    <th>Media</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $linha)
                    <tr>
                        <td><strong class="{{ $linha->grupo ? 'success' : '' }}">{{ $linha->nome }}</strong></td>
                        @foreach ($safras as $safra)
                            <td>{{ $linha->valores[(int)$safra->id] ?? '0,00' }}</td>
                        @endforeach
                        <td><strong>{{ $linha->media }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $safras->count() + 2 }}" class="muted">Nenhuma safra encontrada para os filtros selecionados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

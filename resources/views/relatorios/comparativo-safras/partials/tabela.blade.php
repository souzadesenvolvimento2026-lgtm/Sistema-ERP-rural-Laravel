<section class="panel ff-safra-comparison-panel">
    <div class="table-wrap">
        <table class="ff-safra-comparison-table" data-comparativo-safras>
            <thead>
                <tr>
                    <th>Categoria</th>
                    @foreach ($safras as $safra)
                        <th>{{ $safra->descricao }} ({{ $modo === 'sacas_ha' ? 'sc/ha' : 'R$/ha' }})</th>
                    @endforeach
                    <th>Média ({{ $modo === 'sacas_ha' ? 'sc/ha' : 'R$/ha' }})</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $linha)
                    <tr @class([
                        'ff-comparison-group-row' => $linha->grupo,
                        'ff-comparison-detail-row' => ! $linha->grupo,
                    ])
                        @unless($linha->grupo)
                            data-comparison-child="{{ $linha->parent_key }}" hidden
                        @endunless
                    >
                        <td>
                            @if ($linha->grupo && $linha->tem_filhos)
                                <button
                                    type="button"
                                    class="ff-comparison-toggle"
                                    data-comparison-toggle="{{ $linha->key }}"
                                    aria-expanded="false"
                                >
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                    <span>{{ $linha->nome }}</span>
                                </button>
                            @elseif ($linha->grupo)
                                <strong class="ff-comparison-label ff-comparison-label-group">{{ $linha->nome }}</strong>
                            @else
                                <span class="ff-comparison-label ff-comparison-label-child">{{ $linha->nome }}</span>
                            @endif
                        </td>
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

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-comparativo-safras]').forEach((table) => {
                    table.querySelectorAll('[data-comparison-toggle]').forEach((button) => {
                        button.addEventListener('click', () => {
                            const key = button.dataset.comparisonToggle;
                            const expanded = button.getAttribute('aria-expanded') === 'true';

                            button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                            table.querySelectorAll('[data-comparison-child]').forEach((row) => {
                                if (row.dataset.comparisonChild === key) {
                                    row.hidden = expanded;
                                }
                            });
                        });
                    });
                });
            });
        </script>
    @endpush
@endonce

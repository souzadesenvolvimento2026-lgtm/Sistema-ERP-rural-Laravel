<section class="panel">
    <div class="panel-head"><h2>Registros</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Data</th><th>Talhão</th><th>Volume</th><th>Fonte</th><th>Observações</th></tr></thead>
            <tbody>
                @forelse ($registros as $registro)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($registro->data_chuva)->format('d/m/Y') }}</td>
                        <td>{{ $registro->talhao_nome ?: 'Fazenda geral' }}</td>
                        <td><strong>{{ number_format($registro->volume_mm, 1, ',', '.') }} mm</strong></td>
                        <td>{{ $registro->fonte }}</td>
                        <td>{{ $registro->observacoes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Nenhum registro de chuva no ano filtrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Central de atendimento</h2>
        <span class="badge">{{ $conversas->count() }} conversa(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Assunto</th>
                    <th>Cliente</th>
                    <th>Propriedade</th>
                    <th>Atendente</th>
                    <th>Nivel</th>
                    <th>Status</th>
                    <th>Atualizada</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($conversas as $conversa)
                    <tr>
                        <td>{{ $conversa->assunto }}</td>
                        <td>{{ $conversa->cliente_nome ?: '-' }}</td>
                        <td>{{ $conversa->propriedade_nome ?: '-' }}</td>
                        <td>{{ $conversa->atendente_nome ?: 'Nao assumido' }}</td>
                        <td>{{ $conversa->nivel_atendimento }}</td>
                        <td>{{ $conversa->status }}</td>
                        <td>{{ \App\Support\FarmFormat::date($conversa->atualizada_em) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Nenhuma conversa de suporte encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-head"><h2>Ultimas respostas</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Atendente</th>
                    <th>Cliente</th>
                    <th>Propriedade</th>
                    <th>Resumo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ultimas as $linha)
                    <tr>
                        <td>{{ $linha->criada_em_fmt }}</td>
                        <td>{{ $linha->atendente_nome ?: 'Atendente removido' }}<br><span class="muted">{{ $linha->atendente_email ?: '-' }}</span></td>
                        <td>{{ $linha->cliente_nome ?: '-' }}</td>
                        <td>{{ $linha->propriedade_nome ?: '-' }}</td>
                        <td>{{ $linha->resumo }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Nenhuma resposta registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

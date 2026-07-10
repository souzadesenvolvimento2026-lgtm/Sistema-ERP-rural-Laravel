<section class="panel">
    <div class="panel-head"><h2>Atividades registradas</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Início</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th>Safra</th>
                    <th>Talhão</th>
                    <th>Status</th>
                    <th>Custo</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($atividades as $atividade)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($atividade->data_inicio)->format('d/m/Y') }}</td>
                        <td>{{ $tipos[$atividade->tipo] ?? $atividade->tipo }}</td>
                        <td>
                            <strong>{{ $atividade->descricao }}</strong>
                            <br><span class="muted">{{ $atividade->produto ?: $atividade->servico ?: '-' }}</span>
                        </td>
                        <td>{{ $atividade->safra_nome ?: '-' }}</td>
                        <td>{{ $atividade->talhao_nome ?: 'Fazenda geral' }}</td>
                        <td>{{ $statusOptions[$atividade->status] ?? $atividade->status }}</td>
                        <td>R$ {{ number_format($atividade->custo_estimado, 2, ',', '.') }}</td>
                        <td>
                            @if ($atividade->status !== 'concluida')
                                <form method="POST" action="{{ route('talhoes.atividades.status', $atividade->id) }}" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="status" value="concluida">
                                    <button class="btn" type="submit">Concluir</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Nenhuma atividade registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

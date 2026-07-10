<section class="panel">
    <div class="panel-head"><h2>Extrato e conciliação</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Conta</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th>Valor</th>
                    <th>Origem</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movimentacoes as $movimentacao)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($movimentacao->data_movimento)->format('d/m/Y') }}</td>
                        <td>{{ $movimentacao->conta_nome }}</td>
                        <td><span class="pill {{ $movimentacao->tipo === 'entrada' ? 'success' : 'warning' }}">{{ \App\Support\FarmFormat::statusLabel($movimentacao->tipo) }}</span></td>
                        <td>{{ $movimentacao->descricao }}</td>
                        <td><strong>R$ {{ number_format($movimentacao->valor, 2, ',', '.') }}</strong></td>
                        <td>{{ strtoupper($movimentacao->origem) }}</td>
                        <td><span class="pill {{ $movimentacao->status === 'pendente' ? 'warning' : 'success' }}">{{ \App\Support\FarmFormat::statusLabel($movimentacao->status) }}</span></td>
                        <td>
                            @if ($movimentacao->status === 'pendente')
                                <form method="POST" action="{{ route('financeiro.movimentacoes.conciliar', $movimentacao->id) }}" style="display: inline;">
                                    @csrf
                                    <button class="btn" type="submit">Conciliar</button>
                                </form>
                                <form method="POST" action="{{ route('financeiro.movimentacoes.ignorar', $movimentacao->id) }}" style="display: inline;">
                                    @csrf
                                    <button class="btn" type="submit">Ignorar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Nenhuma movimentação bancária encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

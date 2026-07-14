<section class="panel">
    <div class="panel-head">
        <h2>Saldos por conta</h2>
        <a class="btn" href="{{ route('financeiro.contas.index', [], false) }}">Contas</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Conta</th>
                    <th>Detalhe</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contas as $conta)
                    <tr>
                        <td><strong>{{ $conta->nome }}</strong></td>
                        <td>{{ $conta->detalhe }}</td>
                        <td><strong>{{ $conta->saldo }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Nenhuma conta ativa cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

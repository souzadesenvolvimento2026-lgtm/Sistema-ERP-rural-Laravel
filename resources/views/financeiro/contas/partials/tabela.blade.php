<section class="panel">
    <div class="panel-head"><h2>Contas cadastradas</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Banco</th>
                    <th>Agência</th>
                    <th>Conta</th>
                    <th>Saldo inicial</th>
                    <th>Saldo atual</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contas as $contaBancaria)
                    <tr>
                        <td><strong>{{ $contaBancaria->nome }}</strong></td>
                        <td>{{ str_replace('_', ' ', $contaBancaria->tipo) }}</td>
                        <td>{{ $contaBancaria->banco ?: '-' }}</td>
                        <td>{{ $contaBancaria->agencia ?: '-' }}</td>
                        <td>{{ $contaBancaria->numero_conta ?: '-' }}</td>
                        <td><strong>R$ {{ number_format($contaBancaria->saldo_inicial, 2, ',', '.') }}</strong></td>
                        <td><strong>R$ {{ number_format($contaBancaria->saldo_atual, 2, ',', '.') }}</strong></td>
                        <td><span class="status {{ $contaBancaria->ativo ? 'open' : '' }}">{{ $contaBancaria->ativo ? 'Ativa' : 'Inativa' }}</span></td>
                        <td>
                            <div class="inline-actions">
                                <a class="btn small" href="{{ route('financeiro.contas.edit', $contaBancaria->id) }}">Editar</a>
                                <form method="POST" action="{{ route('financeiro.contas.toggle-status', $contaBancaria->id) }}">
                                    @csrf
                                    <button class="btn small" type="submit">{{ $contaBancaria->ativo ? 'Inativar' : 'Ativar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Nenhuma conta cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

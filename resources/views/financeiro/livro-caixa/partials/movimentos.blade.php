<section class="panel">
    <div class="panel-head">
        <h2>Movimentos</h2>
        <span class="badge">{{ $resumo['total_lancamentos'] }} registro(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Histórico</th>
                    <th>Pessoa</th>
                    <th>Documento</th>
                    <th>Categoria</th>
                    <th>Safra</th>
                    <th>Conta</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Comprovante</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movimentos as $movimento)
                    <tr>
                        <td>{{ \App\Support\FarmFormat::date($movimento->data_mov) }}</td>
                        <td><span class="pill {{ $movimento->tipo === 'Entrada' ? 'success' : 'danger' }}">{{ $movimento->tipo }}</span></td>
                        <td>
                            {{ $movimento->descricao }}
                            @if ($movimento->talhao)
                                <br><span class="muted">Talhão: {{ $movimento->talhao }}</span>
                            @endif
                        </td>
                        <td>{{ $movimento->pessoa ?: '-' }}</td>
                        <td>{{ $movimento->documento ?: '-' }}</td>
                        <td>{{ $movimento->categoria ?: '-' }}</td>
                        <td>{{ $movimento->safra ?: '-' }}</td>
                        <td>{{ $movimento->conta ?: '-' }}</td>
                        <td><strong class="success">{{ $movimento->entrada > 0 ? \App\Support\FarmFormat::money($movimento->entrada) : '-' }}</strong></td>
                        <td><strong class="danger">{{ $movimento->saida > 0 ? \App\Support\FarmFormat::money($movimento->saida) : '-' }}</strong></td>
                        <td>{{ $movimento->tem_comprovante ? 'Com arquivo' : 'Sem arquivo' }}</td>
                        <td>{{ $movimento->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="muted">Nenhum movimento encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

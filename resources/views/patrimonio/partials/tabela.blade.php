<section class="panel">
    <div class="panel-head">
        <h2>Patrimônios cadastrados</h2>
        <span class="badge">{{ $rows->count() }} registro(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Modelo/identificação</th>
                    <th>Ano</th>
                    <th>Aquisição</th>
                    <th>Fornecedor</th>
                    <th>Medidores</th>
                    <th>Custo</th>
                    <th>Combustível</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->nome }}</strong>
                            @if ($row->descricao !== '-')
                                <br><span class="muted">{{ $row->descricao }}</span>
                            @endif
                        </td>
                        <td>{{ $row->tipo }}</td>
                        <td>
                            {{ $row->marca_modelo }}
                            @if ($row->identificacao !== '-')
                                <br><span class="muted">{{ $row->identificacao }}</span>
                            @endif
                        </td>
                        <td>{{ $row->ano }}</td>
                        <td>
                            <strong>{{ $row->valor_aquisicao }}</strong>
                            <br><span class="muted">{{ $row->data_aquisicao }}</span>
                        </td>
                        <td>{{ $row->fornecedor }}</td>
                        <td>
                            {{ $row->horimetro }}
                            @if ($row->odometro !== '-')
                                <br><span class="muted">{{ $row->odometro }}</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $row->custo_total }}</strong>
                            <br><a class="muted" href="{{ route('patrimonio.show', $row->id) }}">{{ $row->lancamentos_count }} lanç.</a>
                        </td>
                        <td>{{ $row->combustivel }}</td>
                        <td><span class="pill {{ $row->ativo ? 'success' : 'danger' }}">{{ $row->status }}</span></td>
                        <td>
                            <div class="inline-actions">
                                <a class="btn small" href="{{ route('patrimonio.show', $row->id) }}">Abrir</a>
                                <a class="btn small" href="{{ route('patrimonio.edit', $row->id) }}">Editar</a>
                                <form method="post" action="{{ route('patrimonio.toggle-status', $row->id) }}">
                                    @csrf
                                    <button class="btn small" type="submit">{{ $row->ativo ? 'Inativar' : 'Reativar' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">Nenhum patrimônio encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

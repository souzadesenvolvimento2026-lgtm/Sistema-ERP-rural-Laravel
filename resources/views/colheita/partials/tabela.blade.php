<section class="panel">
    <div class="panel-head">
        <h2>Cargas de colheita</h2>
        <span class="badge">{{ $rows->count() }} carga(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Ticket</th>
                    <th>Safra/Cultura</th>
                    <th>Talhão</th>
                    <th>Motorista</th>
                    <th>Destino</th>
                    <th>Peso final</th>
                    <th>Sacas</th>
                    <th>Produtividade</th>
                    <th>Qualidade</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->data }}</td>
                        <td>{{ $row->ticket }}</td>
                        <td>
                            {{ $row->safra }}
                            @if ($row->cultura !== '-')
                                <br><span class="muted">{{ $row->cultura }}</span>
                            @endif
                        </td>
                        <td>{{ $row->talhao }}</td>
                        <td>
                            {{ $row->motorista }}
                            @if ($row->veiculo !== '-')
                                <br><span class="muted">{{ $row->veiculo }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $row->destino }}
                            @if ($row->local_destino !== '-')
                                <br><span class="muted">{{ $row->local_destino }}</span>
                            @endif
                        </td>
                        <td><strong>{{ $row->peso_final }}</strong></td>
                        <td>{{ $row->sacas }}</td>
                        <td>{{ $row->produtividade }}</td>
                        <td>{{ $row->umidade }} / {{ $row->impureza }}</td>
                        <td>
                            <div class="inline-actions">
                                <a class="btn small" href="{{ route('colheita.edit', $row->id) }}">Editar</a>
                                <form method="POST" action="{{ route('colheita.destroy', $row->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn small danger" type="submit">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">Nenhuma carga de colheita encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

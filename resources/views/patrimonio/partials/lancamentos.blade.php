<section class="panel">
    <div class="panel-head">
        <h2>Historico de lancamentos</h2>
        <span class="badge">{{ $lancamentos->count() }} lancamento(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descricao</th>
                    <th>Fornecedor/Safra</th>
                    <th>Talhao</th>
                    <th>Qtd.</th>
                    <th>Valor unit.</th>
                    <th>Total</th>
                    <th>Marcadores</th>
                    <th>Comprovante</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lancamentos as $row)
                    <tr>
                        <td>{{ $row->data }}</td>
                        <td>{{ $row->tipo }}</td>
                        <td>
                            <strong>{{ $row->descricao }}</strong>
                            @if ($row->observacoes !== '-')
                                <br><span class="muted">{{ $row->observacoes }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $row->fornecedor }}
                            @if ($row->safra !== '-')
                                <br><span class="muted">{{ $row->safra }}</span>
                            @endif
                        </td>
                        <td>{{ $row->talhao }}</td>
                        <td>{{ $row->quantidade }}</td>
                        <td>{{ $row->valor_unitario }}</td>
                        <td><strong>{{ $row->valor }}</strong></td>
                        <td>
                            {{ $row->horimetro }}
                            @if ($row->odometro !== '-')
                                <br><span class="muted">{{ $row->odometro }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($row->comprovante !== '')
                                <a class="btn small" href="{{ asset('uploads/comprovantes/'.$row->comprovante) }}" target="_blank">Abrir</a>
                            @else
                                <span class="muted">Sem arquivo</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Nenhum lancamento encontrado para este patrimonio.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

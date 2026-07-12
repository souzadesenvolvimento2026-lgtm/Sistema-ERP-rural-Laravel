<section class="panel">
    <div class="panel-head"><h2>Central de documentos</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Título</th>
                    <th>Número</th>
                    <th>Pessoa</th>
                    <th>Safra</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Arquivo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documentos as $documento)
                    <tr>
                        <td>{{ $documento->data_documento ? \Illuminate\Support\Carbon::parse($documento->data_documento)->format('d/m/Y') : '-' }}</td>
                        <td>{{ \App\Support\FarmFormat::statusLabel($documento->tipo) }}</td>
                        <td><strong>{{ $documento->titulo }}</strong><br><span class="muted">{{ $documento->usuario_nome ?: '-' }}</span></td>
                        <td>{{ $documento->numero ?: '-' }}</td>
                        <td>{{ $documento->pessoa ?: '-' }}</td>
                        <td>{{ $documento->safra_nome ?: '-' }}</td>
                        <td>{{ $documento->valor > 0 ? 'R$ '.number_format($documento->valor, 2, ',', '.') : '-' }}</td>
                        <td><span class="status {{ $documento->status_tone }}">{{ $documento->status_label }}</span></td>
                        <td>
                            @if ($documento->has_file)
                                <a class="btn small" href="{{ route('fiscal.documentos.arquivo', $documento->id) }}">Abrir</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="inline-actions">
                                @foreach ($documento->actions as $action)
                                    <form method="POST" action="{{ route($action['route_name'], $documento->id) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $action['target_status'] }}">
                                        <button class="btn small" type="submit">{{ $action['label'] }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Nenhum documento cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

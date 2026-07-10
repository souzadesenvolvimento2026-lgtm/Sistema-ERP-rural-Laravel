<section class="panel">
    <div class="panel-head">
        <h2>Agenda financeira</h2>
        <a class="btn" href="{{ route('financeiro.agenda.index') }}">Abrir agenda</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Titulo</th>
                    <th>Pessoa</th>
                    <th>Data</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agenda as $item)
                    <tr>
                        <td><span class="pill {{ $item->tipo === 'receber' ? 'success' : 'danger' }}">{{ $item->tipo === 'receber' ? 'Receber' : 'Pagar' }}</span></td>
                        <td>{{ $item->titulo }}</td>
                        <td>{{ $item->pessoa }}</td>
                        <td>{{ $item->data }}</td>
                        <td><strong>{{ $item->valor }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Sem contas pendentes no momento.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

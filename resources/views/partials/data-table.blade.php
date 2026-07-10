<div class="table-wrap">
    <table>
        <thead>
            <tr>
                @foreach ($columns as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $field => $label)
                        <td>{{ \App\Support\FarmFormat::value($row->{$field} ?? null) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="muted">Nenhum registro encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

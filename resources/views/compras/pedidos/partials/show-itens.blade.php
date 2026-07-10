<section class="panel">
    <div class="panel-head"><h2>Itens do pedido</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Patrimônio</th>
                    <th>Uso</th>
                    <th>Un.</th>
                    <th>Qtd.</th>
                    <th>Valor unit.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->product_code ?: '-' }}</td>
                        <td><strong>{{ $item->description }}</strong></td>
                        <td>{{ $item->categoria_nome ?: '-' }}</td>
                        <td>{{ $item->patrimonio_nome ?: '-' }}</td>
                        <td>{{ \App\Support\FarmFormat::statusLabel($item->patrimonio_uso) }}</td>
                        <td>{{ $item->unit }}</td>
                        <td>{{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                        <td>R$ {{ number_format($item->unit_value, 2, ',', '.') }}</td>
                        <td><strong>R$ {{ number_format($item->total_value, 2, ',', '.') }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

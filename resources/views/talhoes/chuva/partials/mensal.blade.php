<section class="panel">
    <div class="panel-head"><h2>Acumulado mensal</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Mês</th><th>Total</th></tr></thead>
            <tbody>
                @foreach ($mensal as $mes)
                    <tr>
                        <td>{{ $mes['mes'] }}</td>
                        <td><strong>{{ number_format($mes['total'], 1, ',', '.') }} mm</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

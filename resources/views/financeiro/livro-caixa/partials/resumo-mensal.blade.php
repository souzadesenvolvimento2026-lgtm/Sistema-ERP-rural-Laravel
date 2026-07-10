<section class="panel">
    <div class="panel-head"><h2>Resumo mensal</h2></div>
    @include('partials.data-table', [
        'columns' => ['mes' => 'Mes', 'entradas' => 'Entradas', 'saidas' => 'Saidas', 'saldo' => 'Saldo'],
        'rows' => $resumoMensal
    ])
</section>

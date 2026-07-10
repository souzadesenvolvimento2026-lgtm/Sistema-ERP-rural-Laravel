<section class="panel">
    <div class="panel-head"><h2>Registros</h2></div>
    @include('partials.data-table', ['columns' => $columns, 'rows' => $rows])
</section>

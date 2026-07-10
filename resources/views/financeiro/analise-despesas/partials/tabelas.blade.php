<section class="panel">
    <div class="panel-head"><h2>Subcategorias</h2></div>
    @include('partials.data-table', [
        'columns' => ['nome' => 'Subcategoria', 'tipo' => 'Categoria', 'realizado' => 'Realizado', 'atingido' => '% Atingido', 'orcado' => 'Projetado'],
        'rows' => $categoriasResumo
    ])
</section>

<section class="panel">
    <div class="panel-head"><h2>Analise mensal</h2></div>
    @include('partials.data-table', [
        'columns' => ['mes' => 'Mes', 'realizado' => 'Realizado', 'orcado' => 'Projetado', 'desvio' => 'Desvio'],
        'rows' => $mensal
    ])
</section>

<section class="panel">
    <div class="panel-head"><h2>Analise anual</h2></div>
    @include('partials.data-table', [
        'columns' => ['ano' => 'Ano', 'realizado' => 'Realizado', 'orcado' => 'Projetado', 'desvio' => 'Desvio'],
        'rows' => $anual
    ])
</section>

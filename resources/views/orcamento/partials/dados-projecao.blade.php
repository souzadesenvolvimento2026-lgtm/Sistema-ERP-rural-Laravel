@php($projecao = $projecao ?? null)

<section class="panel">
    <div class="panel-head"><h2>Dados da projeção</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            @include('orcamento.partials.dados-projecao-escopo')
            @include('orcamento.partials.dados-projecao-valores')
        </div>
    </div>
</section>

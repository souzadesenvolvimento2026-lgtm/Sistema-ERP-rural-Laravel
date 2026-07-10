<section class="panel">
    <div class="panel-head"><h2>Atendentes nos ultimos 30 dias</h2></div>
    @include('partials.data-table', [
        'columns' => ['nome' => 'Atendente', 'email' => 'E-mail', 'perfil' => 'Perfil', 'respostas' => 'Respostas', 'conversas' => 'Conversas', 'ultimo_atendimento' => 'Ultimo atendimento'],
        'rows' => $atendentes
    ])
</section>

<section class="panel">
    <div class="panel-head"><h2>Nova atividade</h2></div>
    <form method="POST" action="{{ route('talhoes.atividades.store') }}" class="form-grid">
        @csrf

        @include('talhoes.atividades.partials.form-identificacao')
        @include('talhoes.atividades.partials.form-periodo')
        @include('talhoes.atividades.partials.form-operacao')
        @include('talhoes.atividades.partials.form-observacoes')

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar atividade</button>
        </div>
    </form>
</section>

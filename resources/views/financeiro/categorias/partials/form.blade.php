<section class="panel">
    <div class="panel-head"><h2>Nova categoria</h2></div>
    <form method="POST" action="{{ route('financeiro.categorias.store') }}" class="form-grid">
        @csrf
        @include('financeiro.categorias.partials.form-fields', ['categoria' => null, 'parentOptions' => $principais])

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar categoria</button>
        </div>
    </form>
</section>

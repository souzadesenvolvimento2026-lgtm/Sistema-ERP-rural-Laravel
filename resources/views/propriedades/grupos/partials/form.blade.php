<section class="panel">
    <div class="panel-head"><h2>Novo grupo</h2></div>
    <form method="POST" action="{{ route('propriedades.grupos.store') }}" class="form-grid">
        @csrf
        @include('propriedades.grupos.partials.form-fields', ['grupo' => null])

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar grupo</button>
        </div>
    </form>
</section>

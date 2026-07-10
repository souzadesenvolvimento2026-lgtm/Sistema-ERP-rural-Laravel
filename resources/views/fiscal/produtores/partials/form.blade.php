<section class="panel">
    <div class="panel-head"><h2>Novo produtor</h2></div>
    <form method="POST" action="{{ route('fiscal.produtores.store') }}" class="form-grid">
        @csrf
        @include('fiscal.produtores.partials.form-fields', ['produtor' => null])

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar produtor</button>
        </div>
    </form>
</section>

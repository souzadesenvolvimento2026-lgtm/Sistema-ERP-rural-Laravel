<section class="stats">
    @foreach (['preparo_solo', 'plantio', 'manejo', 'colheita'] as $tipo)
        <div class="stat">
            <span>{{ $tipos[$tipo] }}</span>
            <strong>{{ $porTipo[$tipo] ?? 0 }}</strong>
        </div>
    @endforeach
</section>

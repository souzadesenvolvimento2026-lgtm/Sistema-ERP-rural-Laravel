@if ($safra)
    <section class="panel">
        <div class="panel-body">
            <strong>{{ $safra->descricao }}</strong>
            <p class="subtitle">
                Cultura: {{ $safra->cultura_nome ?: '-' }} |
                Area: {{ number_format((float)$safra->area_plantada, 2, ',', '.') }} ha |
                Producao estimada: {{ number_format((float)$safra->producao_estimada, 2, ',', '.') }} sc/ha
            </p>
        </div>
    </section>
@endif

<section class="panel">
    <div class="panel-head"><h2>Pontos pendentes</h2></div>
    <div class="quick-grid">
        @foreach ($alertas as $alerta)
            <div class="quick-card">
                <strong>{{ $alerta['value'] }}</strong>
                <span>{{ $alerta['label'] }}</span>
            </div>
        @endforeach
    </div>
</section>

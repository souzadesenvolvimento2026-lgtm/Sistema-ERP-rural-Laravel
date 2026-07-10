<section class="stats">
    <div class="stat"><span>A pagar</span><strong>R$ {{ number_format($totais['pagar'], 2, ',', '.') }}</strong></div>
    <div class="stat"><span>A receber</span><strong>R$ {{ number_format($totais['receber'], 2, ',', '.') }}</strong></div>
    <div class="stat"><span>Vencidos</span><strong>{{ $totais['vencidos'] }}</strong></div>
    <div class="stat"><span>Próximos 7 dias</span><strong>{{ $totais['semana'] }}</strong></div>
    <div class="stat"><span>Boletos vencendo</span><strong>{{ $totais['boletos'] }}</strong></div>
</section>

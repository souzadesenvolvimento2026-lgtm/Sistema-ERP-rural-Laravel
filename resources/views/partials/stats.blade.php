<section class="stats" aria-label="Resumo">
    @foreach ($cards as $card)
        <div class="stat">
            <span>{{ $card['label'] }}</span>
            <strong class="{{ $card['tone'] ?? '' }}">{{ $card['value'] }}</strong>
        </div>
    @endforeach
</section>

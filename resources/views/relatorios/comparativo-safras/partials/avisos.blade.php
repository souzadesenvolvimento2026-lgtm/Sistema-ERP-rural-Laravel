@if (($avisos['despesas'] + $avisos['receitas']) > 0)
    <section class="panel">
        <div class="panel-body">
            <strong>Lancamentos sem safra vinculada</strong>
            <p class="subtitle">
                Despesas: {{ $avisos['despesas'] }} |
                Receitas: {{ $avisos['receitas'] }}
            </p>
        </div>
    </section>
@endif

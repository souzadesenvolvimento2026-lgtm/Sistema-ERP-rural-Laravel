<section class="panel">
    <div class="panel-body">
        <div class="actions" style="justify-content:flex-start">
            @foreach ($contexto as $item)
                <span class="pill">{{ $item }}</span>
            @endforeach
        </div>
    </div>
</section>

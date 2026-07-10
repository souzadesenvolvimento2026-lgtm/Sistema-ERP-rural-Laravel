@extends('layouts.farmfort', ['title' => 'FarmFort - Painel Administrativo'])

@section('content')
    <section class="ff-admin-hero">
        <div>
            <span>Administração do sistema</span>
            <h1>Estrutura comercial do FarmFort</h1>
            <p>Crie propriedades, limite usuários por plano, vincule gestores e acompanhe a base sem abrir dados operacionais das fazendas.</p>
        </div>
        <div class="ff-admin-hero-actions">
            <a href="{{ route('propriedades.index') }}" class="btn light"><i class="bi bi-map"></i> Propriedades</a>
            <a href="{{ route('usuarios.index') }}" class="btn"><i class="bi bi-people"></i> Usuários</a>
            <a href="{{ route('suporte.admin.index') }}" class="btn"><i class="bi bi-chat-dots"></i> Atendimento</a>
        </div>
    </section>

    <section class="panel ff-admin-panel">
        <div class="panel-head">
            <div>
                <span>Visão geral</span>
                <h2>Base, acessos e operação do sistema</h2>
            </div>
        </div>
        <div class="ff-admin-stats">
            @foreach ($summaryCards as $card)
                <article class="stat">
                    <span>{{ $card['label'] }}</span>
                    <strong>{{ $card['value'] }}</strong>
                    <small>{{ $card['hint'] }}</small>
                </article>
            @endforeach
        </div>
    </section>

    <section class="panel ff-admin-panel">
        <div class="panel-head">
            <div>
                <span>Saúde do servidor</span>
                <h2>Ambiente, recursos e tráfego do FarmFort</h2>
            </div>
            <small>Atualizado em {{ $updatedAt }}</small>
        </div>
        <div class="ff-admin-health">
            @foreach ($healthCards as $card)
                <article>
                    <i class="bi {{ $card['icon'] }}"></i>
                    <span>{{ $card['label'] }}</span>
                    <strong>{{ $card['value'] }}</strong>
                    <small>{{ $card['hint'] }}</small>
                </article>
            @endforeach
        </div>
    </section>

    @foreach ($areas as $area)
        <section class="panel ff-admin-area">
            <div class="panel-head">
                <span>{{ $area['group'] }}</span>
                <h2>{{ $area['title'] }}</h2>
            </div>
            <div class="ff-admin-card-grid">
                @foreach ($area['cards'] as $card)
                    <a href="{{ $card['route'] }}" class="ff-admin-link-card">
                        <i class="bi {{ $card['icon'] }}"></i>
                        <strong>{{ $card['title'] }}</strong>
                        <small>{{ $card['text'] }}</small>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection

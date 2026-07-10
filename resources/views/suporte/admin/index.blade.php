@extends('layouts.farmfort', [
    'title' => 'FarmFort - '.$title,
    'topbarLabel' => 'Chat de Dúvidas',
])

@php
    $suporteEndpoint = url('/pages/ajax/suporte_chat.php');
    $profile = (string)session('perfil', '');
@endphp

@section('content')
    <section class="ff-command ff-support-hero">
        <div>
            <span class="ff-eyebrow">Atendimento</span>
            <h1>Chat de dúvidas dos clientes</h1>
            <p>Receba perguntas enviadas dentro das fazendas, responda pelo painel e acompanhe a fila de atendimento.</p>
        </div>
    </section>

    @include('partials.stats', ['cards' => $cards])

    <section class="ff-support-admin-board" data-support-admin data-support-page data-support-endpoint="{{ $suporteEndpoint }}" data-support-role="{{ $profile }}">
        <header>
            <div>
                <strong>Central de atendimento</strong>
                <small>Novas mensagens aparecem automaticamente no painel.</small>
            </div>
            <span class="badge topbar-badge"><i class="bi bi-chat-dots me-1"></i><span data-support-admin-badge>0</span> não lida(s)</span>
        </header>
        <div class="ff-support-admin-grid">
            <aside class="ff-support-threads" data-support-admin-threads>
                <div class="ff-support-empty">Carregando atendimentos...</div>
            </aside>
            <div class="ff-support-chatbox">
                <div class="ff-support-selected-property" data-support-admin-property hidden>
                    <span>Propriedade em atendimento</span>
                    <strong>Selecione uma conversa</strong>
                </div>
                <div class="ff-support-messages" data-support-admin-messages>
                    <div class="ff-support-empty">Selecione uma conversa para responder.</div>
                </div>
                <div class="ff-support-actions">
                    <button type="button" class="btn btn-sm btn-farmflow" data-support-admin-assume>
                        <i class="bi bi-person-check me-1"></i>Assumir atendimento
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-support-admin-request-close>
                        <i class="bi bi-check2-circle me-1"></i>Perguntar se pode finalizar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-support-admin-forward="gerencia">
                        <i class="bi bi-arrow-up-right-circle me-1"></i>Encaminhar gerência
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-support-admin-forward="admin">
                        <i class="bi bi-shield-check me-1"></i>Encaminhar admin
                    </button>
                    <div class="ff-support-routing" data-support-admin-routing hidden>
                        <select class="form-select form-select-sm" data-support-admin-assignee aria-label="Atendente online"></select>
                        <button type="button" class="btn btn-sm btn-outline-success" data-support-admin-assign>
                            <i class="bi bi-person-plus me-1"></i>Direcionar
                        </button>
                    </div>
                </div>
                <form class="ff-support-form" data-support-admin-form>
                    <label class="ff-support-attach" title="Anexar arquivo ou colar print com Ctrl+V">
                        <input type="file" name="anexos[]" multiple accept=".png,.jpg,.jpeg,.webp,.pdf,.xls,.xlsx,.csv,.xml">
                        <i class="bi bi-paperclip"></i>
                    </label>
                    <textarea name="mensagem" rows="3" placeholder="Responder dúvida do cliente..."></textarea>
                    <button type="submit" class="btn btn-farmflow"><i class="bi bi-send me-1"></i>Responder</button>
                </form>
            </div>
        </div>
    </section>

    @include('suporte.admin.partials.atendentes')
    @include('suporte.admin.partials.ultimas-respostas')
@endsection

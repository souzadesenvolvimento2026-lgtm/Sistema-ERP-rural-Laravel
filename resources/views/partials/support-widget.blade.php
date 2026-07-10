@php
    $loggedUserId = (int)session('usuario_id');
    $profile = (string)session('perfil', '');
    $canHandleSupport = in_array($profile, ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'], true);
    $canUseClientChat = $loggedUserId > 0 && !$canHandleSupport;
    $suporteEndpoint = url('/pages/ajax/suporte_chat.php');
    $chatInternoEndpoint = url('/pages/ajax/chat_interno.php');
@endphp

@if ($loggedUserId > 0 && $canHandleSupport)
<div class="ff-support-widget ff-support-admin-widget" data-support-admin data-support-floating data-support-endpoint="{{ $suporteEndpoint }}" data-support-role="{{ $profile }}">
    <button type="button" class="ff-support-launch" data-support-admin-toggle title="Chat/Suporte">
        <i class="bi bi-chat-dots"></i>
        <span>Chat/Suporte</span>
        <b data-support-admin-badge hidden>0</b>
    </button>
    <section class="ff-support-panel ff-support-admin-panel" data-support-admin-panel hidden>
        <header>
            <div>
                <strong>Chat de suporte</strong>
                <small>Mensagens dos clientes do FarmFort</small>
            </div>
            <div class="ff-support-head-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-support-sound aria-pressed="true"><i class="bi bi-volume-up-fill me-1"></i>Som ativado</button>
                <button type="button" class="ff-support-close" data-support-admin-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
            </div>
        </header>
        <div class="ff-support-admin-grid">
            <aside class="ff-support-threads" data-support-admin-threads></aside>
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
                    <textarea name="mensagem" rows="2" placeholder="Responder dúvida do cliente..."></textarea>
                    <button type="submit" class="btn btn-farmflow"><i class="bi bi-send"></i></button>
                </form>
            </div>
        </div>
    </section>
</div>
@elseif ($canUseClientChat)
<div class="ff-support-widget ff-support-client-widget" data-support-client data-support-endpoint="{{ $suporteEndpoint }}" data-chat-endpoint="{{ $chatInternoEndpoint }}">
    <button type="button" class="ff-support-launch" data-support-client-toggle title="Chat/Suporte">
        <i class="bi bi-question-circle"></i>
        <span>Chat/Suporte</span>
        <b data-internal-total-unread hidden>0</b>
    </button>
    <section class="ff-support-panel ff-support-client-panel" data-support-client-panel hidden>
        <header>
            <div>
                <strong>Chat/Suporte</strong>
                <small>Converse com a equipe da fazenda ou abra suporte</small>
            </div>
            <div class="ff-support-head-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-support-sound aria-pressed="true"><i class="bi bi-volume-up-fill me-1"></i>Som ativado</button>
                <button type="button" class="ff-support-close" data-support-client-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
            </div>
        </header>

        <div class="ff-support-tabs" role="tablist">
            <button type="button" class="active" data-support-client-tab="interno"><i class="bi bi-people me-1"></i>Chat</button>
            <button type="button" data-support-client-tab="suporte"><i class="bi bi-headset me-1"></i>Suporte</button>
        </div>

        <div class="ff-support-tab-view" data-support-client-view="interno">
            <div class="ff-internal-chat" data-internal-chat>
                <aside class="ff-internal-users" data-internal-users>
                    <div class="ff-support-empty">Carregando usuários...</div>
                </aside>
                <section class="ff-internal-conversation">
                    <div class="ff-support-selected-property ff-internal-selected" data-internal-selected hidden>
                        <span>Conversa interna</span>
                        <strong>Selecione um usuário</strong>
                    </div>
                    <div class="ff-support-messages ff-internal-messages" data-internal-messages>
                        <div class="ff-support-empty">Selecione um usuário online ou offline da fazenda para conversar.</div>
                    </div>
                    <form class="ff-support-form" data-internal-form>
                        <label class="ff-support-attach" title="Anexar arquivo ou colar print com Ctrl+V">
                            <input type="file" name="anexos[]" multiple accept=".png,.jpg,.jpeg,.webp,.pdf,.xls,.xlsx,.csv,.xml">
                            <i class="bi bi-paperclip"></i>
                        </label>
                        <textarea name="mensagem" rows="2" placeholder="Mensagem para usuário da fazenda..."></textarea>
                        <button type="submit" class="btn btn-farmflow"><i class="bi bi-send"></i></button>
                    </form>
                </section>
            </div>
        </div>

        <div class="ff-support-tab-view" data-support-client-view="suporte" hidden>
            <div class="ff-support-messages" data-support-client-messages>
                <div class="ff-support-empty">Digite sua dúvida para iniciar o atendimento.</div>
            </div>
            <div class="ff-support-status" data-support-client-status hidden></div>
            <div class="ff-support-actions">
                <button type="button" class="btn btn-sm btn-outline-danger" data-support-client-finish>
                    <i class="bi bi-box-arrow-right me-1"></i>Finalizar chat
                </button>
                <button type="button" class="btn btn-sm btn-farmflow" data-support-client-keep hidden>
                    <i class="bi bi-chat-left-text me-1"></i>Continuar atendimento
                </button>
            </div>
            <form class="ff-support-form" data-support-client-form>
                <label class="ff-support-attach" title="Anexar arquivo ou colar print com Ctrl+V">
                    <input type="file" name="anexos[]" multiple accept=".png,.jpg,.jpeg,.webp,.pdf,.xls,.xlsx,.csv,.xml">
                    <i class="bi bi-paperclip"></i>
                </label>
                <textarea name="mensagem" rows="2" placeholder="Escreva sua dúvida..."></textarea>
                <button type="submit" class="btn btn-farmflow"><i class="bi bi-send"></i></button>
            </form>
        </div>
    </section>
</div>
@endif

<?php
/**
 * Template for the Inbox SaaS Experience
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="was-app-container was-inbox-container">
    <!-- Sidebar: Conversas -->
    <aside class="was-inbox-sidebar">
        <div class="was-inbox-header">
            <h2>Conversas</h2>
            <div class="was-inbox-actions">
                <button id="was-refresh-conversations" class="was-btn-icon" title="Atualizar">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
        </div>
        
        <div class="was-inbox-search">
            <input type="text" id="was-conversation-search" placeholder="Buscar contato...">
        </div>

        <div id="was-conversations-list" class="was-conversations-list">
            <!-- Renderizado via JS -->
            <div class="was-loading-state">Carregando conversas...</div>
        </div>
    </aside>

    <!-- Main: Chat -->
    <main class="was-inbox-main" id="was-inbox-main">
        <div id="was-no-conversation-selected" class="was-empty-state">
            <div class="was-empty-icon">💬</div>
            <h3>Selecione uma conversa</h3>
            <p>Escolha um contato ao lado para visualizar o histórico de mensagens.</p>
        </div>

        <div id="was-active-chat" class="was-active-chat" style="display: none;">
            <header class="was-chat-header">
                <div class="was-contact-info">
                    <div class="was-contact-avatar" id="was-chat-avatar"></div>
                    <div>
                        <h3 id="was-chat-contact-name">Nome do Contato</h3>
                        <span id="was-chat-contact-phone"></span>
                    </div>
                </div>
                <div class="was-chat-actions">
                    <!-- Ações adicionais futuramente -->
                </div>
            </header>

            <div id="was-chat-window-status" class="was-window-status-banner" style="display: none;">
                <span class="was-window-icon"></span>
                <div class="was-window-text">
                    <strong class="was-window-title">Janela de Atendimento</strong>
                    <p class="was-window-desc"></p>
                </div>
                <div class="was-window-timer"></div>
            </div>

            <div id="was-messages-history" class="was-messages-history">
                <!-- Mensagens renderizadas aqui -->
            </div>

            <footer class="was-chat-footer">
                <div id="was-composer-reply" class="was-composer-reply">
                    <div class="was-composer-reply-content">
                        <div id="was-reply-preview-user" class="was-reply-user">Respondendo a...</div>
                        <div id="was-reply-preview-text" class="was-reply-text">...</div>
                    </div>
                    <span id="was-clear-reply" class="was-composer-reply-close dashicons dashicons-no-alt"></span>
                </div>
                <div id="was-chat-input-container">
                    <div class="was-chat-input-actions">
                        <button id="was-open-templates-inbox" class="was-btn-secondary" title="Enviar Modelo">
                            <span class="dashicons dashicons-layout"></span>
                        </button>
                        <button id="was-attach-media" class="was-btn-secondary" title="Anexar">
                            <span class="dashicons dashicons-paperclip"></span>
                        </button>
                        <input type="file" id="was-media-input" style="display:none;" accept="image/*,audio/*,video/*,application/pdf">
                        <div id="was-attachment-menu" class="was-attachment-menu" style="display:none;">
                            <div class="was-attach-item" data-type="image"><span class="dashicons dashicons-format-image"></span> Imagem</div>
                            <div class="was-attach-item" data-type="audio"><span class="dashicons dashicons-controls-volumeon"></span> Áudio</div>
                            <div class="was-attach-item" data-type="video"><span class="dashicons dashicons-format-video"></span> Vídeo</div>
                            <div class="was-attach-item" data-type="document"><span class="dashicons dashicons-media-document"></span> Documento</div>
                        </div>
                    </div>
                    <textarea id="was-message-input" placeholder="Digite uma mensagem..."></textarea>
                    <button id="was-send-message" class="was-btn-primary" disabled>
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path d="M1.101 21.757L23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"></path>
                        </svg>
                    </button>
                </div>
            </footer>
        </div>
    </main>
</div>

<!-- Modal de Seleção de Template na Inbox -->
<div id="was-inbox-tpl-modal" class="was-modal" style="display:none; position:fixed; z-index:12000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div class="was-modal-content" style="background:white; margin:10% auto; padding:20px; width:450px; border-radius:8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        <h3>Escolha um Modelo</h3>
        <div id="was-inbox-tpl-list" style="max-height:300px; overflow-y:auto; margin-bottom:20px; border: 1px solid #eee; border-radius: 4px;">
            <!-- Carregado via JS -->
        </div>
        <div style="text-align:right;">
            <button type="button" id="was-close-inbox-tpl-modal" class="button">Cancelar</button>
        </div>
    </div>
</div>

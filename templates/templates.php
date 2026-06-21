<?php
/**
 * Template para Modelos de Mensagem (Templates)
 * Com Construtor Visual e Preview
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Modelos de Mensagem</h1>
    
    <div id="was-templates-app">
        <div class="was-actions-bar" style="margin: 20px 0; display: flex; gap: 10px;">
            <button id="sync-templates" class="button">Sincronizar com Meta</button>
            <button id="was-open-create-wizard" class="button button-primary">+ Criar modelo</button>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Idioma</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="template-list-body">
                <tr><td colspan="5">Carregando modelos...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Wizard de Criação (Full Screenish Overlay) -->
    <div id="was-template-wizard" class="was-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:#f0f2f5; overflow-y:auto;">
        <div class="was-wizard-container" style="display:flex; height:100vh;">
            
            <!-- Sidebar de Etapas -->
            <div class="was-wizard-sidebar" style="width:300px; background:white; border-right:1px solid #ddd; padding:40px 20px;">
                <h2>Criar modelo</h2>
                <ul class="was-wizard-steps" style="list-style:none; padding:0; margin-top:30px;">
                    <li class="step-item active" data-step="1">1. Objetivo</li>
                    <li class="step-item" data-step="2">2. Identificação</li>
                    <li class="step-item" data-step="3">3. Conteúdo</li>
                    <li class="step-item" data-step="4">4. Botões</li>
                    <li class="step-item" data-step="5">5. Revisão</li>
                </ul>
            </div>

            <!-- Área de Edição -->
            <div class="was-wizard-main" style="flex:1; padding:40px; overflow-y:auto;">
                <form id="was-complex-template-form">
                    
                    <!-- Passo 1: Objetivo -->
                    <div class="was-wizard-step-content" id="step-1">
                        <h3>Qual é o objetivo desta mensagem?</h3>
                        <p class="description">A categoria ajuda o WhatsApp a entender o propósito do seu modelo.</p>
                        <div class="was-category-cards" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
                            <label class="was-cat-card">
                                <input type="radio" name="category" value="UTILITY" checked>
                                <div class="was-cat-card-inner">
                                    <span class="cat-icon">🛠️</span>
                                    <strong>Utilitário</strong>
                                    <p>Atualizar cliente sobre pedido, pagamento, entrega ou agendamento.</p>
                                </div>
                            </label>
                            <label class="was-cat-card">
                                <input type="radio" name="category" value="MARKETING">
                                <div class="was-cat-card-inner">
                                    <span class="cat-icon">🚀</span>
                                    <strong>Marketing</strong>
                                    <p>Enviar oferta, campanha, cupom, lançamento ou promoção.</p>
                                </div>
                            </label>
                            <label class="was-cat-card">
                                <input type="radio" name="category" value="AUTHENTICATION">
                                <div class="was-cat-card-inner">
                                    <span class="cat-icon">🔐</span>
                                    <strong>Autenticação</strong>
                                    <p>Enviar códigos de verificação (OTP), login ou redefinição de senha.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Passo 2: Nome e Idioma -->
                    <div class="was-wizard-step-content" id="step-2" style="display:none;">
                        <h3>Nome e Idioma</h3>
                        <p>
                            <label>Nome interno do modelo</label><br>
                            <input type="text" id="wiz-tpl-name" class="regular-text" required placeholder="ex: confirmacao_pedido">
                            <span class="description">Apenas letras minúsculas, números e underline.</span>
                        </p>
                        <p>
                            <label>Idioma da mensagem</label><br>
                            <select id="wiz-tpl-lang" class="regular-text">
                                <option value="pt_BR">Português (Brasil)</option>
                                <option value="en_US">Inglês (US)</option>
                                <option value="es_ES">Espanhol</option>
                            </select>
                        </p>
                    </div>

                    <!-- Passo 3: Conteúdo -->
                    <div class="was-wizard-step-content" id="step-3" style="display:none;">
                        <h3>Estrutura da Mensagem</h3>
                        
                        <!-- Campos para Marketing/Utilitário -->
                        <div id="wiz-standard-fields">
                            <div class="was-form-group">
                                <label>Cabeçalho (Opcional)</label>
                                <select id="wiz-header-type" class="regular-text">
                                    <option value="NONE">Nenhum</option>
                                    <option value="TEXT">Texto</option>
                                </select>
                                <div id="wiz-header-text-container" style="display:none; margin-top:10px;">
                                    <input type="text" id="wiz-header-text" class="regular-text" placeholder="Título da mensagem" maxlength="60">
                                </div>
                            </div>
                            <div class="was-form-group" style="margin-top:20px;">
                                <label>Corpo da mensagem (Obrigatório)</label>
                                <textarea id="wiz-body-text" style="width:100%;" rows="6" placeholder="Digite sua mensagem aqui... Use {{nome}} para variáveis."></textarea>
                                <button type="button" id="wiz-add-var" class="button" style="margin-top:5px;">+ Inserir variável</button>
                            </div>
                            <div class="was-form-group" style="margin-top:20px;">
                                <label>Rodapé (Opcional)</label>
                                <input type="text" id="wiz-footer-text" class="regular-text" placeholder="Texto curto no rodapé" maxlength="60">
                            </div>
                        </div>

                        <!-- Campos para Autenticação -->
                        <div id="wiz-auth-fields" style="display:none;">
                            <div class="was-form-group">
                                <label>Tipo de Autenticação</label><br>
                                <select id="wiz-auth-type" class="regular-text" style="width:100%;">
                                    <option value="COPY_CODE">Copiar código (Copy Code)</option>
                                    <option value="ONE_TAP">Um toque (One-tap autofill - Android)</option>
                                    <option value="ZERO_TAP">Zero toque (Zero-tap - Avançado)</option>
                                </select>
                            </div>
                            <div class="was-form-group" style="margin-top:15px;">
                                <label>Texto do Botão</label><br>
                                <input type="text" id="wiz-auth-button-text" class="regular-text" value="Copiar código" style="width:100%;">
                            </div>
                            <div class="was-form-group" style="margin-top:15px;">
                                <label>Expiração do código (minutos)</label><br>
                                <input type="number" id="wiz-auth-expiration" class="regular-text" value="10" min="1" max="90" style="width:100%;">
                            </div>
                            <div class="was-form-group" style="margin-top:15px;">
                                <label>
                                    <input type="checkbox" id="wiz-auth-security" checked> 
                                    Adicionar recomendação de segurança
                                </label>
                                <p class="description">Ex: "Por segurança, não compartilhe este código."</p>
                            </div>

                            <!-- Campos Extras para One-tap / Zero-tap -->
                            <div id="wiz-auth-mobile-fields" style="display:none; margin-top:20px; padding:15px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                                <p><strong>Configurações do Aplicativo</strong></p>
                                <p>
                                    <label>Nome do pacote (Package Name)</label><br>
                                    <input type="text" id="wiz-auth-package" class="regular-text" style="width:100%;" placeholder="com.exemplo.app">
                                </p>
                                <p>
                                    <label>Assinatura do App (Signature Hash)</label><br>
                                    <input type="text" id="wiz-auth-hash" class="regular-text" style="width:100%;" placeholder="HASH_DO_APP">
                                </p>
                                <p id="wiz-auth-zerotap-wrap" style="display:none;">
                                    <label>
                                        <input type="checkbox" id="wiz-auth-zerotap-terms"> 
                                        Aceito os termos do Zero-tap
                                    </label>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Passo 4: Botões -->
                    <div class="was-wizard-step-content" id="step-4" style="display:none;">
                        <h3>Botões de Interação</h3>
                        <p class="description">Adicione botões para facilitar a resposta do cliente ou direcioná-lo para um link.</p>
                        
                        <div id="wiz-buttons-list" style="margin-top:15px;">
                            <!-- Injetado via JS -->
                        </div>

                        <div class="was-button-actions" style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                            <button type="button" class="button wiz-add-btn-type" data-type="QUICK_REPLY">+ Resposta Rápida</button>
                            <button type="button" class="button wiz-add-btn-type" data-type="URL">+ Abrir Site</button>
                            <button type="button" class="button wiz-add-btn-type" data-type="PHONE_NUMBER">+ Ligar</button>
                            <button type="button" class="button wiz-add-btn-type" data-type="COPY_CODE">+ Copiar Código</button>
                        </div>
                    </div>

                    <!-- Passo 5: Revisão -->
                    <div class="was-wizard-step-content" id="step-5" style="display:none;">
                        <h3>Revisão de Conformidade</h3>
                        <p class="description">Verifique se tudo está correto antes de enviar para a Meta.</p>
                        
                        <div id="wiz-variables-examples" style="margin-top:20px; background:white; padding:20px; border-radius:8px; border:1px solid #ddd;">
                            <!-- Exemplos de variáveis injetados via JS -->
                        </div>

                        <div id="wiz-checklist" style="margin-top:20px; background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd;">
                            <h4 style="margin-top:0;">Checklist</h4>
                            <ul id="was-checklist-items" style="list-style:none; padding:0;">
                                <!-- Dinâmico -->
                            </ul>
                        </div>
                    </div>

                    <!-- Navegação do Wizard -->
                    <div class="was-wizard-footer" style="margin-top:40px; display:flex; gap:10px;">
                        <button type="button" id="wiz-prev" class="button" style="display:none;">Anterior</button>
                        <button type="button" id="wiz-next" class="button button-primary">Próximo</button>
                        <button type="submit" id="wiz-submit" class="button button-primary" style="display:none; background:#25d366; border-color:#25d366;">Enviar para Aprovação</button>
                        <button type="button" id="wiz-cancel" class="button button-link-delete" style="margin-left:auto;">Sair sem salvar</button>
                    </div>
                </form>
            </div>

            <!-- Preview Lateral (Fixo) -->
            <div class="was-wizard-preview" style="width:400px; background:#e5ddd5; display:flex; align-items:center; justify-content:center; padding:20px;">
                <div class="was-wa-preview-card">
                    <div class="was-wa-header" id="pre-header" style="display:none;"></div>
                    <div class="was-wa-body" id="pre-body">Sua mensagem aparecerá aqui...</div>
                    <div class="was-wa-footer" id="pre-footer" style="display:none;"></div>
                    <div class="was-wa-buttons" id="pre-buttons" style="display:none;"></div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal de Envio (Aprimorado) -->
    <div id="was-send-template-modal" class="was-modal" style="display:none; position:fixed; z-index:11000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); overflow-y:auto;">
        <div class="was-modal-content" style="background:#f0f2f5; margin:5% auto; padding:0; width:800px; border-radius:12px; overflow:hidden; display:flex; flex-direction:row; box-shadow:0 20px 50px rgba(0,0,0,0.3);">
            
            <!-- Coluna de Configuração -->
            <div style="flex:1; background:white; padding:30px; max-height:80vh; overflow-y:auto;">
                <h2 style="margin-top:0;">Enviar Modelo</h2>
                <form id="was-send-template-form">
                    <input type="hidden" id="send-tpl-id">
                    
                    <p><strong>Modelo selecionado:</strong> <span id="send-tpl-name-display" style="color:#008069;"></span></p>
                    
                    <div style="margin-top:20px;">
                        <label><strong>1. Destinatário (WhatsApp ID)</strong></label><br>
                        <input type="text" id="send-tpl-to" style="width:100%; margin-top:5px;" required placeholder="5511999999999">
                    </div>

                    <div id="was-tpl-variables-container" style="margin-top:25px; border-top:1px solid #eee; padding-top:20px;">
                        <label><strong>2. Preencher Variáveis</strong></label>
                        <div id="was-tpl-variables-inputs" style="margin-top:10px;">
                            <!-- Injetado via JS -->
                        </div>
                    </div>

                    <div style="margin-top:30px; display:flex; gap:10px;">
                        <button type="submit" class="button button-primary" style="background:#25d366; border-color:#25d366; flex:1; height:40px; font-weight:bold;">Enviar Agora</button>
                        <button type="button" id="was-close-send-modal" class="button" style="flex:1; height:40px;">Cancelar</button>
                    </div>
                </form>
            </div>

            <!-- Coluna de Preview Realista -->
            <div style="width:350px; background:#e5ddd5; padding:40px 20px; display:flex; align-items:center; justify-content:center;">
                <div style="width:100%;">
                    <p style="text-align:center; color:#667781; font-size:0.8rem; margin-bottom:15px; text-transform:uppercase; letter-spacing:1px;">Preview do Envio</p>
                    <div class="was-wa-preview-card" style="width:100%; margin:0 auto;">
                        <div class="was-wa-header" id="send-pre-header" style="display:none;"></div>
                        <div class="was-wa-body" id="send-pre-body">...</div>
                        <div class="was-wa-footer" id="send-pre-footer" style="display:none;"></div>
                        <div class="was-wa-buttons" id="send-pre-buttons" style="display:none;"></div>
                    </div>
                    <p style="margin-top:20px; font-size:0.75rem; color:#667781; text-align:center;">As variáveis aparecerão aqui conforme você as preencher.</p>
                </div>
            </div>

        </div>
    </div>

</div>

<style>
/* Estilos rápidos para o Wizard */
.was-cat-card input { display:none; }
.was-cat-card-inner { border:2px solid #ddd; padding:20px; border-radius:8px; cursor:pointer; background:white; transition:all 0.2s; text-align:center; }
.was-cat-card-inner .cat-icon { font-size:24px; display:block; margin-bottom:10px; }
.was-cat-card input:checked + .was-cat-card-inner { border-color:#2563eb; background:#eff6ff; box-shadow:0 0 0 1px #2563eb; }
.step-item { padding:12px; border-radius:6px; margin-bottom:5px; color:#64748b; font-weight:500; }
.step-item.active { background:#eff6ff; color:#2563eb; }

/* Botões no editor */
.wiz-btn-edit-item { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:15px; margin-bottom:10px; position:relative; }
.wiz-btn-edit-item .remove-btn { position:absolute; right:10px; top:10px; cursor:pointer; color:#ef4444; font-weight:bold; }

/* Preview WhatsApp */
.was-wa-preview-card { background:white; width:280px; border-radius:8px; box-shadow:0 1px 2px rgba(0,0,0,0.1); padding:10px; position:relative; }
.was-wa-header { font-weight:bold; margin-bottom:5px; font-size:0.9rem; color:#111; }
.was-wa-body { white-space:pre-wrap; font-size:0.9rem; color:#111; line-height:1.4; }
.was-wa-footer { color:#667781; font-size:0.75rem; margin-top:5px; }
.was-wa-buttons { margin-top:10px; border-top:1px solid #f0f2f5; margin-left:-10px; margin-right:-10px; margin-bottom:-10px; }
.was-wa-btn-item { padding:10px; text-align:center; color:#00a884; font-weight:500; font-size:0.85rem; border-top:1px solid #f0f2f5; }
</style>

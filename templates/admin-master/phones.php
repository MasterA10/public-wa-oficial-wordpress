<?php
/**
 * Master Admin Phones Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Números WhatsApp</h1>
    <p class="description">Cada número já aparece junto da WABA. Clique em um número para consultar templates e configurar o encaminhamento dos webhooks para uma URL externa.</p>

    <div class="card" style="margin-top:20px; padding:18px; max-width:1100px;">
        <h2 style="margin-top:0;">Cadastrar novo número</h2>
        <p class="description">Use o Cadastro Incorporado oficial da Meta para adicionar um número a um tenant.</p>
        <div style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
            <label>Tenant<br><select id="master-onboarding-tenant" required><option value="">Carregando tenants...</option></select></label>
            <label>Número WhatsApp<br><input id="master-onboarding-phone" type="tel" inputmode="tel" placeholder="+55 (11) 99999-9999" required></label>
            <button type="button" id="master-start-onboarding" class="button button-primary">Cadastro incorporado</button>
        </div>
        <div id="master-onboarding-status" role="status" aria-live="polite" style="display:none; margin-top:14px;"></div>
    </div>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>WABA</th>
                <th>Phone ID</th>
                <th>Número</th>
                <th>Nome Verificado</th>
                <th>Status</th>
                <th>Qualidade</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-phones-list">
            <tr><td colspan="8">Carregando números...</td></tr>
        </tbody>
    </table>

    <section id="was-master-phone-details" style="display:none; margin-top:28px; padding:24px; background:#fff; border:1px solid #dcdcde; border-radius:8px;">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start;">
            <div>
                <h2 id="was-master-phone-title" style="margin-top:0;">Número</h2>
                <p id="was-master-phone-meta" class="description"></p>
            </div>
            <button type="button" id="was-master-phone-close" class="button">Fechar</button>
        </div>

        <div style="display:flex; gap:8px; border-bottom:1px solid #dcdcde; margin:20px 0;">
            <button type="button" class="button button-primary was-phone-detail-tab" data-tab="routes">Rotas / Webhooks</button>
            <button type="button" class="button was-phone-detail-tab" data-tab="templates">Templates</button>
        </div>

        <div id="was-phone-detail-routes">
            <h3>Rotas de encaminhamento</h3>
            <p class="description">Uma rota pega o webhook recebido da Meta para este número e envia o payload para uma URL externa.</p>
            <form id="was-master-route-form" style="display:grid; grid-template-columns:1fr 2fr 1fr auto; gap:10px; align-items:end; margin:18px 0;">
                <label>Nome<br><input id="master-route-name" type="text" placeholder="Meu backend"></label>
                <label>URL externa de destino<br><input id="master-route-url" type="url" required placeholder="https://app.exemplo.com/webhooks/whatsapp"></label>
                <label>Segredo (opcional)<br><input id="master-route-secret" type="password" placeholder="Assinatura"></label>
                <button type="submit" class="button button-primary">Criar rota</button>
            </form>
            <div id="was-master-routes-list"></div>
        </div>

        <div id="was-phone-detail-templates" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <div>
                    <h3>Templates deste número</h3>
                    <p class="description">Templates pertencentes à WABA vinculada a este número.</p>
                </div>
                <button type="button" id="was-master-sync-phone-templates" class="button">Sincronizar com Meta</button>
            </div>
            <div id="was-master-phone-templates-list"></div>
        </div>
    </section>

    <!-- Modal Test Message -->
    <div id="was-master-test-msg-modal" class="was-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="was-modal-content" style="background:white; margin:10% auto; padding:20px; width:400px; border-radius:8px;">
            <h2>Testar Envio de Mensagem</h2>
            <p class="description">Envie uma mensagem de texto simples para validar a conexão.</p>
            <form id="was-master-test-msg-form">
                <input type="hidden" id="master-test-phone-id">
                <p>
                    <label>Número de Destino (com DDI)</label><br>
                    <input type="text" id="master-test-to" class="regular-text" placeholder="Número do destinatário com DDI" required>
                </p>
                <p class="submit" style="text-align:right;">
                    <button type="button" id="was-master-test-msg-cancel" class="button">Cancelar</button>
                    <button type="submit" class="button button-primary">Enviar Teste</button>
                </p>
            </form>
        </div>
    </div>
</div>

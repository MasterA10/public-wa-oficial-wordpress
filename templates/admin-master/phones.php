<?php
/**
 * Master Admin Phones Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de Números WhatsApp</h1>
    <p class="description">Visualize todos os números de telefone registrados na plataforma.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>Phone ID</th>
                <th>Número</th>
                <th>Nome Verificado</th>
                <th>Status</th>
                <th>Qualidade</th>
                <th>Padrão</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-phones-list">
            <tr><td colspan="8">Carregando números...</td></tr>
        </tbody>
    </table>

    <!-- Modal Test Message -->
    <div id="was-master-test-msg-modal" class="was-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="was-modal-content" style="background:white; margin:10% auto; padding:20px; width:400px; border-radius:8px;">
            <h2>Testar Envio de Mensagem</h2>
            <p class="description">Envie uma mensagem de texto simples para validar a conexão.</p>
            <form id="was-master-test-msg-form">
                <input type="hidden" id="master-test-phone-id">
                <p>
                    <label>Número de Destino (com DDI)</label><br>
                    <input type="text" id="master-test-to" class="regular-text" placeholder="5511999999999" required>
                </p>
                <p class="submit" style="text-align:right;">
                    <button type="button" id="was-master-test-msg-cancel" class="button">Cancelar</button>
                    <button type="submit" class="button button-primary">Enviar Teste</button>
                </p>
            </form>
        </div>
    </div>
</div>

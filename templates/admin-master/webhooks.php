<?php
/**
 * Master Admin Webhooks Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Saúde dos Webhooks</h1>
    <p class="description">Monitore os eventos recebidos da Meta API em tempo real.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Data Recebimento</th>
                <th>Tenant</th>
                <th>Tipo de Evento</th>
                <th>Assinatura</th>
                <th>Status Processamento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-webhooks-list">
            <tr><td colspan="6">Carregando eventos...</td></tr>
        </tbody>
    </table>

    <div id="was-master-webhook-payload-modal" class="was-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="was-modal-content" style="background:white; margin:5% auto; padding:20px; width:min(900px, 90%); max-height:80vh; overflow-y:auto; border-radius:8px;">
            <h2>Payload JSON do webhook</h2>
            <pre id="was-master-webhook-payload-json" style="background:#111827; color:#e5e7eb; padding:16px; border-radius:6px; max-height:60vh; overflow:auto; white-space:pre-wrap;"></pre>
            <p class="submit" style="text-align:right;">
                <button type="button" id="was-master-webhook-payload-close" class="button">Fechar</button>
            </p>
        </div>
    </div>
</div>

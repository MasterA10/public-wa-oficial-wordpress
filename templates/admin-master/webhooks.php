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
</div>

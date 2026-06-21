<?php
/**
 * Master Admin WABAs Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de WABAs Conectadas</h1>
    <p class="description">Visualize todas as contas do WhatsApp Business integradas à plataforma.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>WABA ID</th>
                <th>Nome</th>
                <th>Status</th>
                <th>Webhook</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-wabas-list">
            <tr><td colspan="7">Carregando WABAs...</td></tr>
        </tbody>
    </table>
</div>

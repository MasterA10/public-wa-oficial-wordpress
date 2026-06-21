<?php
/**
 * Master Admin Audit Logs Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Logs de Auditoria Master</h1>
    <p class="description">Histórico completo de todas as ações administrativas realizadas na plataforma.</p>

    <div id="was-master-audit-app" style="margin-top: 20px;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Usuário</th>
                    <th>Tenant</th>
                    <th>Ação</th>
                    <th>Entidade</th>
                    <th>IP / User Agent</th>
                    <th>Metadata</th>
                </tr>
            </thead>
            <tbody id="master-audit-list">
                <tr><td colspan="7">Carregando logs...</td></tr>
            </tbody>
        </table>
    </div>
</div>

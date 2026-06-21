<?php
/**
 * Master Admin Dashboard Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Painel Master Administrativo</h1>
    <p class="description">Gerenciamento centralizado de todos os tenants, apps Meta e saúde da operação.</p>

    <div class="was-master-dashboard" style="margin-top: 20px;">
        <div class="was-stats-grid">
            <div class="was-stat-card">
                <h3>Clientes Ativos</h3>
                <div class="was-stat-value" id="master-stat-tenants">0</div>
            </div>
            <div class="was-stat-card">
                <h3>WABAs Conectadas</h3>
                <div class="was-stat-value" id="master-stat-wabas">0</div>
            </div>
            <div class="was-stat-card">
                <h3>Números Ativos</h3>
                <div class="was-stat-value" id="master-stat-phones">0</div>
            </div>
            <div class="was-stat-card">
                <h3>Templates Aprovados</h3>
                <div class="was-stat-value" id="master-stat-templates">0</div>
            </div>
            <div class="was-stat-card">
                <h3>Webhooks (Hoje)</h3>
                <div class="was-stat-value" id="master-stat-webhooks">0</div>
            </div>
            <div class="was-stat-card">
                <h3>Falhas de Onboarding</h3>
                <div class="was-stat-value" id="master-stat-onboarding-failures" style="color: var(--danger);">0</div>
            </div>
        </div>

        <div style="margin-top: 30px; display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            <div class="card">
                <h2>Alertas Operacionais</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Gravidade</th>
                            <th>Entidade</th>
                            <th>Problema</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody id="master-alerts-list">
                        <tr><td colspan="4">Carregando alertas...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2>Apps Meta Ativos</h2>
                <div id="master-active-apps-list">
                    <p>Carregando apps...</p>
                </div>
            </div>
        </div>
    </div>
</div>

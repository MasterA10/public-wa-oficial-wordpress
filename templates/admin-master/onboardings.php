<?php
/**
 * Master Admin Onboardings Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Log de Onboardings (Cadastro Incorporado)</h1>
    <p class="description">Monitore todas as tentativas de conexão de novos clientes.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Tenant / Usuário</th>
                <th>Status</th>
                <th>WABA / Phone ID</th>
                <th>Erro</th>
                <th>Data Início</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-onboardings-list">
            <tr><td colspan="6">Carregando onboardings...</td></tr>
        </tbody>
    </table>
</div>

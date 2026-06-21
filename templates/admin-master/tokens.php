<?php
/**
 * Master Admin Tokens Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Tokens e Permissões</h1>
    <p class="description">Monitore a validade e a saúde dos Access Tokens de todos os clientes sem expor segredos.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Prefixo</th>
                <th>Tamanho</th>
                <th>Status</th>
                <th>Expira em</th>
                <th>Último Erro</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-tokens-list">
            <tr><td colspan="7">Carregando tokens...</td></tr>
        </tbody>
    </table>
</div>

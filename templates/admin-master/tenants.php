<?php
/**
 * Master Admin Tenants Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de Clientes / Tenants</h1>
    <p class="description">Visualize e gerencie todos os clientes da plataforma.</p>

    <div class="was-actions-bar" style="margin: 20px 0;">
        <button id="master-btn-add-tenant" class="button button-primary">+ Novo Cliente</button>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Slug</th>
                <th>Plano</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-tenants-list">
            <tr><td colspan="6">Carregando clientes...</td></tr>
        </tbody>
    </table>

    <!-- Modal Tenant -->
    <div id="was-master-tenant-modal" class="was-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="was-modal-content" style="background:white; margin:5% auto; padding:20px; width:500px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
            <h2 id="master-tenant-modal-title">Novo Cliente</h2>
            <form id="was-master-tenant-form">
                <input type="hidden" id="master-tenant-id">
                <table class="form-table">
                    <tr>
                        <td><label>Nome da Empresa</label><br><input type="text" id="master-tenant-name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <td><label>Slug (Identificador Único)</label><br><input type="text" id="master-tenant-slug" class="regular-text" required placeholder="ex: minha-empresa"></td>
                    </tr>
                    <tr>
                        <td>
                            <label>Plano</label><br>
                            <select id="master-tenant-plan" class="regular-text">
                                <option value="free">Gratuito</option>
                                <option value="pro">Profissional</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit" style="text-align:right;">
                    <button type="button" id="was-master-tenant-cancel" class="button">Cancelar</button>
                    <button type="submit" class="button button-primary">Salvar Cliente</button>
                </p>
            </form>
        </div>
    </div>
</div>

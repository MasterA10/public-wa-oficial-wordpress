<?php
/**
 * Master Admin Meta Apps Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de Apps Meta</h1>
    <p class="description">Gerencie as aplicações Meta (Facebook) que sua plataforma utiliza.</p>

    <div class="was-actions-bar" style="margin: 20px 0;">
        <button id="master-btn-add-app" class="button button-primary">+ Novo App Meta</button>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>App</th>
                <th>App ID</th>
                <th>Graph Versão</th>
                <th>Config ID</th>
                <th>Ambiente</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-apps-list">
            <tr><td colspan="7">Carregando apps...</td></tr>
        </tbody>
    </table>

    <!-- Modal App Meta -->
    <div id="was-master-app-modal" class="was-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="was-modal-content" style="background:white; margin:5% auto; padding:20px; width:500px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
            <h2 id="master-app-modal-title">Novo App Meta</h2>
            <form id="was-master-app-form">
                <input type="hidden" id="master-app-id">
                <table class="form-table">
                    <tr>
                        <td><label>Nome Interno</label><br><input type="text" id="master-app-name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <td><label>App ID</label><br><input type="text" id="master-app-appid" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <td><label>App Secret</label><br><input type="password" id="master-app-secret" class="regular-text" placeholder="Manter vazio se não quiser alterar"></td>
                    </tr>
                    <tr>
                        <td>
                            <label>Ambiente</label><br>
                            <select id="master-app-env" class="regular-text">
                                <option value="production">Produção</option>
                                <option value="sandbox">Sandbox</option>
                                <option value="staging">Staging</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit" style="text-align:right;">
                    <button type="button" id="was-master-app-cancel" class="button">Cancelar</button>
                    <button type="submit" class="button button-primary">Salvar App</button>
                </p>
            </form>
        </div>
    </div>
</div>

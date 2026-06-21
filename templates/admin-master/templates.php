<?php
/**
 * Master Admin Templates Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Gerenciamento de Templates Globais</h1>
    <p class="description">Visualize todos os modelos de mensagens de todos os clientes.</p>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Nome do Template</th>
                <th>Categoria</th>
                <th>Idioma</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody id="master-templates-list">
            <tr><td colspan="6">Carregando templates...</td></tr>
        </tbody>
    </table>

    <!-- Modal Template Payload -->
    <div id="was-master-tpl-payload-modal" class="was-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="was-modal-content" style="background:white; margin:5% auto; padding:20px; width:800px; max-height:80vh; overflow-y:auto; border-radius:8px;">
            <h2>Payload do Template: <span id="master-tpl-name-title"></span></h2>
            
            <div style="margin-top:15px;">
                <strong>Payload Amigável (Builder)</strong>
                <pre id="master-tpl-friendly-pre" style="background:#f4f4f4; padding:10px; border-radius:4px; font-size:0.8rem;"></pre>
            </div>

            <div style="margin-top:15px;">
                <strong>Payload Meta (API)</strong>
                <pre id="master-tpl-meta-pre" style="background:#f4f4f4; padding:10px; border-radius:4px; font-size:0.8rem;"></pre>
            </div>

            <p class="submit" style="text-align:right;">
                <button type="button" id="was-master-tpl-payload-close" class="button">Fechar</button>
            </p>
        </div>
    </div>
</div>

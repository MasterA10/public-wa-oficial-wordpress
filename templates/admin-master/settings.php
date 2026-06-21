<?php
/**
 * Master Admin Global Settings Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>Configurações Globais do SaaS</h1>
    <p class="description">Ajustes técnicos e credenciais mestras que afetam toda a plataforma.</p>

    <div id="was-master-settings-app" style="margin-top: 20px;">
        <form id="was-master-settings-form" onsubmit="event.preventDefault(); return false;">
            <div class="card" style="max-width: 800px;">
                <h2>Parâmetros da Meta Graph API</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="master_graph_version">Versão Padrão da Graph API</label></th>
                        <td>
                            <input name="master_graph_version" type="text" id="master_graph_version" value="v25.0" class="regular-text">
                            <p class="description">Utilizada como fallback quando não definida no App Meta.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="master_webhook_url">URL de Webhook (Global)</label></th>
                        <td>
                            <input name="master_webhook_url" type="text" id="master_webhook_url" value="<?php echo esc_url(home_url('/was-meta-check-99')); ?>" class="large-text" readonly>
                            <p class="description">Esta é a URL que deve ser configurada em todos os Apps Meta.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Políticas de Operação</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="master_msg_rate_limit">Limite de Mensagens/Min (por Tenant)</label></th>
                        <td>
                            <input name="master_msg_rate_limit" type="number" id="master_msg_rate_limit" value="60" class="small-text">
                            <p class="description">Proteção contra spam e estouro de limites da Meta.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="master_log_retention">Retenção de Logs (Dias)</label></th>
                        <td>
                            <input name="master_log_retention" type="number" id="master_log_retention" value="90" class="small-text">
                            <p class="description">Logs mais antigos que isso serão removidos automaticamente.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="master_polling_interval">Intervalo de Polling (ms)</label></th>
                        <td>
                            <input name="master_polling_interval" type="number" id="master_polling_interval" value="3000" class="small-text" min="500" step="500">
                            <p class="description">Velocidade de atualização do chat em tempo real (ex: 3000 = 3 seg). Diminua para App Review.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <button type="button" id="was-btn-save-master-settings" class="button button-primary">Salvar Configurações Master</button>
            </p>
        </form>
    </div>
</div>

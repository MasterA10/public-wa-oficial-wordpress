<?php
/**
 * Template para WhatsApp Setup e Embedded Signup
 */
if (!defined('ABSPATH')) {
    exit;
}

$tenant_id = \WAS\Auth\TenantContext::get_current_tenant_id();
global $wpdb;
$settings_table = \WAS\Core\TableNameResolver::get_table_name('settings');
$embedded_signup_url = $wpdb->get_var($wpdb->prepare(
    "SELECT setting_value FROM $settings_table WHERE tenant_id = %d AND setting_key = 'embedded_signup_url'",
    $tenant_id
));
$meta_app = (new \WAS\Meta\MetaAppRepository())->get_active_app(false);
$has_signup_config = (bool) ($embedded_signup_url || ($meta_app && !empty($meta_app->app_id) && !empty($meta_app->config_id)));
?>
<div class="wrap">
    <h1>WhatsApp Business Setup</h1>
    <hr>

    <?php if (!$has_signup_config): ?>
        <div class="notice notice-warning">
            <p>Você precisa configurar a <strong>URL do Cadastro Incorporado</strong> antes de conectar uma conta WhatsApp. Vá em <a href="<?php echo \WAS\Core\URLService::get_meta_settings_url(); ?>">Configurações Meta</a>.</p>
        </div>
    <?php else: ?>
        <div id="was-whatsapp-setup-app">
                <!-- Nova seção de Verificação de Conexão -->
                <div class="was-verify-connection-box" style="margin: 20px 0; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div>
                            <h2 style="margin:0; font-size: 1.25rem;">Diagnóstico de Conexão Oficial</h2>
                            <p class="description">Valide se sua integração com a Meta Cloud API está 100% operacional.</p>
                        </div>
                        <button id="was-btn-check-connection" class="button button-primary">Verificar conexão oficial</button>
                    </div>

                    <div id="was-verify-results" style="display:none;">
                        <div id="was-verify-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                            <!-- Preenchido via JS -->
                        </div>
                    </div>
                </div>

                <div class="card" style="max-width: 800px; margin-top: 20px;">
                    <strong>URL do Webhook:</strong><br>
                    <code><?php echo esc_url_raw(rest_url(WAS_REST_NAMESPACE . '/meta/webhook')); ?></code>
                    <p class="description">Use esta URL na configuração do produto "WhatsApp" no seu painel da Meta.</p>
                </div>

                <div id="was-connect-actions">
                    <label for="was-signup-phone" style="display:block; margin: 20px 0 6px; font-weight:600;">Número WhatsApp da empresa</label>
                    <input id="was-signup-phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+55 (11) 99999-9999" style="width: min(100%, 360px); padding: 8px;">
                    <p class="description">Informe o número que será conectado no cadastro incorporado. Ele será usado para confirmar o número correto no tenant.</p>
                    <button id="was-launch-signup" class="button button-primary button-large" style="background-color: #1877f2; border-color: #1877f2;">
                        Embedded Signup
                    </button>
                    <button id="was-disconnect-waba" class="button button-link-delete" style="display: none;">Desconectar Conta</button>
                    <div id="was-onboarding-result" role="status" aria-live="polite" style="display:none; margin-top:16px;"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

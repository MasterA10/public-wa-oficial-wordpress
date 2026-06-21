<?php

namespace WAS\REST;

use WAS\Meta\MetaAppRepository;
use WAS\Meta\TokenVault;
use WAS\Compliance\AuditLogger;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller REST para configuração do Meta App
 */
class MetaApiController {

    private $repository;

    public function __construct() {
        $this->repository = new MetaAppRepository();
    }

    /**
     * Obtém a configuração atual do Meta App.
     */
    public function get_config(WP_REST_Request $request) {
        $app = $this->repository->get_active_app(false); // Não descriptografar aqui

        if (!$app) {
            $app = (object)[
                'app_id' => '',
                'app_secret' => '',
                'config_id' => '',
                'graph_version' => 'v25.0',
                'verify_token' => ''
            ];
        }
$phone_service = new \WAS\WhatsApp\PhoneNumberService();
$token_service = new \WAS\Meta\TokenService();
$tenant_id = \WAS\Auth\TenantContext::get_tenant_id();
$raw_token = $token_service->get_active_token($tenant_id);

// Buscar WABA ID atual
global $wpdb;
$acc_table = \WAS\Core\TableNameResolver::get_table_name('whatsapp_accounts');
$waba_id = $wpdb->get_var($wpdb->prepare("SELECT waba_id FROM $acc_table WHERE tenant_id = %d LIMIT 1", $tenant_id));

// Buscar URL do Cadastro Incorporado
$settings_table = \WAS\Core\TableNameResolver::get_table_name('settings');
$embedded_signup_url = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $settings_table WHERE tenant_id = %d AND setting_key = 'embedded_signup_url'", $tenant_id));

        // Tenta descriptografar o segredo se existir
        $app_secret_masked = '';
        if (!empty($app->app_secret)) {
            try {
                $app_secret_masked = TokenVault::mask(TokenVault::decrypt($app->app_secret));
            } catch (\Exception $e) {
                $app_secret_masked = '********'; // Fallback se falhar
            }
        }

        return new WP_REST_Response([
            'app_id'        => $app->app_id,
            'app_secret'    => $app->app_secret ? '********' : '',
            'config_id'     => $app->config_id ?? '',
            'graph_version' => $app->graph_version,
            'verify_token'  => $app->verify_token ? '********' : '',
            'webhook_url'   => home_url('/was-meta-check-99'),
            'oauth_callback_url' => rest_url(WAS_REST_NAMESPACE . '/meta/oauth/callback'),
            'deauthorize_url'    => rest_url(WAS_REST_NAMESPACE . '/meta/deauthorize'),
            'data_deletion_url'  => rest_url(WAS_REST_NAMESPACE . '/meta/data-deletion'),
            'privacy_policy_url' => home_url('/privacy-policy'),
            'terms_of_service_url' => home_url('/terms-of-service'),
            'support_url'        => home_url('/support'),
            'primary_phone_number_id' => $phone_service->get_primary_id($tenant_id),
            'meta_access_token' => $raw_token ? '********' : '',
            'waba_id'       => $waba_id ?: '',
            'embedded_signup_url' => $embedded_signup_url ?: ''
        ], 200);
    }

    /**
     * Revela a configuração do Meta App, mascarada corretamente, após validação de senha.
     */
    public function reveal_config(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $password = $params['password'] ?? '';
        
        $user = wp_get_current_user();
        
        if (!$user->exists() || !wp_check_password($password, $user->data->user_pass, $user->ID)) {
            return new WP_REST_Response(['message' => 'Senha incorreta ou usuário não autenticado.'], 403);
        }

        // Se a senha for válida, retornamos os dados com a máscara de exibição segura (e não os asteriscos fixos)
        $app = $this->repository->get_active_app(false);
        $phone_service = new \WAS\WhatsApp\PhoneNumberService();
        $token_service = new \WAS\Meta\TokenService();
        $tenant_id = \WAS\Auth\TenantContext::get_tenant_id();
        $raw_token = $token_service->get_active_token($tenant_id);

        $app_secret_masked = '';
        if ($app && !empty($app->app_secret)) {
            try {
                $app_secret_masked = TokenVault::mask(TokenVault::decrypt($app->app_secret));
            } catch (\Exception $e) {
                $app_secret_masked = 'ERROR_DECRYPTING';
            }
        }

        return new WP_REST_Response([
            'app_secret'    => $app_secret_masked,
            'meta_access_token' => $raw_token ? TokenVault::mask($raw_token, 8) : '',
            'verify_token'  => $app ? $app->verify_token : ''
        ], 200);
    }

    /**
     * Salva a configuração do Meta App.
     */
    public function save_config(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $tenant_id = \WAS\Auth\TenantContext::get_tenant_id();
        $whatsapp_account_id = null;

        if (empty($params['app_id'])) {
            return new WP_REST_Response(['message' => 'App ID é obrigatório'], 400);
        }

        try {
            // Se o secret vier mascarado (asteriscos ou pontos), não atualizamos
            if (isset($params['app_secret']) && (strpos($params['app_secret'], '...') !== false || $params['app_secret'] === '********')) {
                unset($params['app_secret']);
            }
            
            // O mesmo para verify_token
            if (isset($params['verify_token']) && $params['verify_token'] === '********') {
                unset($params['verify_token']);
            }

            $result = $this->repository->save_app($params);

            if ($result === false) {
                global $wpdb;
                $db_error = $wpdb->last_error ? ' | DB Error: ' . $wpdb->last_error : '';
                return new WP_REST_Response(['message' => 'Erro ao salvar configuração do App no banco' . $db_error], 500);
            }

            // Salvar URL do Cadastro Incorporado (nas configurações do Tenant)
            if (isset($params['embedded_signup_url'])) {
                global $wpdb;
                $settings_table = \WAS\Core\TableNameResolver::get_table_name('settings');
                $url = sanitize_text_field($params['embedded_signup_url']);
                
                $existing_setting = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $settings_table WHERE tenant_id = %d AND setting_key = 'embedded_signup_url'",
                    $tenant_id
                ));
                
                if ($existing_setting) {
                    $wpdb->update($settings_table, ['setting_value' => $url], ['tenant_id' => $tenant_id, 'setting_key' => 'embedded_signup_url']);
                } else {
                    $wpdb->insert($settings_table, [
                        'tenant_id' => $tenant_id,
                        'setting_key' => 'embedded_signup_url',
                        'setting_value' => $url
                    ]);
                }
            }

            // Salvar WABA ID se fornecido
            if (!empty($params['waba_id'])) {
                global $wpdb;
                $acc_table = \WAS\Core\TableNameResolver::get_table_name('whatsapp_accounts');
                $existing_acc = $wpdb->get_var($wpdb->prepare("SELECT id FROM $acc_table WHERE tenant_id = %d LIMIT 1", $tenant_id));

                if ($existing_acc) {
                    $wpdb->update($acc_table, ['waba_id' => sanitize_text_field($params['waba_id'])], ['id' => $existing_acc]);
                    $whatsapp_account_id = (int) $existing_acc;
                } else {
                    $wpdb->insert($acc_table, [
                        'tenant_id' => $tenant_id,
                        'waba_id'   => sanitize_text_field($params['waba_id']),
                        'name'      => 'Conta WhatsApp',
                        'status'    => 'active',
                        'created_at' => current_time('mysql', 1)
                    ]);
                    $whatsapp_account_id = (int) $wpdb->insert_id;
                }
            }

            // Salvar Access Token se fornecido e não mascarado
            if (!empty($params['meta_access_token']) && strpos($params['meta_access_token'], '...') === false && $params['meta_access_token'] !== '********') {
                global $wpdb;
                $token_table = \WAS\Core\TableNameResolver::get_table_name('meta_tokens');
                $encrypted_token = TokenVault::encrypt($params['meta_access_token']);

                // Limpa antigos e insere o novo (Simplificado para o MVP)
                $wpdb->delete($token_table, ['tenant_id' => $tenant_id]);
                $wpdb->insert($token_table, [
                    'tenant_id' => $tenant_id,
                    'whatsapp_account_id' => $whatsapp_account_id ?: $this->get_whatsapp_account_id_for_tenant($tenant_id),
                    'access_token_encrypted' => $encrypted_token,
                    'status' => 'active',
                    'created_at' => current_time('mysql', 1)
                ]);
            }

            // Salvar Phone Number ID se fornecido
            if (!empty($params['primary_phone_number_id'])) {
                $phone_service = new \WAS\WhatsApp\PhoneNumberService();
                $phone_service->register_phone_number(sanitize_text_field($params['primary_phone_number_id']), $tenant_id);
            }

            AuditLogger::log('save_meta_config', 'meta_app', (string)$result, [
                'app_id' => $params['app_id']
            ]);

            return new WP_REST_Response(['message' => 'Configurações salvas com sucesso', 'id' => $result], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    private function get_whatsapp_account_id_for_tenant($tenant_id) {
        global $wpdb;
        $acc_table = \WAS\Core\TableNameResolver::get_table_name('whatsapp_accounts');

        $account_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $acc_table WHERE tenant_id = %d ORDER BY id ASC LIMIT 1", $tenant_id));

        return $account_id ? (int) $account_id : null;
    }

    /**
     * Verifica permissão para gerenciar Meta App.
     */
    public function permissions_check() {
        if (!Routes::check_auth()) {
            return false;
        }
        // Exige privilégios de administrador/platform owner para ler e escrever
        return current_user_can('manage_options') || current_user_can('was_platform_admin');
    }
}

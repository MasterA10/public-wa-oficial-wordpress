<?php

namespace WAS\Meta;

use WAS\Core\TableNameResolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MetaAppRepository
 * 
 * Centraliza leitura e escrita de configuração do Meta App.
 */
class MetaAppRepository {

    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('meta_apps');
    }

    /**
     * Obtém o aplicativo Meta ativo (único para a plataforma).
     * 
     * @param bool $decrypt_secret Se deve descriptografar o app_secret.
     * @return object|null
     */
    public function get_active_app(bool $decrypt_secret = false) {
        global $wpdb;

        $row = $wpdb->get_row("SELECT * FROM {$this->table_name} WHERE status = 'active' ORDER BY is_default DESC, id ASC LIMIT 1");

        if ($row && $decrypt_secret && !empty($row->app_secret)) {
            try {
                $row->app_secret = TokenVault::decrypt($row->app_secret);
            } catch (\Exception $e) {
                // Log error or handle
                $row->app_secret = '';
            }
        }

        if ( ! $row && getenv( 'META_APP_ID' ) ) {
            $row = (object) [
                'id' => 0,
                'app_id' => getenv( 'META_APP_ID' ),
                'app_secret' => getenv( 'META_APP_SECRET' ) ?: '',
                'config_id' => getenv( 'META_EMBEDDED_SIGNUP_CONFIGURATION_ID' ) ?: '',
                'embedded_signup_config_id' => getenv( 'META_EMBEDDED_SIGNUP_CONFIGURATION_ID' ) ?: '',
                'graph_version' => getenv( 'META_GRAPH_API_VERSION' ) ?: ( defined( 'WAS_META_GRAPH_DEFAULT_VERSION' ) ? WAS_META_GRAPH_DEFAULT_VERSION : 'v25.0' ),
                'verify_token' => getenv( 'META_WEBHOOK_VERIFY_TOKEN' ) ?: '',
                'status' => 'active',
            ];
        }
        return $row;
    }

    public function get_active_app_for_verify_token($token, bool $decrypt_secret = false) {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE status = 'active' ORDER BY is_default DESC, id ASC LIMIT 100");
        foreach ( $rows as $row ) {
            if ( ! empty( $row->verify_token ) && hash_equals( (string) $row->verify_token, (string) $token ) ) {
                if ( $decrypt_secret && ! empty( $row->app_secret ) ) {
                    $row->app_secret = TokenVault::decrypt( $row->app_secret );
                }
                return $row;
            }
        }
        if ( getenv( 'META_APP_ID' ) && getenv( 'META_WEBHOOK_VERIFY_TOKEN' ) && hash_equals( (string) getenv( 'META_WEBHOOK_VERIFY_TOKEN' ), (string) $token ) ) {
            return $this->get_active_app( $decrypt_secret );
        }
        return null;
    }

    /**
     * Salva ou atualiza a configuração do aplicativo Meta.
     * 
     * @param array $data [app_id, app_secret, graph_version, verify_token]
     * @return int|bool ID do registro ou false em falha.
     */
    public function save_app(array $data) {
        global $wpdb;

        $existing = $this->get_active_app();

        $prepared_data = [
            'app_id'        => sanitize_text_field($data['app_id']),
            'config_id'     => sanitize_text_field($data['config_id'] ?? ''),
            'graph_version' => sanitize_text_field($data['graph_version'] ?? WAS_META_GRAPH_DEFAULT_VERSION),
            'verify_token'  => sanitize_text_field($data['verify_token']),
            'updated_at'    => current_time('mysql', true),
        ];

        if (!empty($data['app_secret'])) {
            $prepared_data['app_secret'] = TokenVault::encrypt($data['app_secret']);
        }

        if ($existing && (int) $existing->id > 0) {
            $result = $wpdb->update(
                $this->table_name,
                $prepared_data,
                ['id' => $existing->id]
            );
            if ($result === false) {
                error_log('WAS Error [MetaAppRepository::save_app update]: ' . $wpdb->last_error);
            }
            return $result !== false ? $existing->id : false;
        } else {
            $prepared_data['created_at'] = current_time('mysql', true);
            $result = $wpdb->insert($this->table_name, $prepared_data);
            if ($result === false) {
                error_log('WAS Error [MetaAppRepository::save_app insert]: ' . $wpdb->last_error);
            }
            return $result ? $wpdb->insert_id : false;
        }
    }
}

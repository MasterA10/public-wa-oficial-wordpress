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

        $row = $wpdb->get_row("SELECT * FROM {$this->table_name} LIMIT 1");

        if ($row && $decrypt_secret && !empty($row->app_secret)) {
            try {
                $row->app_secret = TokenVault::decrypt($row->app_secret);
            } catch (\Exception $e) {
                // Log error or handle
                $row->app_secret = '';
            }
        }

        return $row;
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

        if ($existing) {
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

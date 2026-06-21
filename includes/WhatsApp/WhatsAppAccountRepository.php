<?php

namespace WAS\WhatsApp;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository para Contas WhatsApp (WABA)
 */
class WhatsAppAccountRepository {

    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('whatsapp_accounts');
    }

    /**
     * Busca conta pelo WABA ID.
     */
    public function findByWabaId(string $waba_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE waba_id = %s",
            $waba_id
        ));
    }

    /**
     * Busca contas do tenant atual.
     */
    public function getByTenant(?int $tenant_id = null) {
        global $wpdb;
        $tenant_id = $tenant_id ?: TenantContext::get_tenant_id();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d",
            $tenant_id
        ));
    }

    /**
     * Busca uma conta do tenant, filtrando opcionalmente por waba_id.
     */
    public function findForTenant(int $tenant_id, ?string $waba_id = null) {
        global $wpdb;
        if ($waba_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE tenant_id = %d AND waba_id = %s LIMIT 1",
                $tenant_id,
                $waba_id
            ));
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d LIMIT 1",
            $tenant_id
        ));
    }

    /**
     * Cria ou atualiza uma conta.
     */
    public function createOrUpdate(array $data) {
        global $wpdb;
        
        $data['tenant_id'] = $data['tenant_id'] ?? TenantContext::getTenantId();
        $existing = $this->findByWabaId($data['waba_id']);

        $prepared = [
            'tenant_id'    => $data['tenant_id'],
            'waba_id'      => $data['waba_id'],
            'name'         => sanitize_text_field($data['name'] ?? ''),
            'status'       => sanitize_text_field($data['status'] ?? 'active'),
            'updated_at'   => current_time('mysql', true),
        ];

        if ($existing) {
            $wpdb->update($this->table_name, $prepared, ['id' => $existing->id]);
            return $existing->id;
        } else {
            $prepared['created_at'] = current_time('mysql', true);
            $wpdb->insert($this->table_name, $prepared);
            return $wpdb->insert_id;
        }
    }
}

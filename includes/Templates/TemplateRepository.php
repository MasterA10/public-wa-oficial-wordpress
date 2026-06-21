<?php
namespace WAS\Templates;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('message_templates');
    }

    public function create(array $data) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        if (!$tenant_id && defined('WP_CLI')) {
            $tenant_id = 1; // Fallback para testes CLI
        }

        $defaults = [
            'tenant_id' => $tenant_id,
            'whatsapp_account_id' => 0, // Fallback
            'status' => 'draft',
            'created_at' => current_time('mysql', 1),
            'updated_at' => current_time('mysql', 1),
        ];

        // Se não vier account_id, busca o primeiro do tenant
        if (!isset($data['whatsapp_account_id'])) {
            $acc_table = TableNameResolver::get_table_name('whatsapp_accounts');
            $account_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $acc_table WHERE tenant_id = %d LIMIT 1", $tenant_id));
            if ($account_id) {
                $defaults['whatsapp_account_id'] = $account_id;
            }
        }

        $payload = array_merge($defaults, $data);
        
        $result = $wpdb->insert($this->table_name, $payload);

        return $result ? $wpdb->insert_id : false;
    }

    public function update($id, array $data) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        $data['updated_at'] = current_time('mysql', 1);

        return $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $id, 'tenant_id' => $tenant_id]
        );
    }

    public function delete($id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->delete(
            $this->table_name,
            ['id' => $id, 'tenant_id' => $tenant_id]
        );
    }

    public function get_by_id($id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND tenant_id = %d",
            $id,
            $tenant_id
        ));
    }

    public function get_by_name_lang($name, $language) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE name = %s AND language = %s AND tenant_id = %d AND deleted_at IS NULL",
            $name,
            $language,
            $tenant_id
        ));
    }

    /**
     * Busca um template mesmo que esteja marcado como deletado.
     * Útil para evitar erros de 'Duplicate Entry' na sincronização.
     */
    public function find_any_by_name_lang($tenant_id, $waba_id, $name, $language) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE tenant_id = %d AND waba_id = %s AND name = %s AND language = %s 
             LIMIT 1",
            $tenant_id,
            $waba_id,
            $name,
            $language
        ));
    }

    public function getTable() {
        return $this->table_name;
    }

    public function findByWabaNameLanguage($tenant_id, $waba_id, $name, $language) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d AND waba_id = %s AND name = %s AND language = %s AND deleted_at IS NULL",
            $tenant_id,
            $waba_id,
            $name,
            $language
        ));
    }

    public function findForTenant($id, $tenant_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND tenant_id = %d AND deleted_at IS NULL",
            $id,
            $tenant_id
        ));
    }

    public function softDelete($id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        return $wpdb->update(
            $this->table_name,
            ['status' => 'DELETED', 'deleted_at' => current_time('mysql', 1)],
            ['id' => $id, 'tenant_id' => $tenant_id]
        );
    }

    public function list_templates($limit = 50, $offset = 0) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT %d OFFSET %d",
            $tenant_id,
            $limit,
            $offset
        );
        $results = $wpdb->get_results($sql);
        return $results;
    }
}

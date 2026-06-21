<?php
namespace WAS\WhatsApp;

use WAS\Core\TableNameResolver;

if (!defined('ABSPATH')) {
    exit;
}

class PhoneNumberService {
    /**
     * Registra ou atualiza um número de telefone para um tenant.
     */
    public function register_phone_number($phone_number_id, $tenant_id, $display_phone = '') {
        global $wpdb;
        $table = TableNameResolver::get_table_name('whatsapp_phone_numbers');
        $whatsapp_account_id = $this->get_whatsapp_account_id_for_tenant($tenant_id);

        // Primeiro, desmarca qualquer outro número como default para este tenant
        $wpdb->update($table, ['is_default' => 0], ['tenant_id' => $tenant_id]);

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE phone_number_id = %s",
            $phone_number_id
        ));

        if ($existing) {
            return $wpdb->update($table, [
                'tenant_id' => $tenant_id,
                'whatsapp_account_id' => $whatsapp_account_id,
                'display_phone_number' => $display_phone,
                'is_default' => 1 // Marca como principal
            ], ['id' => $existing]);
        }

        return $wpdb->insert($table, [
            'tenant_id' => $tenant_id,
            'whatsapp_account_id' => $whatsapp_account_id,
            'phone_number_id' => $phone_number_id,
            'display_phone_number' => $display_phone,
            'status' => 'active',
            'is_default' => 1, // Marca como principal
            'created_at' => current_time('mysql', 1)
        ]);
    }

    /**
     * Obtém o Phone Number ID principal de um tenant.
     */
    public function get_primary_id($tenant_id) {
        global $wpdb;
        $table = TableNameResolver::get_table_name('whatsapp_phone_numbers');
        
        // Busca primeiro o que for default, se não houver, pega o mais recente
        return $wpdb->get_var($wpdb->prepare(
            "SELECT phone_number_id FROM $table WHERE tenant_id = %d ORDER BY is_default DESC, created_at DESC LIMIT 1",
            $tenant_id
        ));
    }

    private function get_whatsapp_account_id_for_tenant($tenant_id) {
        global $wpdb;
        $accounts_table = TableNameResolver::get_table_name('whatsapp_accounts');

        $account_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $accounts_table WHERE tenant_id = %d ORDER BY id ASC LIMIT 1",
            $tenant_id
        ));

        return $account_id ? (int) $account_id : 1;
    }
}

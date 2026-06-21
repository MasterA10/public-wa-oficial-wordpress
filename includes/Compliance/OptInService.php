<?php

namespace WAS\Compliance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço de Opt-In
 */
class OptInService {
    
    /**
     * Registra opt-in manual de um contato
     */
    public function registerOptIn($contact_id, $source = 'manual', $consent_text = 'Consentimento manual via painel') {
        global $wpdb;
        $optins_table = $wpdb->prefix . 'was_contact_optins';
        $contacts_table = $wpdb->prefix . 'was_contacts';

        $wpdb->insert($optins_table, [
            'tenant_id'    => \WAS\Auth\TenantContext::getTenantId(),
            'contact_id'   => $contact_id,
            'source'       => $source,
            'consent_text' => $consent_text,
            'status'       => 'active',
            'created_at'   => current_time('mysql', 1)
        ]);

        // Atualiza status no contato
        $wpdb->update($contacts_table, ['opt_in_status' => 'opt_in'], ['id' => $contact_id]);

        AuditLogger::log('register_opt_in', 'contact', $contact_id, ['source' => $source]);
    }
}

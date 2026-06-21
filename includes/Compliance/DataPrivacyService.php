<?php

namespace WAS\Compliance;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço de Privacidade de Dados
 */
class DataPrivacyService {
    
    /**
     * Exporta dados de um contato
     */
    public function exportContactData($contact_id) {
        global $wpdb;
        $tenant_id = TenantContext::getTenantId();
        
        // Simulação de busca em was_contacts (WAS-017) e was_messages (WAS-020)
        // Como o Dev 03 ainda não criou os repositórios, faremos via SQL direto para o MVP
        $contacts_table = $wpdb->prefix . 'was_contacts';
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $contacts_table WHERE id = %d AND tenant_id = %d",
            $contact_id,
            $tenant_id
        ));

        if (!$contact) {
            throw new \Exception("Contato não encontrado.");
        }

        return [
            'contact' => $contact,
            'exported_at' => current_time('mysql', 1)
        ];
    }

    /**
     * Anonimiza ou exclui dados de um contato
     */
    public function deleteContactData($contact_id) {
        global $wpdb;
        $tenant_id = TenantContext::getTenantId();
        $contacts_table = $wpdb->prefix . 'was_contacts';

        // Auditoria antes de apagar
        AuditLogger::log('delete_contact_data', 'contact', $contact_id);

        return $wpdb->update($contacts_table, [
            'phone' => 'ANONYMOZED',
            'profile_name' => 'DELETED',
            'opt_in_status' => 'revoked'
        ], [
            'id' => $contact_id,
            'tenant_id' => $tenant_id
        ]);
    }
}

<?php

namespace WAS\WhatsApp;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository para Números WhatsApp
 */
class PhoneNumberRepository {

    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('whatsapp_phone_numbers');
    }

    /**
     * Busca número pelo Phone Number ID da Meta.
     */
    public function findByPhoneNumberId(string $phone_number_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE phone_number_id = %s",
            $phone_number_id
        ));
    }

    /**
     * Busca número padrão do tenant.
     */
    public function getDefaultByTenant(?int $tenant_id = null) {
        global $wpdb;
        $tenant_id = $tenant_id ?: TenantContext::getTenantId();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d ORDER BY is_default DESC, created_at DESC LIMIT 1",
            $tenant_id
        ));
    }

    /**
     * Busca o número principal vinculado a uma conta WhatsApp.
     */
    public function getPrimaryForAccount(int $whatsapp_account_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE whatsapp_account_id = %d ORDER BY is_default DESC, created_at DESC LIMIT 1",
            $whatsapp_account_id
        ));
    }

    /**
     * Cria ou atualiza um número.
     */
    public function createOrUpdate(array $data) {
        global $wpdb;

        $data['tenant_id'] = $data['tenant_id'] ?? TenantContext::getTenantId();
        $existing = $this->findByPhoneNumberId($data['phone_number_id']);

        $prepared = [
            'tenant_id'            => $data['tenant_id'],
            'whatsapp_account_id'  => $data['whatsapp_account_id'], // ID interno da was_whatsapp_accounts
            'phone_number_id'      => $data['phone_number_id'],
            'display_phone_number' => sanitize_text_field($data['display_phone_number'] ?? ''),
            'verified_name'        => sanitize_text_field($data['verified_name'] ?? ''),
            'quality_rating'       => sanitize_text_field($data['quality_rating'] ?? 'UNKNOWN'),
            'status'               => sanitize_text_field($data['status'] ?? 'active'),
            'is_default'           => !empty($data['is_default']) ? 1 : 0,
            'updated_at'           => current_time('mysql', true),
        ];

        if ($existing) {
            $wpdb->update($this->table_name, $prepared, ['id' => $existing->id]);
            return $existing->id;
        } else {
            // Se for o primeiro número do tenant, define como padrão
            $default = $this->getDefaultByTenant($data['tenant_id']);
            if (!$default) {
                $prepared['is_default'] = 1;
            }

            $prepared['created_at'] = current_time('mysql', true);
            $wpdb->insert($this->table_name, $prepared);
            return $wpdb->insert_id;
        }
    }

    /**
     * Atualiza dados do número com base no diagnóstico da Meta.
     */
    public function upsertFromDiagnostics(int $tenant_id, string $waba_id, array $data) {
        global $wpdb;
        $phone_number_id = $data['id'] ?? $data['phone_number_id'];

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE phone_number_id = %s AND tenant_id = %d",
            $phone_number_id,
            $tenant_id
        ));

        $prepared = [
            'display_phone_number' => $data['display_phone_number'] ?? null,
            'verified_name'        => $data['verified_name'] ?? null,
            'status'               => $data['status'] ?? null,
            'name_status'          => $data['details']['name_status'] ?? null,
            'quality_rating'       => $data['quality_rating'] ?? null,
            'messaging_limit_tier' => $data['messaging_limit_tier'] ?? null,
            'account_mode'         => $data['account_mode'] ?? null,
            'health_status_json'   => isset($data['details']['health_status']) ? json_encode($data['details']['health_status']) : null,
            'last_diagnostic_at'   => current_time('mysql', 1),
        ];

        if ($existing) {
            $wpdb->update($this->table_name, $prepared, ['id' => $existing->id]);
        }
    }
}

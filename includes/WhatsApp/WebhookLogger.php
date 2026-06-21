<?php
namespace WAS\WhatsApp;

use WAS\Core\TableNameResolver;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookLogger {
    /**
     * Registra uma tentativa de verificação (GET Challenge)
     */
    public static function log_verification($params, $response_code, $response_body) {
        self::log('verification', 'GET', $params, $response_code, $response_body);
    }

    /**
     * Registra o recebimento de um payload (POST Event)
     */
    public static function log_event($payload, $headers, $response_code, $response_body) {
        self::log('event', 'POST', $payload, $response_code, $response_body, $headers);
    }

    private static function log($type, $method, $data, $response_code, $response_body, $headers = []) {
        global $wpdb;
        $table = TableNameResolver::get_table_name('audit_logs');

        // Sanitizar dados sensíveis se necessário
        $metadata = [
            'type'          => $type,
            'method'        => $method,
            'request_data'  => $data,
            'headers'       => $headers,
            'response_code' => $response_code,
            'response_body' => $response_body,
            'remote_addr'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $wpdb->insert($table, [
            'tenant_id'  => 0, // Global ou resolver do payload
            'action'     => 'WEBHOOK_' . strtoupper($type),
            'entity_type' => 'webhook',
            'metadata'   => json_encode($metadata),
            'created_at' => current_time('mysql', 1)
        ]);
    }
}

<?php

namespace WAS\REST;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;
use WAS\Compliance\DataPrivacyService;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller REST para Compliance e Logs
 */
class ComplianceApiController {
    private $privacyService;

    public function __construct() {
        $this->privacyService = new DataPrivacyService();
    }
    
    /**
     * Lista logs de auditoria
     */
    public function get_audit_logs(WP_REST_Request $request) {
        global $wpdb;
        $table_name = TableNameResolver::getAuditLogsTable();
        $tenant_id = TenantContext::getTenantId();

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.user_login 
             FROM $table_name l 
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE l.tenant_id = %d OR l.tenant_id = 0 
             ORDER BY l.created_at DESC LIMIT 100",
            $tenant_id
        ));

        return new WP_REST_Response($logs, 200);
    }

    /**
     * Lista logs técnicos da Meta API
     */
    public function get_meta_api_logs(WP_REST_Request $request) {
        global $wpdb;
        $table_name = TableNameResolver::getMetaApiLogsTable();
        $tenant_id = TenantContext::getTenantId();

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE tenant_id = %d ORDER BY created_at DESC LIMIT 100",
            $tenant_id
        ));

        return new WP_REST_Response($logs, 200);
    }

    /**
     * Exporta contato
     */
    public function export_contact(WP_REST_Request $request) {
        $id = $request->get_param('id');
        try {
            $data = $this->privacyService->exportContactData($id);
            return new WP_REST_Response($data, 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * Exclui/Anonimiza contato
     */
    public function delete_contact(WP_REST_Request $request) {
        $id = $request->get_param('id');
        $this->privacyService->deleteContactData($id);
        return new WP_REST_Response(['message' => 'Solicitação de exclusão processada.'], 200);
    }

    /**
     * Lista eventos de webhook
     */
    public function get_webhook_events(WP_REST_Request $request) {
        global $wpdb;
        $table_name = TableNameResolver::getWebhookEventsTable();

        $events = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY received_at DESC LIMIT 100"
        );

        return new WP_REST_Response($events, 200);
    }

    /**
     * Callback de permissão para compliance
     */
    public function permissions_check() {
        return Routes::check_auth();
    }
}

<?php

namespace WAS\Compliance;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger de Auditoria
 */
class AuditLogger {
    public static function log($action, $entity_type, $entity_id, $metadata = []) {
        global $wpdb;

        $table_name = TableNameResolver::getAuditLogsTable();
        $tenant_id = TenantContext::getTenantId();
        $user_id = get_current_user_id();

        $wpdb->insert($table_name, [
            'tenant_id'   => $tenant_id,
            'user_id'     => $user_id,
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'metadata'    => json_encode($metadata),
            'created_at'  => current_time('mysql', 1)
        ]);
    }
}

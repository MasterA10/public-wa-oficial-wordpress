<?php
/**
 * TableNameResolver class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolver for table names.
 */
class TableNameResolver {

	/**
	 * Get the full table name with prefix.
	 *
	 * @param string $table The table identifier.
	 * @return string
	 */
	public static function get_table_name( $table ) {
		global $wpdb;
		return $wpdb->prefix . 'was_' . $table;
	}

	public static function getAuditLogsTable() {
		return self::get_table_name( 'audit_logs' );
	}

    public static function getMetaApiLogsTable() {
        return self::get_table_name( 'meta_api_logs' );
    }

    public static function getWebhookEventsTable() {
        return self::get_table_name( 'webhook_events' );
    }

    public static function getTemplatesTable() {
        return self::get_table_name( 'message_templates' );
    }

    public static function getOnboardingSessionsTable() {
        return self::get_table_name( 'onboarding_sessions' );
    }

    public static function getHealthChecksTable() {
        return self::get_table_name( 'health_checks' );
    }

    public static function getAdminAuditLogsTable() {
        return self::get_table_name( 'admin_audit_logs' );
    }

    public static function getAppReviewChecklistTable() {
        return self::get_table_name( 'app_review_checklist' );
    }

    public static function getMessageReferralsTable() {
        return self::get_table_name( 'message_referrals' );
    }

    public static function getAuthCodeAttemptsTable() {
        return self::get_table_name( 'auth_code_attempts' );
    }

    public static function getRouterApiTokensTable() {
        return self::get_table_name( 'router_api_tokens' );
    }

    public static function getRoutesTable() {
        return self::get_table_name( 'routes' );
    }

    public static function getOutboxDeliveriesTable() {
        return self::get_table_name( 'outbox_deliveries' );
    }

    public static function getOnboardingRegistrationsTable() {
        return self::get_table_name( 'onboarding_registrations' );
    }

    public static function getOnboardingReconciliationJobsTable() {
        return self::get_table_name( 'onboarding_reconciliation_jobs' );
    }

    public static function getOutboundMessagesTable() {
        return self::get_table_name( 'outbound_messages' );
    }

	/**
	 * List of all plugin tables.
	 *
	 * @return array
	 */
	public static function get_all_tables() {
		return [
			'tenants',
			'tenant_users',
			'meta_apps',
			'whatsapp_accounts',
			'whatsapp_phone_numbers',
			'meta_tokens',
			'contacts',
			'contact_optins',
			'conversations',
			'messages',
			'message_statuses',
			'message_templates',
			'media',
			'webhook_events',
			'audit_logs',
			'meta_api_logs',
			'settings',
			'onboarding_sessions',
			'health_checks',
			'admin_audit_logs',
			'app_review_checklist',
			'message_referrals',
			'auth_code_attempts',
			'meta_oauth_logs',
			'meta_deauthorize_logs',
			'data_deletion_requests',
			'router_api_tokens',
			'routes',
			'outbox_deliveries',
			'onboarding_registrations',
			'onboarding_reconciliation_jobs',
			'outbound_messages',
		];
	}
}

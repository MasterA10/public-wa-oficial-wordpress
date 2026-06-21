<?php
/**
 * DashboardApiController class.
 *
 * @package WAS\REST
 */

namespace WAS\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles dashboard-related REST requests.
 */
class DashboardApiController {

	/**
	 * Get dashboard summary.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_summary( $request ) {
		global $wpdb;
		$tenant_id = \WAS\Auth\TenantContext::get_current_tenant_id();

		$table_wa_accounts = \WAS\Core\TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$table_wa_phones   = \WAS\Core\TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$table_messages    = \WAS\Core\TableNameResolver::get_table_name( 'messages' );
		$table_conversations = \WAS\Core\TableNameResolver::get_table_name( 'conversations' );
		$table_templates   = \WAS\Core\TableNameResolver::get_table_name( 'message_templates' );

		$accounts_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_wa_accounts WHERE tenant_id = %d", $tenant_id ) );
		$phones_count   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_wa_phones WHERE tenant_id = %d AND status = 'active'", $tenant_id ) );
		$messages_today = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_messages WHERE tenant_id = %d AND DATE(created_at) = CURDATE()", $tenant_id ) );
		$conv_open      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_conversations WHERE tenant_id = %d AND status = 'open'", $tenant_id ) );
		$templates_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_templates WHERE tenant_id = %d", $tenant_id ) );
		
		return rest_ensure_response( [
			'whatsapp_accounts'  => (int) $accounts_count,
			'active_numbers'     => (int) $phones_count,
			'messages_today'     => (int) $messages_today,
			'open_conversations' => (int) $conv_open,
			'templates'          => (int) $templates_count,
		] );
	}
}

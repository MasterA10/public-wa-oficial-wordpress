<?php
/**
 * Capabilities class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Capabilities and Roles.
 */
class Capabilities {

	/**
	 * Get all plugin capabilities.
	 *
	 * @return array
	 */
	public static function get_capabilities() {
		return [
			'was_access_app',
			'was_manage_tenant',
			'was_manage_whatsapp',
			'was_manage_templates',
			'was_send_messages',
			'was_view_inbox',
			'was_assign_conversations',
			'was_view_logs',
			'was_manage_billing',
			'was_manage_compliance',
			'was_platform_admin',
			'was_view_master_dashboard',
		];
	}

	/**
	 * Create/Update custom roles and caps.
	 */
	public static function register() {
		$caps = self::get_capabilities();

		// Add caps to administrator.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		// Define SaaS roles.
		self::add_saas_roles();
	}

	/**
	 * Define SaaS roles and their caps.
	 */
	private static function add_saas_roles() {
		// Platform Owner - all caps.
		add_role( 'platform_owner', 'Platform Owner', array_fill_keys( self::get_capabilities(), true ) );

		// Platform Admin.
		add_role( 'platform_admin', 'Platform Admin', [
			'was_access_app'           => true,
			'was_platform_admin'       => true,
			'was_view_master_dashboard' => true,
			'was_view_logs'            => true,
			'was_manage_compliance'    => true,
		] );

		// Platform Support.
		add_role( 'platform_support', 'Platform Support', [
			'was_access_app'           => true,
			'was_view_master_dashboard' => true,
			'was_view_logs'            => true,
		] );

		// Tenant Admin.
		add_role( 'tenant_admin', 'Tenant Admin', [
			'was_access_app'        => true,
			'was_manage_tenant'     => true,
			'was_manage_whatsapp'   => true,
			'was_manage_templates'  => true,
			'was_send_messages'     => true,
			'was_view_inbox'        => true,
			'was_assign_conversations' => true,
			'was_view_logs'         => true,
		] );

		// Manager.
		add_role( 'manager', 'Manager', [
			'was_access_app'        => true,
			'was_manage_templates'  => true,
			'was_send_messages'     => true,
			'was_view_inbox'        => true,
			'was_assign_conversations' => true,
		] );

		// Agent.
		add_role( 'agent', 'Agent', [
			'was_access_app'    => true,
			'was_send_messages' => true,
			'was_view_inbox'    => true,
		] );

		// Viewer.
		add_role( 'viewer', 'Viewer', [
			'was_access_app' => true,
			'was_view_inbox' => true,
		] );

		// Compliance.
		add_role( 'compliance', 'Compliance', [
			'was_access_app'           => true,
			'was_view_logs'            => true,
			'was_manage_compliance'    => true,
		] );
	}
}

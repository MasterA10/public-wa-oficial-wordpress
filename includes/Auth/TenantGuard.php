<?php
/**
 * TenantGuard class.
 *
 * @package WAS\Auth
 */

namespace WAS\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates tenant access.
 */
class TenantGuard {

	/**
	 * Check if the user belongs to the tenant.
	 *
	 * @param int $tenant_id The tenant ID.
	 * @param int $user_id   Optional user ID.
	 * @return bool
	 */
	public static function user_belongs_to_tenant( $tenant_id, $user_id = null ) {
		global $wpdb;
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id || ! $tenant_id ) {
			return false;
		}

		// Platform Owner can access everything (optional, but requested for platform_owner role).
		if ( user_can( $user_id, 'platform_owner' ) ) {
			return true;
		}

		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenant_users' );
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM $table WHERE tenant_id = %d AND user_id = %d AND status = 'active'",
			$tenant_id,
			$user_id
		) );

		return (bool) $exists;
	}

	/**
	 * Ensure the current user has access to the current tenant.
	 * Throws exception or returns false if no access.
	 *
	 * @return bool
	 */
	public static function check_access() {
		$tenant_id = TenantContext::get_current_tenant_id();
		if ( ! $tenant_id ) {
			return false;
		}

		return self::user_belongs_to_tenant( $tenant_id );
	}
}

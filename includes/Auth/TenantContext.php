<?php
/**
 * TenantContext class.
 *
 * @package WAS\Auth
 */

namespace WAS\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the current tenant context.
 */
class TenantContext {

	/**
	 * Current tenant ID.
	 *
	 * @var int|null
	 */
	private static $current_tenant_id = null;

	/**
	 * Get the current tenant ID.
	 *
	 * @return int|null
	 */
	public static function get_current_tenant_id() {
		if ( null !== self::$current_tenant_id ) {
			return self::$current_tenant_id;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return null;
		}

		// Try to get from session/user meta.
		$tenant_id = get_user_meta( $user_id, '_was_current_tenant_id', true );

		if ( ! $tenant_id ) {
			// Fallback: get the first tenant the user belongs to.
			$tenant_id = self::get_first_user_tenant( $user_id );
			if ( $tenant_id ) {
				update_user_meta( $user_id, '_was_current_tenant_id', $tenant_id );
			}
		}

		self::$current_tenant_id = $tenant_id ? (int) $tenant_id : null;
		return self::$current_tenant_id;
	}

	/**
	 * Alias for get_current_tenant_id.
	 */
	public static function getTenantId() {
		return self::get_current_tenant_id();
	}

	/**
	 * Alias for get_current_tenant_id.
	 */
	public static function get_tenant_id() {
		return self::get_current_tenant_id();
	}

	/**
	 * Set the current tenant ID.
	 *
	 * @param int $tenant_id The tenant ID.
	 */
	public static function set_current_tenant_id( $tenant_id ) {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_user_meta( $user_id, '_was_current_tenant_id', $tenant_id );
		}
		self::$current_tenant_id = (int) $tenant_id;
	}

	/**
	 * Alias for set_current_tenant_id.
	 */
	public static function set_tenant_id( $tenant_id ) {
		self::set_current_tenant_id( $tenant_id );
	}

	/**
	 * Get the first tenant ID the user is linked to.
	 *
	 * @param int $user_id The user ID.
	 * @return int|null
	 */
	private static function get_first_user_tenant( $user_id ) {
		global $wpdb;
		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenant_users' );
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT tenant_id FROM $table WHERE user_id = %d LIMIT 1", $user_id ) );
		return $id ? (int) $id : null;
	}
}

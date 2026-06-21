<?php
/**
 * TenantUserRepository class.
 *
 * @package WAS\Tenants
 */

namespace WAS\Tenants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles user-tenant relationship data access.
 */
class TenantUserRepository {

	/**
	 * Attach a user to a tenant.
	 *
	 * @param int    $tenant_id The tenant ID.
	 * @param int    $user_id   The user ID.
	 * @param string $role      The role in the tenant.
	 * @return bool
	 */
	public function attach_user( $tenant_id, $user_id, $role = 'agent' ) {
		global $wpdb;
		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenant_users' );
		
		$result = $wpdb->replace( $table, [
			'tenant_id' => $tenant_id,
			'user_id'   => $user_id,
			'role'      => $role,
			'status'    => 'active',
		] );

		return false !== $result;
	}

	/**
	 * Detach a user from a tenant.
	 *
	 * @param int $tenant_id The tenant ID.
	 * @param int $user_id   The user ID.
	 * @return bool
	 */
	public function detach_user( $tenant_id, $user_id ) {
		global $wpdb;
		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenant_users' );
		
		$result = $wpdb->delete( $table, [
			'tenant_id' => $tenant_id,
			'user_id'   => $user_id,
		] );

		return false !== $result;
	}

	/**
	 * Get all tenants a user belongs to.
	 *
	 * @param int $user_id The user ID.
	 * @return array
	 */
	public function get_user_tenants( $user_id ) {
		global $wpdb;
		$table_users   = \WAS\Core\TableNameResolver::get_table_name( 'tenant_users' );
		$table_tenants = \WAS\Core\TableNameResolver::get_table_name( 'tenants' );
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT t.*, tu.role FROM $table_tenants t 
			JOIN $table_users tu ON t.id = tu.tenant_id 
			WHERE tu.user_id = %d AND tu.status = 'active'",
			$user_id
		) );
	}

	/**
	 * Check if a user belongs to a tenant.
	 *
	 * @param int $user_id   The user ID.
	 * @param int $tenant_id The tenant ID.
	 * @return bool
	 */
	public function user_belongs_to_tenant( $user_id, $tenant_id ) {
		global $wpdb;
		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenant_users' );
		
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM $table WHERE user_id = %d AND tenant_id = %d AND status = 'active'",
			$user_id,
			$tenant_id
		) );

		return (bool) $exists;
	}
}

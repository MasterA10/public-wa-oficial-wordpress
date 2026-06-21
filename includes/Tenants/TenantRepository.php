<?php
/**
 * TenantRepository class.
 *
 * @package WAS\Tenants
 */

namespace WAS\Tenants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles tenant data access.
 */
class TenantRepository {

	/**
	 * Find a tenant by ID.
	 *
	 * @param int $id The tenant ID.
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;
		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenants' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	/**
	 * Find a tenant by slug.
	 *
	 * @param string $slug The tenant slug.
	 * @return object|null
	 */
	public function find_by_slug( $slug ) {
		global $wpdb;
		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenants' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ) );
	}

	/**
	 * Create a new tenant.
	 *
	 * @param array $data Tenant data.
	 * @return int|false
	 */
	public function create( $data ) {
		global $wpdb;
		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenants' );
		
		$data['created_at'] = current_time( 'mysql', true );
		
		$result = $wpdb->insert( $table, $data );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update tenant status.
	 *
	 * @param int    $id     The tenant ID.
	 * @param string $status The new status.
	 * @return bool
	 */
	public function update_status( $id, $status ) {
		global $wpdb;
		$table = \WAS\Core\TableNameResolver::get_table_name( 'tenants' );
		
		$result = $wpdb->update( 
			$table, 
			[ 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ], 
			[ 'id' => $id ] 
		);
		return false !== $result;
	}
}

<?php
/**
 * OnboardingSessionRepository class.
 *
 * @package WAS\Auth
 */

namespace WAS\Auth;

use WAS\Core\TableNameResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for managing onboarding sessions.
 */
class OnboardingSessionRepository {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->table_name = TableNameResolver::getOnboardingSessionsTable();
	}

	/**
	 * Create a new onboarding session.
	 *
	 * @param int    $tenant_id    Tenant ID.
	 * @param int    $user_id      User ID.
	 * @param string $session_uuid Session UUID.
	 * @return int|bool ID of the created session or false on failure.
	 */
	public function create( $tenant_id, $user_id, $session_uuid ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			[
				'tenant_id'    => $tenant_id,
				'user_id'      => $user_id,
				'session_uuid' => $session_uuid,
				'status'       => 'started',
				'started_at'   => current_time( 'mysql', true ),
				'created_at'   => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Find session by UUID.
	 *
	 * @param string $session_uuid Session UUID.
	 * @return object|null
	 */
	public function find_by_uuid( $session_uuid ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $this->table_name WHERE session_uuid = %s LIMIT 1",
				$session_uuid
			)
		);
	}

	/**
	 * Update session data.
	 *
	 * @param string $session_uuid Session UUID.
	 * @param array  $data         Data to update.
	 * @return bool
	 */
	public function update( $session_uuid, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		$result = $wpdb->update(
			$this->table_name,
			$data,
			[ 'session_uuid' => $session_uuid ]
		);

		return $result !== false;
	}
}

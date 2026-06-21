<?php
/**
 * AuthApiController class.
 *
 * @package WAS\REST
 */

namespace WAS\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles auth-related REST requests.
 */
class AuthApiController {

	/**
	 * Get current user and tenant info.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_me( $request ) {
		$user = wp_get_current_user();
		$tenant_id = \WAS\Auth\TenantContext::get_current_tenant_id();
		$tenant = (new \WAS\Tenants\TenantRepository())->find($tenant_id);

		return rest_ensure_response( [
			'id'    => $user->ID,
			'name'  => $user->display_name,
			'email' => $user->user_email,
			'tenant' => [
				'id'   => $tenant_id,
				'name' => $tenant ? $tenant->name : null,
				'slug' => $tenant ? $tenant->slug : null,
			],
			'capabilities' => array_keys( array_filter( $user->allcaps ) ),
		] );
	}
}

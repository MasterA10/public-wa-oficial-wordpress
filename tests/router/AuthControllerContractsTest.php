<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\REST\AuthApiController;

class AuthControllerContractsTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_tenant_id( 1 );
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [
			'id' => 1, 'name' => 'Minha Empresa', 'slug' => 'minha-empresa', 'status' => 'active', 'created_at' => current_time( 'mysql', true ),
		] );
	}

	public function test_get_me_returns_current_user_tenant_and_capabilities() {
		$response = ( new AuthApiController() )->get_me( new WP_REST_Request( 'GET', '/auth/me' ) );
		$data = $response->get_data();

		$this->assert_same( 200, $response->get_status() );
		$this->assert_same( 1, $data['id'] );
		$this->assert_same( 'Test User', $data['name'] );
		$this->assert_same( 1, $data['tenant']['id'] );
		$this->assert_same( 'Minha Empresa', $data['tenant']['name'] );
		$this->assert_true( in_array( 'was_view_inbox', $data['capabilities'], true ) );
	}
}

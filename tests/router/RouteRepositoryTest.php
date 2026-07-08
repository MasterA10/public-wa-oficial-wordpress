<?php

use WAS\Core\TableNameResolver;
use WAS\Router\RouteRepository;
use WAS\Router\RouterApiController;

class RouteRepositoryTest extends WAS_Router_TestCase {

	public function test_allows_same_target_url_for_different_phone_numbers() {
		$repository = new RouteRepository();
		$target_url = 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/';

		$first = $repository->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'name'            => 'Agenda 1',
				'target_url'      => $target_url,
				'secret'          => 'secret-one',
				'is_active'       => true,
			]
		);
		$second = $repository->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 11,
				'name'            => 'Agenda 2',
				'target_url'      => $target_url,
				'secret'          => 'secret-two',
				'is_active'       => true,
			]
		);

		$this->assert_true( $first > 0 );
		$this->assert_true( $second > 0 );
		$this->assert_true( $first !== $second, 'Cada numero precisa ter uma rota propria mesmo com a mesma URL.' );
		$this->assert_count( 2, $GLOBALS['wpdb']->tables[ TableNameResolver::getRoutesTable() ] );
	}

	public function test_reuses_active_route_for_same_phone_and_target_url() {
		$repository = new RouteRepository();
		$target_url = 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/';

		$first = $repository->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'name'            => 'Agenda',
				'target_url'      => $target_url,
				'secret'          => 'old-secret',
				'is_active'       => true,
			]
		);
		$second = $repository->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'name'            => 'Agenda Atualizado',
				'target_url'      => $target_url,
				'secret'          => 'new-secret',
				'is_active'       => true,
			]
		);

		$route = $repository->find( $first );
		$this->assert_same( $first, $second );
		$this->assert_same( 'Agenda Atualizado', $route->name );
		$this->assert_count( 1, $GLOBALS['wpdb']->tables[ TableNameResolver::getRoutesTable() ] );
	}

	public function test_recreates_route_after_deactivation() {
		$repository = new RouteRepository();
		$target_url = 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/';

		$first = $repository->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'target_url'      => $target_url,
				'is_active'       => true,
			]
		);
		$repository->update_status( $first, false, 'disabled' );
		$second = $repository->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'target_url'      => $target_url,
				'is_active'       => true,
			]
		);

		$this->assert_true( $second !== $first );
		$this->assert_same( 'disabled', $repository->find( $first )->status );
		$this->assert_same( 'active', $repository->find( $second )->status );
	}

	public function test_admin_strict_create_returns_conflict_for_same_active_route() {
		$repository = new RouteRepository();
		$target_url = 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/';

		$first = $repository->create_strict(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'target_url'      => $target_url,
				'is_active'       => true,
			]
		);
		$second = $repository->create_strict(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'target_url'      => $target_url,
				'is_active'       => true,
			]
		);

		$this->assert_false( is_wp_error( $first ) );
		$this->assert_true( is_wp_error( $second ) );
		$this->assert_same( 'active_route_already_exists_for_phone', $second->get_error_code() );
	}

	public function test_admin_route_conflict_response_keeps_legacy_detail_shape() {
		$GLOBALS['was_test_options']['was_router_service_secret'] = 'service-secret';
		$target_url = 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/';
		$controller = new RouterApiController();

		$first = new WP_REST_Request( 'POST', '/admin-api/routes' );
		$first->set_header( 'Authorization', 'Bearer service-secret' );
		$first->set_body_params(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'target_url'      => $target_url,
				'is_active'       => true,
			]
		);
		$controller->create_route( $first );

		$duplicate = new WP_REST_Request( 'POST', '/admin-api/routes' );
		$duplicate->set_header( 'Authorization', 'Bearer service-secret' );
		$duplicate->set_body_params(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'target_url'      => $target_url,
				'is_active'       => true,
			]
		);
		$response = $controller->create_route( $duplicate );
		$data = $response->get_data();

		$this->assert_same( 409, $response->get_status() );
		$this->assert_false( $data['success'] );
		$this->assert_same( 'active_route_already_exists_for_phone', $data['error'] );
		$this->assert_same( 'active_route_already_exists_for_phone', $data['detail']['error'] );
		$this->assert_true( str_contains( $data['detail']['message'], 'rota ativa' ) );
	}

	public function test_reactivation_rejects_duplicate_active_route() {
		$repository = new RouteRepository();
		$target_url = 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/';

		$active = $repository->create_strict(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'target_url'      => $target_url,
				'is_active'       => true,
			]
		);
		$inactive = $repository->create_strict(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'target_url'      => $target_url,
				'is_active'       => false,
				'status'          => 'disabled',
			]
		);
		$result = $repository->update_status( $inactive->id, true, 'active' );

		$this->assert_false( is_wp_error( $active ) );
		$this->assert_false( is_wp_error( $inactive ) );
		$this->assert_true( is_wp_error( $result ) );
		$this->assert_same( 'active_route_already_exists_for_phone', $result->get_error_code() );
	}
}

<?php

use WAS\Core\TableNameResolver;
use WAS\Router\ApiTokenService;
use WAS\Router\RawRequestDispatcher;
use WAS\Router\RouterApiController;

class RouterAuthTest extends WAS_Router_TestCase {

	public function test_auth_login_issues_bearer_token_for_admin_credentials() {
		$request = new WP_REST_Request( 'POST', '/auth/login' );
		$request->set_body_params(
			[
				'username' => 'admin',
				'password' => 'secret',
				'name'     => 'Agenda Master',
			]
		);

		$response = ( new RouterApiController() )->login( $request );
		$data = $response->get_data();
		$tokens = $GLOBALS['wpdb']->tables[ TableNameResolver::getRouterApiTokensTable() ] ?? [];

		$this->assert_same( 200, $response->get_status() );
		$this->assert_same( 'bearer', $data['token_type'] );
		$this->assert_true( 0 === strpos( $data['access_token'], 'wasrt_' ) );
		$this->assert_count( 1, $tokens );
		$this->assert_same( 1, (int) $tokens[0]['user_id'] );
		$this->assert_same( 'Agenda Master', $tokens[0]['name'] );
		$this->assert_same( hash( 'sha256', $data['access_token'] ), $tokens[0]['token_hash'] );
	}

	public function test_auth_login_raw_dispatcher_matches_agenda_master_retry_contract() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$request = new WP_REST_Request( 'POST', '/auth/login' );
		$request->set_body_params(
			[
				'username' => 'admin',
				'password' => 'secret',
			]
		);
		$dispatcher = new RawRequestDispatcher();
		$method = ( new ReflectionClass( $dispatcher ) )->getMethod( 'dispatch' );
		$method->setAccessible( true );

		$response = $method->invoke( $dispatcher, 'auth/login', $request );
		$data = $response->get_data();

		$this->assert_same( 200, $response->get_status() );
		$this->assert_same( 'bearer', $data['token_type'] );
		$this->assert_true( 0 === strpos( $data['access_token'], 'wasrt_' ) );
	}

	public function test_raw_whatsapp_send_endpoint_accepts_bearer_token_for_debounce_followup_sender_contract() {
		$this->seed_whatsapp_sender_dependencies();
		$token = ( new ApiTokenService() )->issue_for_user( 1, 'Debounce sender' )['access_token'];
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$request = new WP_REST_Request( 'POST', '/v1/whatsapp/send' );
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		$request->set_body_params(
			[
				'phone_number_id' => 10,
				'to_number'       => '5511999999999',
				'message_type'    => 'text',
				'payload'         => [ 'text' => [ 'body' => 'Ainda posso te ajudar?' ] ],
			]
		);
		$dispatcher = new RawRequestDispatcher();
		$method = ( new ReflectionClass( $dispatcher ) )->getMethod( 'dispatch' );
		$method->setAccessible( true );

		$response = $method->invoke( $dispatcher, 'v1/whatsapp/send', $request );
		$data = $response->get_data();
		$call = $GLOBALS['was_test_http_posts'][0];
		$body = json_decode( $call['args']['body'], true );

		$this->assert_same( 200, $response->get_status() );
		$this->assert_same( 'sent', $data['status'] );
		$this->assert_same( 'wamid.router', $data['wa_message_id'] );
		$this->assert_true( str_contains( $call['url'], '/v25.0/meta-phone-1/messages' ) );
		$this->assert_same( 'Bearer waba-token', $call['args']['headers']['Authorization'] );
		$this->assert_same( 'whatsapp', $body['messaging_product'] );
		$this->assert_same( '5511999999999', $body['to'] );
		$this->assert_same( 'text', $body['type'] );
		$this->assert_same( 'Ainda posso te ajudar?', $body['text']['body'] );
		$this->assert_false( array_key_exists( 'phone_number_id', $body ) );
	}

	public function test_onboarding_registration_rejects_expired_token_then_accepts_fresh_login_token() {
		$this->seed_onboarding_dependencies();
		$expired = ( new ApiTokenService() )->issue_for_user( 1, 'expired-token', gmdate( 'Y-m-d H:i:s', time() - 3600 ) );
		$controller = new RouterApiController();

		$first = new WP_REST_Request( 'POST', '/v1/onboarding/meta/registrations' );
		$first->set_header( 'Authorization', 'Bearer ' . $expired['access_token'] );
		$first->set_body_params( $this->agenda_registration_payload() );
		$first_response = $controller->create_onboarding_registration( $first );
		$first_data = $first_response->get_data();

		$this->assert_same( 401, $first_response->get_status() );
		$this->assert_same( 'router_unauthorized', $first_data['error'] );
		$this->assert_same( 'router_unauthorized', $first_data['detail']['error'] );

		$login = new WP_REST_Request( 'POST', '/auth/login' );
		$login->set_body_params(
			[
				'username' => 'admin',
				'password' => 'secret',
			]
		);
		$fresh_token = $controller->login( $login )->get_data()['access_token'];

		$second = new WP_REST_Request( 'POST', '/v1/onboarding/meta/registrations' );
		$second->set_header( 'Authorization', 'Bearer ' . $fresh_token );
		$second->set_body_params( $this->agenda_registration_payload() );
		$second_response = $controller->create_onboarding_registration( $second );
		$second_data = $second_response->get_data();

		$this->assert_same( 200, $second_response->get_status() );
		$this->assert_same( 'onboarding', $second_data['status'] );
		$this->assert_same( '5511999999999', $second_data['phone_number'] );
		$this->assert_same( [ '551199999999', '5511999999999' ], $second_data['phone_number_variants'] );
		$this->assert_same( 'state=attempt-agenda', str_replace( 'https://facebook.com/dialog?', '', $this->registration_by_attempt( 'attempt-agenda' )->authorization_url ) );
	}

	public function test_auth_me_accepts_issued_token_and_updates_last_used_at() {
		$login = new WP_REST_Request( 'POST', '/auth/login' );
		$login->set_body_params(
			[
				'username' => 'admin',
				'password' => 'secret',
			]
		);
		$token = ( new RouterApiController() )->login( $login )->get_data()['access_token'];

		$request = new WP_REST_Request( 'GET', '/auth/me' );
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		$response = ( new RouterApiController() )->me( $request );
		$data = $response->get_data();
		$tokens = $GLOBALS['wpdb']->tables[ TableNameResolver::getRouterApiTokensTable() ] ?? [];

		$this->assert_same( 200, $response->get_status() );
		$this->assert_same( 'api_token', $data['type'] );
		$this->assert_same( 1, $data['user_id'] );
		$this->assert_same( [ 'router:admin', 'router:service' ], $data['capabilities'] );
		$this->assert_not_null( $tokens[0]['last_used_at'] );
	}

	public function test_auth_me_accepts_service_secret() {
		$GLOBALS['was_test_options']['was_router_service_secret'] = 'service-secret';
		$request = new WP_REST_Request( 'GET', '/auth/me' );
		$request->set_header( 'Authorization', 'Bearer service-secret' );

		$response = ( new RouterApiController() )->me( $request );
		$data = $response->get_data();

		$this->assert_same( 200, $response->get_status() );
		$this->assert_same( 'service', $data['type'] );
		$this->assert_same( 0, $data['user_id'] );
		$this->assert_same( [ 'router:service', 'router:admin' ], $data['capabilities'] );
	}

	public function test_invalid_bearer_token_returns_401() {
		$request = new WP_REST_Request( 'GET', '/auth/me' );
		$request->set_header( 'Authorization', 'Bearer expired-token' );

		$response = ( new RouterApiController() )->me( $request );
		$data = $response->get_data();

		$this->assert_same( 401, $response->get_status() );
		$this->assert_false( $data['success'] );
		$this->assert_same( 'router_unauthorized', $data['error'] );
	}

	private function agenda_registration_payload() {
		return [
			'tenant_id'             => 2,
			'meta_app_id'           => 2,
			'company_id'            => 'company-1',
			'attempt_id'            => 'attempt-agenda',
			'phone_number'          => '+55 11 99999-9999',
			'phone_number_variants' => [ '5511999999999', '551199999999' ],
			'callback_url'          => 'https://client.test/whatsapp/webhooks/waba-router/',
			'authorization_url'     => 'https://facebook.com/dialog?state=attempt-agenda',
			'redirect_uri'          => 'https://client.test/api/integrations/callbacks/meta/',
		];
	}

	private function seed_onboarding_dependencies() {
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::get_table_name( 'tenants' ),
			[
				'id'         => 2,
				'name'       => 'Agenda',
				'slug'       => 'agenda',
				'status'     => 'active',
				'created_at' => current_time( 'mysql', true ),
			]
		);
		$wpdb->insert(
			TableNameResolver::get_table_name( 'meta_apps' ),
			[
				'id'            => 2,
				'name'          => 'Meta App',
				'app_id'        => 'app-1',
				'app_secret'    => 'secret',
				'verify_token'  => 'verify',
				'graph_version' => 'v25.0',
				'status'        => 'active',
				'created_at'    => current_time( 'mysql', true ),
			]
		);
	}

	private function seed_whatsapp_sender_dependencies() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 7, 'tenant_id' => 1, 'meta_app_id' => 1, 'waba_id' => 'meta-waba-1', 'name' => 'WABA', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [ 'id' => 10, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'phone_number_id' => 'meta-phone-1', 'display_phone_number' => '+55 11 99999-9999', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [ 'id' => 20, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'access_token_encrypted' => \WAS\Meta\TokenVault::encrypt( 'waba-token' ), 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'messages' => [ [ 'id' => 'wamid.router' ] ] ],
		];
	}

	private function registration_by_attempt( $attempt_id ) {
		global $wpdb;
		$table = TableNameResolver::getOnboardingRegistrationsTable();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE attempt_id = %s LIMIT 1", $attempt_id ) );
	}
}

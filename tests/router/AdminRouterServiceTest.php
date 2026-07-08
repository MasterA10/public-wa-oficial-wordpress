<?php

use WAS\Core\TableNameResolver;
use WAS\Router\AdminRouterService;
use WAS\Router\RawRequestDispatcher;
use WAS\Router\TemplateRouterService;

class AdminRouterServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 7, 'tenant_id' => 1, 'meta_app_id' => 1, 'waba_id' => 'meta-waba-1', 'name' => 'WABA', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [ 'id' => 10, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'phone_number_id' => 'meta-phone-1', 'display_phone_number' => '+55 11 99999-9999', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [ 'id' => 20, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'access_token_encrypted' => \WAS\Meta\TokenVault::encrypt( 'waba-token' ), 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'id' => 'meta-template-1', 'status' => 'PENDING' ],
		];
	}

	public function test_template_crud_and_phone_number_template_menu_match_router_contract() {
		$submit = ( new TemplateRouterService() )->submit_template(
			[
				'waba_id'         => 7,
				'phone_number_id' => 10,
				'name'            => 'boas_vindas',
				'language'        => 'pt_BR',
				'category'        => 'MARKETING',
				'components'      => [ [ 'type' => 'BODY', 'text' => 'Bem vindo' ] ],
			]
		);

		$this->assert_false( is_wp_error( $submit ) );
		$template_id = $submit['template_id'];
		$service = new AdminRouterService();

		$updated = $service->update_template(
			$template_id,
			[
				'category'   => 'UTILITY',
				'components' => [ [ 'type' => 'BODY', 'text' => 'Bem vindo atualizado' ] ],
			]
		);

		$this->assert_false( is_wp_error( $updated ) );
		$this->assert_same( 'UTILITY', $updated->category );
		$this->assert_same( 'Bem vindo atualizado', json_decode( $updated->components_json, true )[0]['text'] );

		$list = $service->list_phone_number_templates( 10 );
		$this->assert_false( is_wp_error( $list ) );
		$this->assert_count( 1, $list );
		$this->assert_same( $template_id, $list[0]['id'] );

		$get = $service->get_template( $template_id );
		$this->assert_false( is_wp_error( $get ) );
		$this->assert_same( 'boas_vindas', $get['name'] );
		$this->assert_same( 'UTILITY', $get['category'] );

		$deleted = $service->delete_template( $template_id );
		$this->assert_false( is_wp_error( $deleted ) );
		$this->assert_same( 'DELETED', $deleted['status'] );
		$this->assert_count( 0, $service->list_phone_number_templates( 10 ) );
	}

	public function test_admin_template_sync_shortcuts_import_from_meta_without_phone_binding() {
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'data' => [
						[
							'id'         => 'meta-button-template',
							'name'       => 'botao_sync',
							'language'   => 'pt_BR',
							'category'   => 'UTILITY',
							'status'     => 'APPROVED',
							'components' => [ [ 'type' => 'BODY', 'text' => 'Botao' ] ],
						],
					],
				],
			],
			[
				'code' => 200,
				'body' => [
					'data' => [
						[
							'id'         => 'meta-global-template',
							'name'       => 'global_sync',
							'language'   => 'pt_BR',
							'category'   => 'MARKETING',
							'status'     => 'PENDING',
							'components' => [ [ 'type' => 'BODY', 'text' => 'Global' ] ],
						],
					],
				],
			],
		];

		$service = new AdminRouterService();
		$instance = $service->sync_phone_number_templates( 10 );
		$templates = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ];
		$by_name = [];
		foreach ( $templates as $template ) {
			$by_name[ $template['name'] ] = $template;
		}

		$this->assert_false( is_wp_error( $instance ) );
		$this->assert_same( 1, $instance['imported_count'] );
		$this->assert_same( 7, $instance['waba_id'] );
		$this->assert_same( 10, $instance['phone_number_id'] );
		$this->assert_true( str_contains( $instance['message'], 'Numero #10' ) );
		$this->assert_array_has_key( 'botao_sync', $by_name );
		$this->assert_null( $by_name['botao_sync']['router_phone_number_id'] );

		$global = $service->sync_all_templates();

		$this->assert_false( is_wp_error( $global ) );
		$this->assert_same( 1, $global['wabas_checked'] );
		$this->assert_same( 1, $global['imported_count'] );
		$this->assert_count( 0, $global['errors'] );
		$this->assert_true( str_contains( $global['message'], 'Sincronizacao global de templates concluida' ) );
		$this->assert_count( 2, $GLOBALS['was_test_http_gets'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][0]['url'], '/v25.0/meta-waba-1/message_templates' ) );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][1]['url'], '/v25.0/meta-waba-1/message_templates' ) );
	}

	public function test_raw_dispatcher_exposes_admin_template_sync_shortcuts() {
		$GLOBALS['was_test_options']['was_router_service_secret'] = 'service-secret';
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'data' => [
						[
							'id'         => 'meta-raw-phone-template',
							'name'       => 'raw_phone_sync',
							'language'   => 'pt_BR',
							'category'   => 'UTILITY',
							'status'     => 'APPROVED',
							'components' => [ [ 'type' => 'BODY', 'text' => 'Raw phone' ] ],
						],
					],
				],
			],
			[
				'code' => 200,
				'body' => [
					'data' => [
						[
							'id'         => 'meta-raw-global-template',
							'name'       => 'raw_global_sync',
							'language'   => 'pt_BR',
							'category'   => 'MARKETING',
							'status'     => 'PENDING',
							'components' => [ [ 'type' => 'BODY', 'text' => 'Raw global' ] ],
						],
					],
				],
			],
		];
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$dispatcher = new RawRequestDispatcher();
		$method = ( new ReflectionClass( $dispatcher ) )->getMethod( 'dispatch' );
		$method->setAccessible( true );

		$request = new WP_REST_Request( 'POST', '/admin-api/phone-numbers/10/templates/sync' );
		$request->set_header( 'Authorization', 'Bearer service-secret' );
		$phone_response = $method->invoke( $dispatcher, 'admin-api/phone-numbers/10/templates/sync', $request );
		$phone_data = $phone_response->get_data();

		$request = new WP_REST_Request( 'POST', '/admin-api/templates/sync-all' );
		$request->set_header( 'Authorization', 'Bearer service-secret' );
		$global_response = $method->invoke( $dispatcher, 'admin-api/templates/sync-all', $request );
		$global_data = $global_response->get_data();

		$this->assert_same( 200, $phone_response->get_status() );
		$this->assert_same( 1, $phone_data['imported_count'] );
		$this->assert_same( 10, $phone_data['phone_number_id'] );
		$this->assert_same( 200, $global_response->get_status() );
		$this->assert_same( 1, $global_data['wabas_checked'] );
		$this->assert_same( 1, $global_data['imported_count'] );
	}

	public function test_admin_can_delete_template_from_meta_with_name_and_hsm_id() {
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::getTemplatesTable(),
			[
				'id'                    => 90,
				'tenant_id'             => 1,
				'whatsapp_account_id'   => 7,
				'router_phone_number_id'=> 10,
				'meta_template_id'      => 'meta-delete-1',
				'waba_id'               => 'meta-waba-1',
				'name'                  => 'apagar_modelo',
				'language'              => 'pt_BR',
				'category'              => 'UTILITY',
				'status'                => 'APPROVED',
				'components_json'       => wp_json_encode( [ [ 'type' => 'BODY', 'text' => 'Apagar' ] ] ),
				'body_text'             => 'Apagar',
				'created_at'            => current_time( 'mysql', true ),
				'updated_at'            => current_time( 'mysql', true ),
			]
		);
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'success' => true ],
		];

		$result = ( new AdminRouterService() )->delete_template( 90, [ 'delete_from_meta' => true ] );
		$call = $GLOBALS['was_test_http_posts'][0];

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 'DELETED', $result['status'] );
		$this->assert_true( $result['meta_deleted'] );
		$this->assert_same( 'DELETE', $call['args']['method'] );
		$this->assert_true( str_contains( $call['url'], '/v25.0/meta-waba-1/message_templates' ) );
		$this->assert_true( str_contains( $call['url'], 'name=apagar_modelo' ) );
		$this->assert_true( str_contains( $call['url'], 'hsm_id=meta-delete-1' ) );
		$this->assert_same( 'Bearer waba-token', $call['args']['headers']['Authorization'] );
	}

	public function test_rotates_waba_token_and_disables_phone_routes_on_soft_delete() {
		$service = new AdminRouterService();
		$route = $service->create_route(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'name'            => 'Agenda',
				'target_url'      => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
				'secret'          => 'old-secret',
			]
		);
		$rotation = $service->rotate_waba_token( 7, [ 'access_token' => 'fresh-token' ] );
		$deleted_phone = $service->delete_resource( 'phone-numbers', 10 );

		$this->assert_false( is_wp_error( $route ) );
		$this->assert_false( is_wp_error( $rotation ) );
		$this->assert_true( $rotation['token_rotated'] );
		$this->assert_same( 'disabled', $deleted_phone->status );
		$this->assert_same( 'disabled', ( new \WAS\Router\RouteRepository() )->find( $route->id )->status );
	}
}

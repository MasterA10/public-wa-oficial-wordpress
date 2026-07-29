<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\REST\TemplateApiController;

class TemplateApiControllerTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_tenant_id( 1 );
		$now = current_time( 'mysql', true );
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => $now ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 7, 'tenant_id' => 1, 'waba_id' => 'waba-template-api', 'status' => 'active', 'created_at' => $now ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [
			'id' => 20, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'access_token_encrypted' => TokenVault::encrypt( 'template-token' ), 'status' => 'active', 'created_at' => $now,
		] );
	}

	public function test_create_item_builds_meta_payload_persists_local_template_and_returns_meta_id() {
		$GLOBALS['was_test_http_response'] = [ 'code' => 200, 'body' => [ 'id' => 'meta-template-1', 'status' => 'PENDING' ] ];
		$request = new WP_REST_Request( 'POST', '/templates' );
		$request->set_body( wp_json_encode( $this->template_payload() ) );

		$response = ( new TemplateApiController() )->create_item( $request );
		$data = $response->get_data();
		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$local = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ][0];

		$this->assert_same( 201, $response->get_status() );
		$this->assert_true( $data['success'] );
		$this->assert_same( 'meta-template-1', $data['meta_id'] );
		$this->assert_same( 'welcome_user', $body['name'] );
		$this->assert_same( 'UTILITY', $body['category'] );
		$this->assert_same( 'PENDING', $local['status'] );
		$this->assert_same( 'meta-template-1', $local['meta_template_id'] );
		$this->assert_same( 'Bearer template-token', $GLOBALS['was_test_http_posts'][0]['args']['headers']['Authorization'] );
	}

	public function test_create_item_marks_meta_rejection_as_failed_and_returns_interpretable_error() {
		$GLOBALS['was_test_http_response'] = [ 'code' => 400, 'body' => [ 'error' => [ 'message' => 'Template policy rejected', 'code' => 132000 ] ] ];
		$request = new WP_REST_Request( 'POST', '/templates' );
		$request->set_body( wp_json_encode( $this->template_payload() ) );

		$response = ( new TemplateApiController() )->create_item( $request );
		$local = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ][0];

		$this->assert_same( 400, $response->get_status() );
		$this->assert_same( 'Erro na Meta: Template policy rejected', $response->get_data()['message'] );
		$this->assert_same( 'FAILED', $local['status'] );
		$this->assert_same( 'Template policy rejected', $local['last_meta_error'] );
	}

	public function test_controller_lists_and_gets_templates_and_returns_not_found_error() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::getTemplatesTable(), array_merge( $this->template_payload(), [
			'id'                  => 40,
			'tenant_id'           => 1,
			'whatsapp_account_id' => 7,
			'body_text'           => 'Olá!',
			'status'              => 'APPROVED',
			'deleted_at'          => null,
		] ) );

		$controller = new TemplateApiController();
		$list = $controller->get_items( new WP_REST_Request( 'GET', '/templates' ) );
		$get_request = new WP_REST_Request( 'GET', '/templates/40' );
		$get_request->set_param( 'id', 40 );
		$get = $controller->get_item( $get_request );
		$missing_request = new WP_REST_Request( 'GET', '/templates/999' );
		$missing_request->set_param( 'id', 999 );
		$missing = $controller->get_item( $missing_request );

		$this->assert_same( 200, $list->get_status() );
		$this->assert_count( 1, $list->get_data() );
		$this->assert_same( 200, $get->get_status() );
		$this->assert_same( 40, (int) $get->get_data()->id );
		$this->assert_true( is_wp_error( $missing ) );
		$this->assert_same( 'not_found', $missing->get_error_code() );
		$this->assert_same( 404, $missing->get_error_data()['status'] );
	}

	public function test_scoped_list_returns_templates_for_the_selected_phone_waba_only() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [
			'id' => 10,
			'tenant_id' => 1,
			'whatsapp_account_id' => 7,
			'phone_number_id' => 'meta-phone-10',
			'status' => 'active',
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [
			'id' => 8,
			'tenant_id' => 1,
			'waba_id' => 'waba-other',
			'status' => 'active',
		] );
		$payload = array_merge( $this->template_payload(), [
			'body_text' => 'Olá!',
			'deleted_at' => null,
		] );
		$wpdb->insert( TableNameResolver::getTemplatesTable(), array_merge( $payload, [
			'id' => 41,
			'tenant_id' => 1,
			'whatsapp_account_id' => 7,
			'status' => 'APPROVED',
		] ) );
		$wpdb->insert( TableNameResolver::getTemplatesTable(), array_merge( $payload, [
			'id' => 42,
			'tenant_id' => 1,
			'whatsapp_account_id' => 8,
			'name' => 'other_waba_template',
			'status' => 'APPROVED',
		] ) );

		$request = new WP_REST_Request( 'GET', '/templates?phone_number_id=10' );
		$request->set_query_params( [ 'phone_number_id' => 10 ] );
		$response = ( new TemplateApiController() )->get_items( $request );

		$this->assert_same( 200, $response->get_status() );
		$this->assert_count( 1, $response->get_data() );
		$this->assert_same( 41, (int) $response->get_data()[0]->id );
	}

	public function test_create_item_uses_the_selected_phone_account_and_keeps_phone_scope() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [
			'id' => 10,
			'tenant_id' => 1,
			'whatsapp_account_id' => 7,
			'phone_number_id' => 'meta-phone-10',
			'status' => 'active',
		] );
		$GLOBALS['was_test_http_response'] = [ 'code' => 200, 'body' => [ 'id' => 'meta-phone-template', 'status' => 'PENDING' ] ];
		$request = new WP_REST_Request( 'POST', '/templates' );
		$request->set_body( wp_json_encode( array_merge( $this->template_payload(), [ 'phone_number_id' => 10 ] ) ) );

		$response = ( new TemplateApiController() )->create_item( $request );
		$local = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ][0];

		$this->assert_same( 201, $response->get_status() );
		$this->assert_same( 7, (int) $local['whatsapp_account_id'] );
		$this->assert_same( 10, (int) $local['router_phone_number_id'] );
	}

	public function test_permissions_check_requires_manage_options() {
		$GLOBALS['was_test_capabilities'][1]['manage_options'] = false;
		$this->assert_false( ( new TemplateApiController() )->permissions_check() );
		$GLOBALS['was_test_capabilities'][1]['manage_options'] = true;
		$this->assert_true( ( new TemplateApiController() )->permissions_check() );
	}

	private function template_payload() {
		return [
			'name'     => 'welcome_user',
			'category' => 'UTILITY',
			'language' => 'pt_BR',
			'body'     => [ 'text' => 'Olá, seja bem-vindo!' ],
		];
	}
}

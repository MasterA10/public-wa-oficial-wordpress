<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\REST\ComplianceApiController;
use WAS\REST\DashboardApiController;
use WAS\REST\MetaCallbackController;
use WAS\REST\MetaApiController;
use WAS\REST\Routes;
use WAS\REST\WhatsAppApiController;

class RestControllerContractsTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_tenant_id( 1 );
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 7, 'tenant_id' => 1, 'waba_id' => 'waba-1', 'name' => 'Agenda WABA', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [ 'id' => 10, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'phone_number_id' => 'phone-1', 'status' => 'active', 'is_default' => 1, 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'contacts' ), [ 'id' => 30, 'tenant_id' => 1, 'wa_id' => '5531999000001', 'phone' => '5531999000001', 'profile_name' => 'Ana', 'opt_in_status' => 'unknown' ] );
	}

	public function test_whatsapp_controller_lists_accounts_with_primary_phone_and_phone_endpoint_contract() {
		$controller = new WhatsAppApiController();
		$accounts_response = $controller->get_accounts( new WP_REST_Request( 'GET', '/whatsapp/accounts' ) );
		$phones_response = $controller->get_phone_numbers( new WP_REST_Request( 'GET', '/whatsapp/phone-numbers' ) );
		$accounts = $accounts_response->get_data();

		$this->assert_same( 200, $accounts_response->get_status() );
		$this->assert_count( 1, $accounts );
		$this->assert_same( 'phone-1', $accounts[0]->phone_number_id );
		$this->assert_same( 200, $phones_response->get_status() );
		$this->assert_same( [], $phones_response->get_data() );
	}

	public function test_dashboard_summary_counts_tenant_resources() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'messages' ), [ 'id' => 50, 'tenant_id' => 1, 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'conversations' ), [ 'id' => 60, 'tenant_id' => 1, 'status' => 'open' ] );
		$wpdb->insert( TableNameResolver::getTemplatesTable(), [ 'id' => 70, 'tenant_id' => 1, 'deleted_at' => null ] );

		$summary = ( new DashboardApiController() )->get_summary( new WP_REST_Request( 'GET', '/dashboard' ) )->get_data();

		$this->assert_same( 1, $summary['whatsapp_accounts'] );
		$this->assert_same( 1, $summary['active_numbers'] );
		$this->assert_same( 1, $summary['messages_today'] );
		$this->assert_same( 1, $summary['open_conversations'] );
		$this->assert_same( 1, $summary['templates'] );
	}

	public function test_compliance_controller_exports_deletes_and_lists_audit_meta_and_webhook_records() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::getAuditLogsTable(), [ 'id' => 1, 'tenant_id' => 1, 'action' => 'test', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::getMetaApiLogsTable(), [ 'id' => 2, 'tenant_id' => 1, 'operation' => 'messages.send', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::getWebhookEventsTable(), [ 'id' => 3, 'tenant_id' => 1, 'received_at' => current_time( 'mysql', true ) ] );
		$controller = new ComplianceApiController();

		$export_request = new WP_REST_Request( 'POST', '/contacts/30/export' );
		$export_request->set_param( 'id', 30 );
		$export = $controller->export_contact( $export_request );
		$delete_request = new WP_REST_Request( 'POST', '/contacts/30/delete' );
		$delete_request->set_param( 'id', 30 );
		$deleted = $controller->delete_contact( $delete_request );

		$this->assert_same( 200, $export->get_status() );
		$this->assert_same( 30, (int) $export->get_data()['contact']->id );
		$this->assert_same( 200, $deleted->get_status() );
		$this->assert_count( 2, $controller->get_audit_logs( new WP_REST_Request() )->get_data() );
		$this->assert_count( 1, $controller->get_meta_api_logs( new WP_REST_Request() )->get_data() );
		$this->assert_count( 1, $controller->get_webhook_events( new WP_REST_Request() )->get_data() );
	}

	public function test_meta_callbacks_log_deauthorization_and_create_data_deletion_request() {
		$controller = new MetaCallbackController();
		$deauthorize = new WP_REST_Request( 'POST', '/meta/deauthorize' );
		$deauthorize->set_body_params( [ 'signed_request' => 'signed-value' ] );
		$deauthorize_response = $controller->deauthorize_callback( $deauthorize );
		$deletion = new WP_REST_Request( 'POST', '/meta/data-deletion' );
		$deletion->set_body_params( [ 'signed_request' => 'signed-value' ] );
		$deletion_response = $controller->data_deletion_callback( $deletion );
		$deletion_data = $deletion_response->get_data();

		$this->assert_same( 200, $deauthorize_response->get_status() );
		$this->assert_true( $deauthorize_response->get_data()['success'] );
		$this->assert_same( 200, $deletion_response->get_status() );
		$this->assert_true( str_contains( $deletion_data['url'], '/data-deletion-status?request=' ) );
		$this->assert_true( str_starts_with( $deletion_data['confirmation_code'], 'DEL-' ) );
		$this->assert_count( 1, $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'meta_deauthorize_logs' ) ] ?? [] );
		$this->assert_count( 1, $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'data_deletion_requests' ) ] ?? [] );
	}

	public function test_routes_authentication_rejects_logged_out_user_and_accepts_tenant_member() {
		TenantContext::set_runtime_tenant_id( null );
		$GLOBALS['was_test_logged_in'] = false;
		$logged_out = Routes::check_auth();
		$this->assert_true( is_wp_error( $logged_out ) );
		$this->assert_same( 'rest_unauthorized', $logged_out->get_error_code() );

		$GLOBALS['was_test_logged_in'] = true;
		$GLOBALS['was_test_capabilities'][1]['platform_owner'] = false;
		$GLOBALS['wpdb']->insert( TableNameResolver::get_table_name( 'tenant_users' ), [ 'tenant_id' => 1, 'user_id' => 1, 'status' => 'active' ] );
		$allowed = Routes::check_auth();
		$this->assert_same( true, $allowed );
	}

	public function test_meta_configuration_controller_masks_secrets_normalizes_signup_and_persists_token_and_phone() {
		$controller = new MetaApiController();
		$save_request = new WP_REST_Request( 'POST', '/meta/config' );
		$save_request->set_body( wp_json_encode( [
			'app_id'                 => 'app-1',
			'app_secret'             => 'app-secret',
			'verify_token'           => 'verify-1',
			'waba_id'                => 'waba-1',
			'meta_access_token'      => 'access-token-1234567890',
			'primary_phone_number_id'=> 'phone-1',
			'embedded_signup_url'    => 'https://business.facebook.com/signup?config_id=config-1',
		] ) );
		$saved = $controller->save_config( $save_request );
		$config = $controller->get_config( new WP_REST_Request( 'GET', '/meta/config' ) )->get_data();
		$reveal_request = new WP_REST_Request( 'POST', '/meta/config/reveal' );
		$reveal_request->set_body( wp_json_encode( [ 'password' => 'secret' ] ) );
		$revealed = $controller->reveal_config( $reveal_request )->get_data();

		$this->assert_same( 200, $saved->get_status() );
		$this->assert_same( '********', $config['app_secret'] );
		$this->assert_same( '********', $config['verify_token'] );
		$this->assert_same( '********', $config['meta_access_token'] );
		$this->assert_same( 'waba-1', $config['waba_id'] );
		$this->assert_true( str_contains( $config['embedded_signup_url'], 'redirect_uri=' ) );
		$this->assert_same( 'app-...cret', $revealed['app_secret'] );
		$this->assert_same( TokenVault::mask( 'access-token-1234567890', 8 ), $revealed['meta_access_token'] );
		$this->assert_same( 'verify-1', $revealed['verify_token'] );
	}
}

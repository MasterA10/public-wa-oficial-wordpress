<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\WhatsApp\IntegrationConnectionCheckService;

class IntegrationConnectionCheckServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;

		TenantContext::set_tenant_id( 1 );
		$now = current_time( 'mysql', true );
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => $now ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_apps' ), [
			'id'            => 1,
			'app_id'        => 'app-connection-check',
			'app_secret'    => TokenVault::encrypt( 'app-secret' ),
			'graph_version' => 'v25.0',
			'status'        => 'active',
			'is_default'    => 1,
			'created_at'    => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [
			'id' => 7, 'tenant_id' => 1, 'waba_id' => 'waba-check', 'status' => 'active', 'created_at' => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [
			'id' => 10, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'phone_number_id' => 'phone-check', 'created_at' => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [
			'id' => 20, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'access_token_encrypted' => TokenVault::encrypt( 'check-token' ), 'status' => 'active', 'created_at' => $now,
		] );
	}

	public function test_connection_check_reports_token_waba_phone_webhook_and_templates() {
		$GLOBALS['was_test_http_response_queue'] = [
			[ 'code' => 200, 'body' => [ 'data' => [ 'is_valid' => true, 'app_id' => 'app-connection-check' ] ] ],
			[ 'code' => 200, 'body' => [ 'id' => 'waba-check', 'name' => 'Agenda WABA' ] ],
			[ 'code' => 200, 'body' => [ 'data' => [ [ 'id' => 'phone-check', 'display_phone_number' => '+55 31 97118-3457', 'verified_name' => 'Agenda', 'quality_rating' => 'GREEN' ] ] ] ],
			[ 'code' => 200, 'body' => [ 'id' => 'phone-check', 'status' => 'CONNECTED', 'name_status' => 'APPROVED', 'quality_rating' => 'GREEN', 'health_status' => 'GREEN' ] ],
			[ 'code' => 200, 'body' => [ 'data' => [ 'about' => 'Agenda oficial' ] ] ],
			[ 'code' => 200, 'body' => [ 'data' => [ [ 'id' => 'app-connection-check' ] ] ] ],
			[ 'code' => 200, 'body' => [ 'data' => [ [ 'name' => 'hello_world' ] ] ] ],
		];

		$result = ( new IntegrationConnectionCheckService() )->checkConnection( 1 );

		$this->assert_same( 'success', $result['token']['status'] );
		$this->assert_same( 'success', $result['waba']['status'] );
		$this->assert_same( 'success', $result['phone_numbers']['status'] );
		$this->assert_same( 'CONNECTED', $result['phone_numbers']['numbers'][0]['status'] );
		$this->assert_same( 'success', $result['webhook']['status'] );
		$this->assert_same( 'success', $result['templates']['status'] );
		$this->assert_false( array_key_exists( 'business_profile', $result ) );
		$this->assert_count( 7, $GLOBALS['was_test_http_gets'] );
	}

	public function test_connection_check_stops_with_interpretable_token_error() {
		global $wpdb;

		$wpdb->tables[ TableNameResolver::get_table_name( 'meta_tokens' ) ] = [];
		$result = ( new IntegrationConnectionCheckService() )->checkConnection( 1 );

		$this->assert_same( 'error', $result['token']['status'] );
		$this->assert_same( 'Token não configurado.', $result['token']['details'] );
		$this->assert_count( 0, $GLOBALS['was_test_http_gets'] );
	}
}

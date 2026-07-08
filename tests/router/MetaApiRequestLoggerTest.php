<?php

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaApiRequestLogger;

class MetaApiRequestLoggerTest extends WAS_Router_TestCase {

	public function test_meta_token_exchange_logs_mask_access_token_values() {
		\WAS\Auth\TenantContext::set_current_tenant_id( 42 );

		MetaApiRequestLogger::log(
			'meta_token_exchange_response',
			'GET',
			'/v25.0/oauth/access_token?client_id=app&access_token=super-secret-meta-token&debug=1',
			200,
			true,
			[
				'access_token' => 'super-secret-meta-token',
				'nested'       => [
					'access_token' => 'nested-secret-token',
					'media_url'    => 'https://lookaside.fbsbx.com/media?access_token=media-secret-token&expires=1',
				],
			],
			123,
			[],
			[
				'verify_token' => 'verify-secret-token',
				'links'        => [
					'https://graph.facebook.com/debug?access_token=request-secret-token',
				],
			]
		);

		$rows = $GLOBALS['wpdb']->tables[ TableNameResolver::getMetaApiLogsTable() ] ?? [];
		$this->assert_count( 1, $rows );
		$row = $rows[0];
		$encoded = wp_json_encode( $row );

		$this->assert_same( 42, (int) $row['tenant_id'] );
		$this->assert_same( 'meta_token_exchange_response', $row['operation'] );
		$this->assert_true( str_contains( $row['path'], 'access_token=TOKEN_MASKED' ) );
		$this->assert_true( str_contains( $row['request_payload'], 'TOKEN_MASKED' ) );
		$this->assert_true( str_contains( $row['response_body'], 'TOKEN_MASKED' ) );
		$this->assert_false( str_contains( $encoded, 'super-secret-meta-token' ) );
		$this->assert_false( str_contains( $encoded, 'nested-secret-token' ) );
		$this->assert_false( str_contains( $encoded, 'media-secret-token' ) );
		$this->assert_false( str_contains( $encoded, 'request-secret-token' ) );
		$this->assert_false( str_contains( $encoded, 'verify-secret-token' ) );
	}

	public function test_meta_api_logs_table_is_part_of_installation_manifest() {
		$this->assert_true( in_array( 'meta_api_logs', TableNameResolver::get_all_tables(), true ) );
	}
}

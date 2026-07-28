<?php

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaAppRepository;
use WAS\Meta\TokenService;
use WAS\Meta\TokenVault;

class MetaConfigurationContractsTest extends WAS_Router_TestCase {

	public function test_meta_app_repository_saves_encrypts_updates_and_resolves_verify_token() {
		$repository = new MetaAppRepository();
		$id = $repository->save_app(
			[
				'app_id'       => 'app-1',
				'app_secret'   => 'app-secret',
				'config_id'    => 'config-1',
				'graph_version' => 'v25.0',
				'verify_token' => 'verify-1',
			]
		);

		$active = $repository->get_active_app( true );
		$verified = $repository->get_active_app_for_verify_token( 'verify-1', true );
		$updated_id = $repository->save_app(
			[
				'app_id'       => 'app-1-updated',
				'app_secret'   => 'new-secret',
				'graph_version' => 'v26.0',
				'verify_token' => 'verify-2',
			]
		);
		$after_update = $repository->get_active_app( true );

		$this->assert_true( $id > 0 );
		$this->assert_same( $id, $updated_id );
		$this->assert_same( 'app-secret', $active->app_secret );
		$this->assert_same( 'app-secret', $verified->app_secret );
		$this->assert_same( 'app-1-updated', $after_update->app_id );
		$this->assert_same( 'new-secret', $after_update->app_secret );
		$this->assert_same( 'v26.0', $after_update->graph_version );
		$this->assert_null( $repository->get_active_app_for_verify_token( 'wrong-token' ) );
	}

	public function test_token_service_rotates_account_token_and_never_persists_plaintext() {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'meta_tokens' );
		$wpdb->insert(
			$table,
			[
				'id'                    => 1,
				'tenant_id'             => 1,
				'whatsapp_account_id'   => 7,
				'access_token_encrypted' => TokenVault::encrypt( 'old-token' ),
				'status'                => 'active',
			]
		);

		$id = ( new TokenService() )->store_encrypted_token( 1, 7, 'new-token' );
		$rows = $wpdb->tables[ $table ];
		$active = ( new TokenService() )->get_active_token( 1, 7 );

		$this->assert_true( $id > 1 );
		$this->assert_same( 'inactive', $rows[0]['status'] );
		$this->assert_same( 'new-token', $active );
		$this->assert_false( in_array( 'new-token', $rows[1], true ) );
		$this->assert_same( 'new-token', TokenVault::decrypt( $rows[1]['access_token_encrypted'] ) );
	}
}

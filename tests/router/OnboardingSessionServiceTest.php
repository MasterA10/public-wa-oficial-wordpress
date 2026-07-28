<?php

use WAS\Auth\OnboardingSessionService;
use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;

class OnboardingSessionServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;

		TenantContext::set_tenant_id( 1 );
		$now = current_time( 'mysql', true );
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => $now ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_apps' ), [
			'id'            => 1,
			'app_id'        => 'app-onboarding',
			'app_secret'    => TokenVault::encrypt( 'app-secret-onboarding' ),
			'graph_version' => 'v25.0',
			'status'        => 'active',
			'is_default'    => 1,
			'created_at'    => $now,
		] );
	}

	public function test_start_and_complete_exchange_code_save_credentials_and_subscribe_waba() {
		$service = new OnboardingSessionService();
		$session_uuid = $service->start( 1, 1 );

		$GLOBALS['was_test_http_response_queue'] = [
			[ 'code' => 200, 'body' => [ 'access_token' => 'onboarding-access-token' ] ],
			[ 'code' => 200, 'body' => [ 'success' => true ] ],
		];
		$result = $service->complete( $session_uuid, 'oauth-code-1', 'waba-onboarding', 'phone-onboarding', 'business-1' );

		$this->assert_true( str_starts_with( $session_uuid, 'ob_' ) );
		$this->assert_true( $result['success'] );
		$this->assert_same( 'waba-onboarding', $result['waba_id'] );
		$this->assert_same( 'phone-onboarding', $result['phone_number_id'] );
		$this->assert_same( 'app-secret-onboarding', $this->query_arg( $GLOBALS['was_test_http_gets'][0]['url'], 'client_secret' ) );
		$this->assert_same( 'oauth-code-1', $this->query_arg( $GLOBALS['was_test_http_gets'][0]['url'], 'code' ) );
		$subscribe = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_same( 'messages', $subscribe['subscribed_fields'][0] );
		$this->assert_same( 'smb_message_echoes', $subscribe['subscribed_fields'][1] );
		$this->assert_same( 'Bearer onboarding-access-token', $GLOBALS['was_test_http_posts'][0]['args']['headers']['Authorization'] );

		$session = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM ' . TableNameResolver::getOnboardingSessionsTable() . ' WHERE session_uuid = %s', $session_uuid ) );
		$tokens = $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'meta_tokens' ) ];
		$this->assert_same( 'connected', $session->status );
		$this->assert_same( 'waba-onboarding', $session->waba_id );
		$this->assert_same( 'onboarding-access-token', TokenVault::decrypt( $tokens[0]['access_token_encrypted'] ) );
	}

	public function test_complete_rejects_unknown_session_and_cancel_marks_existing_session() {
		$service = new OnboardingSessionService();
		$this->assert_throws( fn() => $service->complete( 'missing-session', 'code', 'waba', 'phone' ) );

		$session_uuid = $service->start( 1, 1 );
		$service->cancel( $session_uuid, 'user_cancelled' );
		$session = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM ' . TableNameResolver::getOnboardingSessionsTable() . ' WHERE session_uuid = %s', $session_uuid ) );

		$this->assert_same( 'cancelled', $session->status );
		$this->assert_same( 'user_cancelled', $session->error_message );
	}

	private function query_arg( $url, $key ) {
		$parts = parse_url( $url );
		parse_str( $parts['query'] ?? '', $query );
		return $query[ $key ] ?? null;
	}
}

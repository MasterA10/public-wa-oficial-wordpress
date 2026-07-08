<?php

use WAS\Core\TableNameResolver;
use WAS\Router\OnboardingService;

class OnboardingRegistrationTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		$GLOBALS['was_test_options']['was_router_auto_subscribe_webhooks'] = false;
		$GLOBALS['was_test_options']['was_router_route_secret'] = 'route-secret';
		$wpdb->insert(
			TableNameResolver::get_table_name( 'tenants' ),
			[
				'id'         => 1,
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

	public function test_registration_expands_brazil_phone_variants() {
		$result = ( new OnboardingService() )->create_registration(
			[
				'tenant_id'             => 1,
				'meta_app_id'           => 2,
				'company_id'            => 'empresa-1',
				'attempt_id'            => 'attempt-1',
				'phone_number'          => '55 (31) 99712-1855',
				'phone_number_variants' => [ '553197121855' ],
				'callback_url'          => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
				'authorization_url'     => 'https://facebook.com/dialog?state=state-123',
			]
		);

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( [ '553197121855', '5531997121855' ], $result['phone_number_variants'] );
		$this->assert_same( '5531997121855', $result['phone_number'] );
	}

	public function test_registration_reuses_attempt_id_and_resets_callback_state() {
		$service = new OnboardingService();
		$first = $service->create_registration(
			[
				'tenant_id'     => 1,
				'meta_app_id'   => 2,
				'attempt_id'    => 'attempt-repeat',
				'phone_number'  => '5531997121855',
				'callback_url'  => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);

		global $wpdb;
		$table = TableNameResolver::getOnboardingRegistrationsTable();
		$wpdb->update(
			$table,
			[
				'notified_at'              => current_time( 'mysql', true ),
				'last_notification_status' => 500,
				'last_notification_error'  => 'failed',
			],
			[ 'id' => $first['id'] ]
		);

		$second = $service->create_registration(
			[
				'tenant_id'     => 1,
				'meta_app_id'   => 2,
				'attempt_id'    => 'attempt-repeat',
				'phone_number'  => '553197121855',
				'callback_url'  => 'https://agenda.dominai.cloud/whatsapp/webhooks/new-waba-router/',
			]
		);
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d LIMIT 1', $first['id'] ) );

		$this->assert_same( $first['id'], $second['id'] );
		$this->assert_null( $row->notified_at );
		$this->assert_null( $row->last_notification_status );
		$this->assert_null( $row->last_notification_error );
		$this->assert_same( 'https://agenda.dominai.cloud/whatsapp/webhooks/new-waba-router/', $row->callback_url );
	}

	public function test_authorization_code_exchange_without_waba_is_queued_and_saved_for_retry() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'     => 1,
				'meta_app_id'   => 2,
				'attempt_id'    => 'attempt-code-only',
				'phone_number'  => '5531997121855',
				'callback_url'  => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
				'redirect_uri'  => 'https://agenda.dominai.cloud/api/integrations/callbacks/meta',
			]
		);
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'access_token' => 'meta-token-from-code' ],
		];

		$first = $service->complete_embedded_signup(
			[
				'tenant_id'           => 1,
				'meta_app_id'         => 2,
				'attempt_id'          => 'attempt-code-only',
				'authorization_code'  => 'code-1',
				'redirect_uri'        => 'https://agenda.dominai.cloud/api/integrations/callbacks/meta',
			]
		);
		$registration = $this->registration_by_attempt( 'attempt-code-only' );
		$jobs = $GLOBALS['wpdb']->tables[ TableNameResolver::getOnboardingReconciliationJobsTable() ];

		$this->assert_same( 'queued_for_retry', $first['status'] );
		$this->assert_same( 'waiting_meta_waba_id', $first['error'] );
		$this->assert_same( 'exchanged', $registration->token_exchange_status );
		$this->assert_not_null( $registration->access_token_encrypted );
		$this->assert_same( 'pending', $jobs[0]['status'] );
		$this->assert_same( 'waiting_meta_waba_id', $jobs[0]['last_error'] );
		$this->assert_count( 1, array_filter( $GLOBALS['was_test_http_gets'], fn( $call ) => str_contains( $call['url'], '/oauth/access_token' ) ) );

		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'data' => [
						[
							'id'                   => 'meta-phone-1',
							'display_phone_number' => '+55 31 99712-1855',
							'verified_name'        => 'Nome da empresa',
						],
					],
				],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$second = $service->complete_embedded_signup(
			[
				'tenant_id'   => 1,
				'meta_app_id' => 2,
				'attempt_id'  => 'attempt-code-only',
				'waba_id'     => 'meta-waba-1',
				'business_id' => 'business-1',
			]
		);

		$this->assert_same( 'completed', $second['status'] );
		$this->assert_same( 'delivered', $second['notification_status'] );
		$this->assert_count( 1, array_filter( $GLOBALS['was_test_http_gets'], fn( $call ) => str_contains( $call['url'], '/oauth/access_token' ) ) );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
	}

	public function test_embedded_signup_tokens_are_not_persisted_in_plaintext() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-secret-token',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-secret-token', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$result = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-secret-token',
				'access_token' => 'super-secret-meta-token',
				'waba_id'      => 'meta-waba-secret-token',
			]
		);
		$registration = $this->registration_by_attempt( 'attempt-secret-token' );
		$meta_tokens = $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'meta_tokens' ) ] ?? [];

		$this->assert_same( 'completed', $result['status'] );
		$this->assert_not_null( $registration->access_token_encrypted );
		$this->assert_true( 'super-secret-meta-token' !== $registration->access_token_encrypted );
		$this->assert_same( 'super-secret-meta-token', \WAS\Router\SecretVault::decrypt( $registration->access_token_encrypted ) );
		$this->assert_count( 1, $meta_tokens );
		$this->assert_true( 'super-secret-meta-token' !== $meta_tokens[0]['access_token_encrypted'] );
		$this->assert_same( 'super-secret-meta-token', \WAS\Meta\TokenVault::decrypt( $meta_tokens[0]['access_token_encrypted'] ) );
		$this->assert_false( str_contains( wp_json_encode( $GLOBALS['wpdb']->tables ), '"super-secret-meta-token"' ) );
	}

	public function test_code_already_used_without_saved_token_returns_controlled_status() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-used-code',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
				'redirect_uri' => 'https://agenda.dominai.cloud/api/integrations/callbacks/meta',
			]
		);
		$GLOBALS['was_test_http_response'] = [
			'code' => 400,
			'body' => [
				'error' => [
					'message'       => 'This authorization code has been used.',
					'code'          => 100,
					'error_subcode' => 36009,
				],
			],
		];

		$result = $service->complete_embedded_signup(
			[
				'tenant_id'          => 1,
				'meta_app_id'        => 2,
				'attempt_id'         => 'attempt-used-code',
				'authorization_code' => 'used-code',
				'redirect_uri'       => 'https://agenda.dominai.cloud/api/integrations/callbacks/meta',
			]
		);
		$registration = $this->registration_by_attempt( 'attempt-used-code' );
		$job = $this->latest_job();

		$this->assert_same( 'queued_for_retry', $result['status'] );
		$this->assert_same( 'code_already_used_without_saved_token', $result['error'] );
		$this->assert_same( 'code_already_used_without_saved_token', $registration->token_exchange_status );
		$this->assert_same( 'pending', $job['status'] );
		$this->assert_same( 'code_already_used_without_saved_token', $job['last_error'] );
		$this->assert_count( 1, array_filter( $GLOBALS['was_test_http_gets'], fn( $call ) => str_contains( $call['url'], '/oauth/access_token' ) ) );
		$this->assert_count( 0, $GLOBALS['was_test_http_posts'] );
	}

	public function test_code_already_used_reregistration_completes_with_existing_waba_token() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-existing-token-first',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-existing-token', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$first = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-existing-token-first',
				'access_token' => 'stored-waba-token',
				'waba_id'      => 'meta-waba-existing-token',
			]
		);

		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-existing-token-second',
				'phone_number' => '553197121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		global $wpdb;
		$wpdb->update(
			TableNameResolver::getOnboardingRegistrationsTable(),
			[
				'access_token_encrypted' => null,
				'token_exchange_status'  => null,
				'token_exchanged_at'     => null,
			],
			[ 'attempt_id' => 'attempt-existing-token-second' ]
		);
		$GLOBALS['was_test_http_gets'] = [];
		$GLOBALS['was_test_http_posts'] = [];
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-existing-token', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$second = $service->complete_embedded_signup(
			[
				'tenant_id'          => 1,
				'meta_app_id'        => 2,
				'attempt_id'         => 'attempt-existing-token-second',
				'authorization_code' => 'used-code',
				'waba_id'            => 'meta-waba-existing-token',
			]
		);
		$registration = $this->registration_by_attempt( 'attempt-existing-token-second' );

		$this->assert_same( 'completed', $first['status'] );
		$this->assert_same( 'completed', $second['status'] );
		$this->assert_same( $first['waba_id'], $second['waba_id'] );
		$this->assert_false( $second['token_exchanged'] );
		$this->assert_same( 'exchanged', $registration->token_exchange_status );
		$this->assert_count( 0, array_filter( $GLOBALS['was_test_http_gets'], fn( $call ) => str_contains( $call['url'], '/oauth/access_token' ) ) );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
	}

	public function test_callback_server_failure_keeps_job_pending_for_retry() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'             => 1,
				'meta_app_id'           => 2,
				'attempt_id'            => 'attempt-callback-500',
				'phone_number'          => '5531997121855',
				'phone_number_variants' => [ '553197121855' ],
				'callback_url'          => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'data' => [
						[
							'id'                   => 'meta-phone-500',
							'display_phone_number' => '+55 31 99712-1855',
							'verified_name'        => 'Nome da empresa',
						],
					],
				],
			],
			[
				'code' => 500,
				'body' => 'boom',
			],
		];

		$result = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-callback-500',
				'access_token' => 'direct-token',
				'waba_id'      => 'meta-waba-500',
				'business_id'  => 'business-500',
			]
		);
		$registration = $this->registration_by_attempt( 'attempt-callback-500' );
		$job = $this->latest_job();

		$this->assert_same( 'queued_for_retry', $result['status'] );
		$this->assert_same( 'failed', $result['notification_status'] );
		$this->assert_same( 500, $result['notification_status_code'] );
		$this->assert_same( 'completed', $registration->status );
		$this->assert_same( 500, (int) $registration->last_notification_status );
		$this->assert_same( 'boom', $registration->last_notification_error );
		$this->assert_null( $registration->notified_at );
		$this->assert_same( 'pending', $job['status'] );
		$this->assert_same( 'notification_failed_500', $job['last_error'] );
	}

	public function test_callback_conflict_marks_notification_failed() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'     => 1,
				'meta_app_id'   => 2,
				'attempt_id'    => 'attempt-callback-409',
				'phone_number'  => '5531997121855',
				'callback_url'  => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$conflict = '{"error":"external_account_conflict"}';
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-409', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 409,
				'body' => $conflict,
			],
		];

		$result = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-callback-409',
				'access_token' => 'direct-token',
				'waba_id'      => 'meta-waba-409',
			]
		);
		$registration = $this->registration_by_attempt( 'attempt-callback-409' );
		$job = $this->latest_job();

		$this->assert_same( 'notification_failed', $result['status'] );
		$this->assert_same( 'failed', $result['notification_status'] );
		$this->assert_same( 409, $result['notification_status_code'] );
		$this->assert_same( $conflict, $result['notification_error'] );
		$this->assert_same( 'completed', $registration->status );
		$this->assert_same( 409, (int) $registration->last_notification_status );
		$this->assert_same( $conflict, $registration->last_notification_error );
		$this->assert_null( $registration->notified_at );
		$this->assert_same( 'failed', $job['status'] );
		$this->assert_same( 'notification_failed_409', $job['last_error'] );
	}

	public function test_failed_onboarding_notification_is_retried_on_repeat_completion() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-retry-callback',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-retry', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 500,
				'body' => 'boom',
			],
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-retry', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$payload = [
			'tenant_id'    => 1,
			'meta_app_id'  => 2,
			'attempt_id'   => 'attempt-retry-callback',
			'access_token' => 'direct-token',
			'waba_id'      => 'meta-waba-retry',
		];

		$first = $service->complete_embedded_signup( $payload );
		$second = $service->complete_embedded_signup( $payload );
		$registration = $this->registration_by_attempt( 'attempt-retry-callback' );

		$this->assert_same( 'queued_for_retry', $first['status'] );
		$this->assert_same( 'completed', $second['status'] );
		$this->assert_same( 'delivered', $second['notification_status'] );
		$this->assert_same( 200, (int) $registration->last_notification_status );
		$this->assert_null( $registration->last_notification_error );
		$this->assert_not_null( $registration->notified_at );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
	}

	public function test_pending_reconciliation_job_retries_failed_onboarding_notification() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-job-retry',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-job-retry', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 500,
				'body' => 'boom',
			],
		];
		$first = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-job-retry',
				'access_token' => 'direct-token',
				'waba_id'      => 'meta-waba-job-retry',
			]
		);
		$pending_job = $this->latest_job();
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-job-retry', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$result = $service->process_pending_reconciliation_jobs( 10 );
		$registration = $this->registration_by_attempt( 'attempt-job-retry' );
		$job = $this->job_by_id( $pending_job['id'] );

		$this->assert_same( 'queued_for_retry', $first['status'] );
		$this->assert_same( 1, $result['processed'] );
		$this->assert_same( 'completed', $result['results'][0]['status'] );
		$this->assert_same( 'completed', $job['status'] );
		$this->assert_same( 2, (int) $job['attempts'] );
		$this->assert_same( 200, (int) $registration->last_notification_status );
		$this->assert_not_null( $registration->notified_at );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
	}

	public function test_processing_reconciliation_job_is_not_run_in_parallel() {
		$service = new OnboardingService();
		$registration = $service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-processing-job',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::getOnboardingReconciliationJobsTable(),
			[
				'registration_id' => (int) $registration['id'],
				'status'          => 'processing',
				'attempts'        => 4,
				'created_at'      => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			]
		);
		$job_id = (int) $wpdb->insert_id;

		$result = $service->process_reconciliation_job( $job_id );
		$job = $this->job_by_id( $job_id );

		$this->assert_same( 'already_processing', $result['status'] );
		$this->assert_same( 'processing', $job['status'] );
		$this->assert_same( 4, (int) $job['attempts'] );
		$this->assert_count( 0, $GLOBALS['was_test_http_posts'] );
	}

	public function test_stale_processing_reconciliation_job_is_resumed_by_pending_worker() {
		$service = new OnboardingService();
		$registration = $service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-stale-processing-job',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		global $wpdb;
		$wpdb->update(
			TableNameResolver::getOnboardingRegistrationsTable(),
			[
				'meta_waba_id'           => 'meta-waba-stale-processing',
				'access_token_encrypted' => \WAS\Router\SecretVault::encrypt( 'direct-token' ),
				'token_exchange_status'  => 'exchanged',
			],
			[ 'id' => (int) $registration['id'] ]
		);
		$wpdb->insert(
			TableNameResolver::getOnboardingReconciliationJobsTable(),
			[
				'registration_id' => (int) $registration['id'],
				'status'          => 'processing',
				'attempts'        => 4,
				'created_at'      => gmdate( 'Y-m-d H:i:s', time() - 360 ),
				'updated_at'      => gmdate( 'Y-m-d H:i:s', time() - 300 ),
			]
		);
		$job_id = (int) $wpdb->insert_id;
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-stale-processing', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$result = $service->process_pending_reconciliation_jobs( 10 );
		$job = $this->job_by_id( $job_id );
		$registration = $this->registration_by_attempt( 'attempt-stale-processing-job' );

		$this->assert_same( 1, $result['processed'] );
		$this->assert_same( 'completed', $result['results'][0]['status'] );
		$this->assert_same( 'completed', $job['status'] );
		$this->assert_same( 5, (int) $job['attempts'] );
		$this->assert_same( 'completed', $registration->status );
		$this->assert_not_null( $registration->notified_at );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
	}

	public function test_processing_reconciliation_resets_inconsistent_onboarding_notification_state() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-inconsistent-job',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-inconsistent', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$first = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-inconsistent-job',
				'access_token' => 'direct-token',
				'waba_id'      => 'meta-waba-inconsistent',
			]
		);

		global $wpdb;
		$registration = $this->registration_by_attempt( 'attempt-inconsistent-job' );
		$wpdb->update(
			TableNameResolver::getOnboardingRegistrationsTable(),
			[
				'status'                  => 'onboarding',
				'last_notification_error' => '',
			],
			[ 'id' => (int) $registration->id ]
		);
		$wpdb->insert(
			TableNameResolver::getOnboardingReconciliationJobsTable(),
			[
				'registration_id' => (int) $registration->id,
				'status'          => 'pending',
				'attempts'        => 0,
				'created_at'      => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			]
		);
		$job_id = (int) $wpdb->insert_id;
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-inconsistent', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$result = $service->process_reconciliation_job( $job_id );
		$registration = $this->registration_by_attempt( 'attempt-inconsistent-job' );
		$job = $this->job_by_id( $job_id );

		$this->assert_same( 'completed', $first['status'] );
		$this->assert_same( 'completed', $result['status'] );
		$this->assert_same( 'completed', $job['status'] );
		$this->assert_same( 'completed', $registration->status );
		$this->assert_same( 200, (int) $registration->last_notification_status );
		$this->assert_not_null( $registration->notified_at );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
	}

	public function test_successful_registration_does_not_renotify_on_repeat() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'     => 1,
				'meta_app_id'   => 2,
				'attempt_id'    => 'attempt-repeat-success',
				'phone_number'  => '5531997121855',
				'callback_url'  => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-repeat', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-repeat', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
		];
		$payload = [
			'tenant_id'    => 1,
			'meta_app_id'  => 2,
			'attempt_id'   => 'attempt-repeat-success',
			'access_token' => 'direct-token',
			'waba_id'      => 'meta-waba-repeat',
		];

		$first = $service->complete_embedded_signup( $payload );
		$second = $service->complete_embedded_signup( $payload );

		$this->assert_same( 'completed', $first['status'] );
		$this->assert_same( 'completed', $second['status'] );
		$this->assert_same( 'delivered', $second['notification_status'] );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
		$this->assert_not_null( $this->registration_by_attempt( 'attempt-repeat-success' )->notified_at );
	}

	public function test_existing_onboarding_route_secret_is_updated_on_repeat() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-route-secret',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-secret', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$payload = [
			'tenant_id'    => 1,
			'meta_app_id'  => 2,
			'attempt_id'   => 'attempt-route-secret',
			'access_token' => 'direct-token',
			'waba_id'      => 'meta-waba-secret',
		];
		$first = $service->complete_embedded_signup( $payload );

		global $wpdb;
		$wpdb->update(
			TableNameResolver::getRoutesTable(),
			[ 'secret_encrypted' => \WAS\Router\SecretVault::encrypt( 'old-route-secret' ) ],
			[ 'id' => (int) $first['route_id'] ]
		);
		$GLOBALS['was_test_options']['was_router_route_secret'] = 'rotated-secret';
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-secret', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
		];

		$second = $service->complete_embedded_signup( $payload );
		$route = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TableNameResolver::getRoutesTable() . ' WHERE id = %d LIMIT 1', (int) $first['route_id'] ) );

		$this->assert_same( $first['route_id'], $second['route_id'] );
		$this->assert_same( 'rotated-secret', \WAS\Router\SecretVault::decrypt( $route->secret_encrypted ) );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
	}

	public function test_reregistration_same_callback_reuses_operational_records_and_updates_secret() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-same-callback-first',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-same-callback', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$first = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-same-callback-first',
				'access_token' => 'direct-token',
				'waba_id'      => 'meta-waba-same-callback',
			]
		);

		$GLOBALS['was_test_options']['was_router_route_secret'] = 'rotated-secret';
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-same-callback-second',
				'phone_number' => '553197121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_gets'] = [];
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-same-callback', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$second = $service->complete_embedded_signup(
			[
				'tenant_id'          => 1,
				'meta_app_id'        => 2,
				'attempt_id'         => 'attempt-same-callback-second',
				'authorization_code' => 'used-later',
				'waba_id'            => 'meta-waba-same-callback',
			]
		);

		global $wpdb;
		$route = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TableNameResolver::getRoutesTable() . ' WHERE id = %d LIMIT 1', (int) $first['route_id'] ) );
		$routes = array_values(
			array_filter(
				$GLOBALS['wpdb']->tables[ TableNameResolver::getRoutesTable() ] ?? [],
				fn( $row ) => (int) ( $row['phone_number_id'] ?? 0 ) === (int) $first['phone_number_id']
			)
		);

		$this->assert_same( 'completed', $first['status'] );
		$this->assert_same( 'completed', $second['status'] );
		$this->assert_same( $first['waba_id'], $second['waba_id'] );
		$this->assert_same( $first['phone_number_id'], $second['phone_number_id'] );
		$this->assert_same( $first['route_id'], $second['route_id'] );
		$this->assert_false( $second['token_exchanged'] );
		$this->assert_count( 1, $routes );
		$this->assert_same( 'rotated-secret', \WAS\Router\SecretVault::decrypt( $route->secret_encrypted ) );
		$this->assert_count( 0, array_filter( $GLOBALS['was_test_http_gets'], fn( $call ) => str_contains( $call['url'], '/oauth/access_token' ) ) );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
	}

	public function test_existing_connected_number_completes_when_meta_phone_listing_fails() {
		global $wpdb;
		$this->seed_existing_connected_number();

		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'             => 1,
				'meta_app_id'           => 2,
				'attempt_id'            => 'attempt-existing-phone',
				'phone_number'          => '5531997121855',
				'phone_number_variants' => [ '553197121855' ],
				'callback_url'          => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 400,
				'body' => [ 'error' => [ 'message' => 'permission denied' ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$result = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-existing-phone',
				'access_token' => 'direct-token',
				'waba_id'      => 'new-real-waba',
				'business_id'  => 'new-business',
			]
		);
		$phone = $wpdb->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ) . ' WHERE id = 10 LIMIT 1' );
		$new_waba = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'whatsapp_accounts' ) . ' WHERE id = %d LIMIT 1', (int) $phone->whatsapp_account_id ) );
		$registration = $this->registration_by_attempt( 'attempt-existing-phone' );

		$this->assert_same( 'completed', $result['status'] );
		$this->assert_same( 10, $result['phone_number_id'] );
		$this->assert_same( $result['waba_id'], $result['router_waba_id'] );
		$this->assert_same( 10, $result['router_phone_number_id'] );
		$this->assert_same( $result['route_id'], $result['router_route_id'] );
		$this->assert_same( 'existing-meta-phone', $result['meta_phone_number_id'] );
		$this->assert_same( 'existing-meta-phone', $result['whatsapp_phone_number_id'] );
		$this->assert_same( '+55 31 99712-1855', $result['display_phone_number'] );
		$this->assert_same( '5531997121855', $result['phone_number'] );
		$this->assert_same( 'delivered', $result['notification_status'] );
		$this->assert_same( 'new-real-waba', $new_waba->waba_id );
		$this->assert_same( 'new-real-waba', $registration->meta_waba_id );
		$this->assert_same( 10, (int) $registration->router_phone_number_id );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
		$callback = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_same( 'onboarding_completed', $callback['event_type'] );
		$this->assert_same( $result['router_waba_id'], $callback['router_waba_id'] );
		$this->assert_same( 10, $callback['router_phone_number_id'] );
		$this->assert_same( $result['router_route_id'], $callback['router_route_id'] );
		$this->assert_same( $result['route_id'], $callback['route_id'] );
		$this->assert_same( 'existing-meta-phone', $callback['meta_phone_number_id'] );
		$this->assert_same( 'existing-meta-phone', $callback['whatsapp_phone_number_id'] );
	}

	public function test_existing_connected_number_moves_to_new_real_waba_before_notifying_when_meta_listing_is_empty() {
		global $wpdb;
		$this->seed_existing_connected_number();

		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'             => 1,
				'meta_app_id'           => 2,
				'attempt_id'            => 'attempt-existing-phone-empty-list',
				'phone_number'          => '5531997121855',
				'phone_number_variants' => [ '553197121855' ],
				'callback_url'          => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$result = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-existing-phone-empty-list',
				'access_token' => 'direct-token',
				'waba_id'      => 'new-real-waba-empty-list',
				'business_id'  => 'new-business-empty-list',
			]
		);
		$phone = $wpdb->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ) . ' WHERE id = 10 LIMIT 1' );
		$new_waba = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'whatsapp_accounts' ) . ' WHERE id = %d LIMIT 1', (int) $phone->whatsapp_account_id ) );
		$old_waba = $wpdb->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'whatsapp_accounts' ) . ' WHERE id = 7 LIMIT 1' );
		$registration = $this->registration_by_attempt( 'attempt-existing-phone-empty-list' );
		$callback = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_same( 'completed', $result['status'] );
		$this->assert_same( 10, $result['phone_number_id'] );
		$this->assert_same( 'delivered', $result['notification_status'] );
		$this->assert_same( 'new-real-waba-empty-list', $new_waba->waba_id );
		$this->assert_same( 'old-meta-waba', $old_waba->waba_id );
		$this->assert_true( (int) $new_waba->id !== (int) $old_waba->id );
		$this->assert_same( 'new-real-waba-empty-list', $registration->meta_waba_id );
		$this->assert_same( 10, (int) $registration->router_phone_number_id );
		$this->assert_same( (int) $new_waba->id, (int) $registration->router_waba_id );
		$this->assert_same( 'new-real-waba-empty-list', $callback['meta_waba_id'] );
		$this->assert_same( (int) $new_waba->id, $callback['router_waba_id'] );
		$this->assert_same( 10, $callback['router_phone_number_id'] );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
	}

	public function test_existing_connected_number_does_not_renotify_after_successful_repeat_completion() {
		$this->seed_existing_connected_number();

		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'             => 1,
				'meta_app_id'           => 2,
				'attempt_id'            => 'attempt-existing-phone-repeat',
				'phone_number'          => '5531997121855',
				'phone_number_variants' => [ '553197121855' ],
				'callback_url'          => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$payload = [
			'tenant_id'    => 1,
			'meta_app_id'  => 2,
			'attempt_id'   => 'attempt-existing-phone-repeat',
			'access_token' => 'direct-token',
			'waba_id'      => 'new-real-waba',
		];
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 400,
				'body' => [ 'error' => [ 'message' => 'permission denied' ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
			[
				'code' => 400,
				'body' => [ 'error' => [ 'message' => 'permission denied' ] ],
			],
		];

		$first = $service->complete_embedded_signup( $payload );
		$second = $service->complete_embedded_signup( $payload );
		$registration = $this->registration_by_attempt( 'attempt-existing-phone-repeat' );

		$this->assert_same( 'completed', $first['status'] );
		$this->assert_same( 'completed', $second['status'] );
		$this->assert_same( 'delivered', $second['notification_status'] );
		$this->assert_same( 10, $second['router_phone_number_id'] );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
		$this->assert_not_null( $registration->notified_at );
		$this->assert_same( 200, (int) $registration->last_notification_status );
	}

	public function test_reopened_registration_for_existing_connected_number_notifies_again() {
		$this->seed_existing_connected_number();

		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'             => 1,
				'meta_app_id'           => 2,
				'attempt_id'            => 'attempt-existing-phone-first',
				'phone_number'          => '5531997121855',
				'phone_number_variants' => [ '553197121855' ],
				'callback_url'          => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 400,
				'body' => [ 'error' => [ 'message' => 'permission denied' ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$first = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-existing-phone-first',
				'access_token' => 'direct-token',
				'waba_id'      => 'new-real-waba',
			]
		);

		$service->create_registration(
			[
				'tenant_id'             => 1,
				'meta_app_id'           => 2,
				'attempt_id'            => 'attempt-existing-phone-reopened',
				'phone_number'          => '553197121855',
				'phone_number_variants' => [ '5531997121855' ],
				'callback_url'          => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$reopened = $this->registration_by_attempt( 'attempt-existing-phone-reopened' );
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 400,
				'body' => [ 'error' => [ 'message' => 'permission denied' ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$second = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-existing-phone-reopened',
				'access_token' => 'direct-token',
				'waba_id'      => 'new-real-waba',
			]
		);

		$this->assert_same( 'completed', $first['status'] );
		$this->assert_same( 'onboarding', $reopened->status );
		$this->assert_null( $reopened->notified_at );
		$this->assert_null( $reopened->last_notification_status );
		$this->assert_null( $reopened->last_notification_error );
		$this->assert_same( 'completed', $second['status'] );
		$this->assert_same( 10, $second['router_phone_number_id'] );
		$this->assert_same( 'delivered', $second['notification_status'] );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
		$this->assert_same( 'attempt-existing-phone-reopened', json_decode( $GLOBALS['was_test_http_posts'][1]['args']['body'], true )['attempt_id'] );
	}

	public function test_reregistration_different_callback_creates_new_default_route_and_disables_previous() {
		$service = new OnboardingService();
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-old-callback',
				'phone_number' => '5531997121855',
				'callback_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-callback', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$first = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-old-callback',
				'access_token' => 'direct-token',
				'waba_id'      => 'meta-waba-callback',
			]
		);

		$new_callback = 'https://agenda.dominai.cloud/whatsapp/webhooks/new-waba-router/';
		$service->create_registration(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-new-callback',
				'phone_number' => '553197121855',
				'callback_url' => $new_callback,
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'data' => [ [ 'id' => 'meta-phone-callback', 'display_phone_number' => '+55 31 99712-1855' ] ] ],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];
		$second = $service->complete_embedded_signup(
			[
				'tenant_id'    => 1,
				'meta_app_id'  => 2,
				'attempt_id'   => 'attempt-new-callback',
				'access_token' => 'direct-token',
				'waba_id'      => 'meta-waba-callback',
			]
		);

		$phone = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ) . ' WHERE id = ' . (int) $second['phone_number_id'] . ' LIMIT 1' );
		$old_route = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::getRoutesTable() . ' WHERE id = ' . (int) $first['route_id'] . ' LIMIT 1' );
		$new_route = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::getRoutesTable() . ' WHERE id = ' . (int) $second['route_id'] . ' LIMIT 1' );

		$this->assert_same( 'completed', $second['status'] );
		$this->assert_true( (int) $second['route_id'] !== (int) $first['route_id'] );
		$this->assert_same( (int) $second['route_id'], (int) $phone->default_route_id );
		$this->assert_same( 0, (int) $old_route->is_active );
		$this->assert_same( 'disabled', $old_route->status );
		$this->assert_same( 1, (int) $new_route->is_active );
		$this->assert_same( $new_callback, $new_route->target_url );
	}

	private function registration_by_attempt( $attempt_id ) {
		global $wpdb;
		$table = TableNameResolver::getOnboardingRegistrationsTable();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE attempt_id = %s LIMIT 1", $attempt_id ) );
	}

	private function seed_existing_connected_number() {
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::get_table_name( 'whatsapp_accounts' ),
			[
				'id'               => 7,
				'tenant_id'        => 1,
				'meta_app_id'      => 2,
				'waba_id'          => 'old-meta-waba',
				'meta_business_id' => 'old-business',
				'name'             => 'Old WABA',
				'status'           => 'active',
				'created_at'       => current_time( 'mysql', true ),
			]
		);
		$wpdb->insert(
			TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ),
			[
				'id'                   => 10,
				'tenant_id'            => 1,
				'whatsapp_account_id'  => 7,
				'phone_number_id'      => 'existing-meta-phone',
				'display_phone_number' => '+55 31 99712-1855',
				'verified_name'        => 'Nome da empresa',
				'status'               => 'active',
				'created_at'           => current_time( 'mysql', true ),
				'updated_at'           => current_time( 'mysql', true ),
			]
		);
	}

	private function latest_job() {
		$jobs = $GLOBALS['wpdb']->tables[ TableNameResolver::getOnboardingReconciliationJobsTable() ] ?? [];
		return end( $jobs );
	}

	private function job_by_id( $job_id ) {
		foreach ( $GLOBALS['wpdb']->tables[ TableNameResolver::getOnboardingReconciliationJobsTable() ] ?? [] as $job ) {
			if ( (int) $job['id'] === (int) $job_id ) {
				return $job;
			}
		}
		return null;
	}
}

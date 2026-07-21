<?php

use WAS\Core\TableNameResolver;
use WAS\Router\RouteRepository;
use WAS\Router\WebhookRouterService;
use WAS\WhatsApp\WebhookProcessor;

class WebhookRouterServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 5, 'tenant_id' => 1, 'meta_app_id' => 1, 'waba_id' => 'meta-waba-1', 'name' => 'WABA', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [ 'id' => 10, 'tenant_id' => 1, 'whatsapp_account_id' => 5, 'phone_number_id' => 'meta-phone-1', 'display_phone_number' => '+55 31 90000-0001', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [ 'id' => 11, 'tenant_id' => 1, 'whatsapp_account_id' => 5, 'phone_number_id' => 'meta-phone-2', 'display_phone_number' => '+55 31 90000-0002', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [ 'id' => 30, 'tenant_id' => 1, 'whatsapp_account_id' => 5, 'access_token_encrypted' => \WAS\Meta\TokenVault::encrypt( 'waba-token' ), 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );

		$routes = new RouteRepository();
		$routes->create_or_update( [ 'tenant_id' => 1, 'phone_number_id' => 10, 'name' => 'Phone 1', 'target_url' => 'https://agenda.test/webhook', 'secret' => 'route-secret', 'is_active' => true ] );
		$routes->create_or_update( [ 'tenant_id' => 1, 'phone_number_id' => 11, 'name' => 'Phone 2', 'target_url' => 'https://agenda.test/webhook', 'secret' => 'route-secret', 'is_active' => true ] );
	}

	public function test_media_extension_uses_correct_mime_type_suffixes() {
		$service = new WebhookRouterService();
		$method = ( new ReflectionClass( $service ) )->getMethod( 'media_extension' );
		$method->setAccessible( true );

		$cases = [
			[ [ 'type' => 'audio', 'mime_type' => 'audio/ogg; codecs=opus' ], '.ogg' ],
			[ [ 'type' => 'audio', 'mime_type' => 'audio/mpeg' ], '.mp3' ],
			[ [ 'type' => 'audio', 'mime_type' => 'audio/mp4' ], '.m4a' ],
			[ [ 'type' => 'image', 'mime_type' => 'image/jpeg' ], '.jpg' ],
			[ [ 'type' => 'image', 'mime_type' => 'image/png' ], '.png' ],
			[ [ 'type' => 'video', 'mime_type' => 'video/mp4' ], '.mp4' ],
			[ [ 'type' => 'document', 'mime_type' => 'application/pdf' ], '.pdf' ],
		];

		foreach ( $cases as $case ) {
			$this->assert_same( $case[1], $method->invoke( $service, $case[0] ), 'Unexpected extension for ' . $case[0]['mime_type'] );
		}
	}

	public function test_inbound_media_marks_http_download_errors_instead_of_leaving_pending() {
		global $wpdb;

		\WAS\Auth\TenantContext::set_tenant_id( 1 );
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'url' => 'https://lookaside.fbsbx.com/media/audio-error' ],
			],
			[
				'code' => 502,
				'body' => 'upstream unavailable',
			],
		];

		$result = ( new \WAS\WhatsApp\InboundMediaService() )->handle_inbound_media(
			1,
			30,
			31,
			'audio-error-id',
			'audio',
			'audio/ogg; codecs=opus'
		);
		$media = $wpdb->tables[ TableNameResolver::get_table_name( 'media' ) ][0];

		$this->assert_false( $result );
		$this->assert_same( 'failed', $media['status'] );
		$this->assert_true( str_contains( $media['error_message'], 'HTTP 502' ) );
	}

	public function test_inbound_media_uses_token_from_webhook_phone_waba() {
		global $wpdb;

		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [
			'id'         => 6,
			'tenant_id'  => 1,
			'waba_id'    => 'meta-waba-2',
			'created_at' => current_time( 'mysql', true ),
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [
			'id'                  => 12,
			'tenant_id'           => 1,
			'whatsapp_account_id' => 6,
			'phone_number_id'     => 'meta-phone-2-waba',
			'created_at'          => current_time( 'mysql', true ),
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [
			'id'                    => 31,
			'tenant_id'             => 1,
			'whatsapp_account_id'   => 6,
			'access_token_encrypted' => \WAS\Meta\TokenVault::encrypt( 'waba-2-token' ),
			'status'                => 'active',
			'created_at'            => current_time( 'mysql', true ),
		] );

		\WAS\Auth\TenantContext::set_tenant_id( 1 );
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [ 'url' => 'https://lookaside.fbsbx.com/media/account-specific' ],
			],
			[
				'code' => 200,
				'body' => 'image-by-account-token',
			],
		];

		$result = ( new \WAS\WhatsApp\InboundMediaService() )->handle_inbound_media(
			1,
			30,
			31,
			'account-specific-media-id',
			'image',
			'image/jpeg',
			'inbound',
			'meta-phone-2-waba'
		);

		$this->assert_true( $result );
		$this->assert_same( 'Bearer waba-2-token', $GLOBALS['was_test_http_gets'][0]['args']['headers']['Authorization'] );
		$this->assert_same( 'Bearer waba-2-token', $GLOBALS['was_test_http_gets'][1]['args']['headers']['Authorization'] );
	}

	public function test_routes_multi_number_webhook_to_each_internal_phone_route() {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'messaging_product' => 'whatsapp',
								'metadata'          => [ 'phone_number_id' => 'meta-phone-1', 'display_phone_number' => '+55 31 90000-0001' ],
								'contacts'          => [ [ 'profile' => [ 'name' => 'Ana' ], 'wa_id' => '5531999000001' ] ],
								'messages'          => [ [ 'id' => 'wamid-1', 'from' => '5531999000001', 'timestamp' => '1710000000', 'type' => 'text', 'text' => [ 'body' => 'Oi' ] ] ],
							],
						],
						[
							'field' => 'messages',
							'value' => [
								'messaging_product' => 'whatsapp',
								'metadata'          => [ 'phone_number_id' => 'meta-phone-2', 'display_phone_number' => '+55 31 90000-0002' ],
								'contacts'          => [ [ 'profile' => [ 'name' => 'Bia' ], 'wa_id' => '5531999000002' ] ],
								'messages'          => [ [ 'id' => 'wamid-2', 'from' => '5531999000002', 'timestamp' => '1710000001', 'type' => 'text', 'text' => [ 'body' => 'Ola' ] ] ],
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );

		$this->assert_true( $result['success'] );
		$this->assert_count( 2, $result['events'] );
		$events = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ];
		$deliveries = $GLOBALS['wpdb']->tables[ TableNameResolver::getOutboxDeliveriesTable() ];
		$this->assert_same( [ 10, 11 ], array_values( array_map( fn( $event ) => (int) $event['whatsapp_phone_number_row_id'], $events ) ) );
		$this->assert_count( 2, $deliveries );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
		$this->assert_same( 'delivered', $deliveries[0]['status'] );
		$this->assert_same( 'route-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );
	}

	public function test_webhook_is_delivered_to_all_active_routes_for_the_same_phone() {
		$second_route_id = ( new RouteRepository() )->create_or_update( [
			'tenant_id'       => 1,
			'phone_number_id' => 10,
			'name'            => 'Phone 1 secondary route',
			'target_url'      => 'https://secondary.test/webhook',
			'secret'          => 'secondary-secret',
			'is_active'       => true,
		] );

		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'messaging_product' => 'whatsapp',
								'metadata'          => [ 'phone_number_id' => 'meta-phone-1' ],
								'messages'          => [
									[ 'id' => 'wamid-same-phone-routes', 'from' => '5531999000001', 'type' => 'text', 'text' => [ 'body' => 'Oi' ] ],
								],
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$routes = ( new RouteRepository() )->active_for_phone( 10, 1 );
		$bodies = array_map(
			fn( $call ) => json_decode( $call['args']['body'], true ),
			$GLOBALS['was_test_http_posts']
		);
		$route_ids = array_values( array_map( fn( $body ) => (int) $body['route_id'], $bodies ) );
		$expected_route_ids = [ 1, (int) $second_route_id ];
		sort( $route_ids );
		sort( $expected_route_ids );

		$this->assert_true( $result['success'] );
		$this->assert_count( 2, $routes );
		$this->assert_count( 2, $result['events'][0]['deliveries'] );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
		$this->assert_same( $expected_route_ids, $route_ids );
		$this->assert_same( 'https://agenda.test/webhook', $GLOBALS['was_test_http_posts'][0]['url'] );
		$this->assert_same( 'https://secondary.test/webhook', $GLOBALS['was_test_http_posts'][1]['url'] );
		$this->assert_same( 'route-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );
		$this->assert_same( 'secondary-secret', $GLOBALS['was_test_http_posts'][1]['args']['headers']['x-waba-router-secret'] );
	}

	public function test_message_received_delivery_payload_matches_debounce_waba_router_normalizer_contract() {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'messaging_product' => 'whatsapp',
								'metadata'          => [ 'phone_number_id' => 'meta-phone-1', 'display_phone_number' => '+55 31 90000-0001' ],
								'contacts'          => [ [ 'profile' => [ 'name' => 'Ana' ], 'wa_id' => '5531999000001' ] ],
								'messages'          => [ [ 'id' => 'wamid-debounce', 'from' => '5531999000001', 'timestamp' => '1710000000', 'type' => 'text', 'text' => [ 'body' => 'Oi direto' ] ] ],
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_true( $result['success'] );
		$this->assert_same( 'message_received', $delivered['event_type'] );
		$this->assert_same( 'text', $delivered['message_type'] );
		$this->assert_same( 'wamid-debounce', $delivered['wa_message_id'] );
		$this->assert_same( '5531999000001', $delivered['wa_from'] );
		$this->assert_same( 10, $delivered['phone_number_id'] );
		$this->assert_same( 1, $delivered['route_id'] );
		$this->assert_same( 'Oi direto', $delivered['payload']['message']['text']['body'] );
		$this->assert_same( 'Oi direto', $delivered['payload']['payload']['messages'][0]['text']['body'] );
		$this->assert_same( 'meta-phone-1', $delivered['payload']['metadata']['phone_number_id'] );
		$this->assert_same( 'meta-phone-1', $delivered['raw_payload']['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] );
	}

	public function test_duplicate_webhook_is_idempotent() {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'metadata' => [ 'phone_number_id' => 'meta-phone-1' ],
								'messages' => [ [ 'id' => 'wamid-duplicate', 'from' => '5531999000001', 'type' => 'text', 'text' => [ 'body' => 'Oi' ] ] ],
							],
						],
					],
				],
			],
		];

		$service = new WebhookRouterService();
		$service->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$second = $service->process_meta_payload( $payload, wp_json_encode( $payload ), true );

		$this->assert_same( 'duplicate', $second['events'][0]['routing_status'] );
		$this->assert_count( 1, $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ] );
		$this->assert_count( 1, $GLOBALS['wpdb']->tables[ TableNameResolver::getOutboxDeliveriesTable() ] );
	}

	public function test_message_status_webhook_routes_string_status_for_agenda_master_parser() {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'messaging_product' => 'whatsapp',
								'metadata'          => [ 'phone_number_id' => 'meta-phone-1', 'display_phone_number' => '+55 31 90000-0001' ],
								'statuses'          => [
									[
										'id'           => 'wamid-outbound-1',
										'status'       => 'failed',
										'timestamp'    => '1778760000',
										'recipient_id' => '5531999000001',
										'errors'       => [
											[
												'code'    => 131047,
												'message' => 'Reengagement message',
												'title'   => 'Outside customer service window',
											],
										],
									],
								],
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_true( $result['success'] );
		$this->assert_same( 'message_status', $event['event_type'] );
		$this->assert_same( 'failed', $event['message_type'] );
		$this->assert_same( 'wamid-outbound-1', $event['wa_message_id'] );
		$this->assert_same( 'message_status', $delivered['event_type'] );
		$this->assert_same( 'failed', $delivered['message_type'] );
		$this->assert_same( 'wamid-outbound-1', $delivered['wa_message_id'] );
		$this->assert_same( 10, $delivered['phone_number_id'] );
		$this->assert_same( 'failed', $delivered['payload']['status'] );
		$this->assert_same( 'failed', $delivered['payload']['payload']['status'] );
		$this->assert_same( 131047, $delivered['payload']['payload']['errors'][0]['code'] );
		$this->assert_same( 'Reengagement message', $delivered['payload']['payload']['errors'][0]['message'] );
		$this->assert_same( 'route-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );
	}

	public function test_account_update_queues_onboarding_reconciliation_for_matching_registration() {
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::getOnboardingRegistrationsTable(),
			[
				'id'                         => 90,
				'tenant_id'                  => 1,
				'meta_app_id'                => 1,
				'company_id'                 => 'company-1',
				'attempt_id'                 => 'attempt-account-update',
				'phone_number'               => '5531990000001',
				'phone_number_variants_json' => wp_json_encode( [ '5531990000001' ] ),
				'provider'                   => 'meta_whatsapp',
				'callback_url'               => 'https://agenda.test/onboarding/callback',
				'status'                     => 'onboarding',
				'created_at'                 => current_time( 'mysql', true ),
				'updated_at'                 => current_time( 'mysql', true ),
			]
		);
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-business-entry',
					'changes' => [
						[
							'field' => 'account_update',
							'value' => [
								'event'     => 'PARTNER_ADDED',
								'waba_info' => [
									'waba_id'           => 'meta-waba-1',
									'owner_business_id' => 'business-123',
									'partner_app_id'    => 'partner-app-1',
								],
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$registration = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TableNameResolver::getOnboardingRegistrationsTable() . ' WHERE id = %d LIMIT 1', 90 ) );
		$jobs = $GLOBALS['wpdb']->tables[ TableNameResolver::getOnboardingReconciliationJobsTable() ] ?? [];

		$this->assert_same( 'onboarding_queued', $result['events'][0]['routing_status'] );
		$this->assert_same( 'account_update', $event['event_type'] );
		$this->assert_same( 'account_update', $event['message_type'] );
		$this->assert_same( 'onboarding_queued', $event['routing_status'] );
		$this->assert_true( str_contains( $event['routing_note'], 'account_update_onboarding_reconciliation_job_ids=' ) );
		$this->assert_same( 'meta-waba-1', $registration->meta_waba_id );
		$this->assert_same( 'business-123', $registration->owner_business_id );
		$this->assert_same( 'business-123', $registration->business_id );
		$this->assert_count( 1, $jobs );
		$this->assert_same( 90, (int) $jobs[0]['registration_id'] );
		$this->assert_same( 'pending', $jobs[0]['status'] );
		$this->assert_count( 0, $GLOBALS['was_test_http_posts'] );

		$second = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$this->assert_same( 'duplicate', $second['events'][0]['routing_status'] );
		$this->assert_count( 1, $GLOBALS['wpdb']->tables[ TableNameResolver::getOnboardingReconciliationJobsTable() ] );
	}

	public function test_account_update_without_matching_onboarding_registration_is_stored_without_delivery() {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-business-entry',
					'changes' => [
						[
							'field' => 'account_update',
							'value' => [
								'event'     => 'PARTNER_ADDED',
								'waba_info' => [
									'waba_id'           => 'meta-waba-without-registration',
									'owner_business_id' => 'business-404',
								],
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];

		$this->assert_same( 'stored', $result['events'][0]['routing_status'] );
		$this->assert_same( 'account_update', $event['event_type'] );
		$this->assert_same( 'stored', $event['routing_status'] );
		$this->assert_same( 'account_update_no_matching_onboarding_registration', $event['routing_note'] );
		$this->assert_count( 0, $GLOBALS['wpdb']->tables[ TableNameResolver::getOnboardingReconciliationJobsTable() ] ?? [] );
		$this->assert_count( 0, $GLOBALS['was_test_http_posts'] );
	}

	public function test_phone_number_quality_update_routes_by_value_phone_number_id() {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'phone_number_quality_update',
							'value' => [
								'event'                        => 'QUALITY_UPDATE',
								'phone_number_id'              => 'meta-phone-1',
								'current_quality_update_event' => 'FLAGGED',
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_same( 'queued', $result['events'][0]['routing_status'] );
		$this->assert_same( 'phone_number_quality_update', $event['event_type'] );
		$this->assert_same( 'QUALITY_UPDATE', $event['message_type'] );
		$this->assert_same( 'meta-phone-1', $event['phone_number_id'] );
		$this->assert_same( 10, (int) $event['whatsapp_phone_number_row_id'] );
		$this->assert_same( 'phone_number_quality_update', $delivered['event_type'] );
		$this->assert_same( 'meta-phone-1', $delivered['payload']['meta_phone_number_id'] );
		$this->assert_same( 'FLAGGED', $delivered['payload']['quality_update_event'] );
		$this->assert_same( 'route-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );
	}

	public function test_phone_number_name_update_routes_by_value_phone_number_id() {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'phone_number_name_update',
							'value' => [
								'event'                => 'NAME_APPROVED',
								'phone_number_id'      => 'meta-phone-2',
								'display_phone_number' => '+55 31 90000-0002',
								'name_status'          => 'APPROVED',
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_same( 'queued', $result['events'][0]['routing_status'] );
		$this->assert_same( 'phone_number_name_update', $event['event_type'] );
		$this->assert_same( 'NAME_APPROVED', $event['message_type'] );
		$this->assert_same( 'meta-phone-2', $event['phone_number_id'] );
		$this->assert_same( 11, (int) $event['whatsapp_phone_number_row_id'] );
		$this->assert_same( 'phone_number_name_update', $delivered['event_type'] );
		$this->assert_same( 'meta-phone-2', $delivered['payload']['meta_phone_number_id'] );
		$this->assert_same( 'APPROVED', $delivered['payload']['name_status'] );
	}

	public function test_routes_smb_message_echoes_as_message_echo_events() {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'smb_message_echoes',
							'value' => [
								'contacts' => [ [ 'user_id' => 'BR.1', 'wa_id' => '553171183457' ] ],
								'message_echoes' => [
									[
										'from'      => '553197121855',
										'id'        => 'wamid-echo-1',
										'text'      => [ 'body' => 'Show' ],
										'timestamp' => '1780348360',
										'to'        => '553171183457',
										'to_user_id'=> 'BR.1',
										'type'      => 'text',
									],
								],
								'metadata' => [
									'display_phone_number' => '553197121855',
									'phone_number_id'      => 'meta-phone-1',
								],
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_true( $result['success'] );
		$this->assert_same( 'message_echo', $event['event_type'] );
		$this->assert_same( 'text', $event['message_type'] );
		$this->assert_same( 'wamid-echo-1', $event['wa_message_id'] );
		$this->assert_same( '553197121855', $event['wa_from'] );
		$this->assert_same( 10, (int) $event['whatsapp_phone_number_row_id'] );
		$this->assert_same( 'message_echo', $delivered['event_type'] );
		$this->assert_same( '553171183457', $delivered['payload']['wa_to'] );
	}

	public function test_meta_template_status_webhook_routes_by_waba_and_template_without_phone_metadata() {
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::getTemplatesTable(),
			[
				'id'                    => 70,
				'tenant_id'             => 1,
				'whatsapp_account_id'   => 5,
				'router_phone_number_id'=> 10,
				'meta_template_id'      => '1531915351929970',
				'waba_id'               => 'meta-waba-1',
				'name'                  => 'appointment_reminder_v2',
				'language'              => 'pt_BR',
				'category'              => 'UTILITY',
				'status'                => 'PENDING',
				'components_json'       => wp_json_encode( [ [ 'type' => 'BODY', 'text' => 'Ola {{1}}' ] ] ),
				'body_text'             => 'Ola {{1}}',
				'created_at'            => current_time( 'mysql', true ),
				'updated_at'            => current_time( 'mysql', true ),
			]
		);
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'time'    => 1781545186,
					'changes' => [
						[
							'field' => 'message_template_status_update',
							'value' => [
								'event'                     => 'APPROVED',
								'message_template_category' => 'MARKETING',
								'message_template_id'       => 1531915351929970,
								'message_template_language' => 'pt_BR',
								'message_template_name'     => 'appointment_reminder_v2',
								'reason'                    => 'NONE',
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$template = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ][0];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_true( $result['success'] );
		$this->assert_same( 'template_status_updated', $event['event_type'] );
		$this->assert_same( 'queued', $event['routing_status'] );
		$this->assert_same( 10, (int) $event['whatsapp_phone_number_row_id'] );
		$this->assert_same( 'APPROVED', $template['status'] );
		$this->assert_same( 'MARKETING', $template['category'] );
		$this->assert_not_null( $template['approved_notified_at'] );
		$this->assert_same( 'template_status_updated', $delivered['event_type'] );
		$this->assert_same( 70, $delivered['payload']['template_id'] );
		$this->assert_same( '1531915351929970', $delivered['payload']['meta_template_id'] );
		$this->assert_same( 'appointment_reminder_v2', $delivered['payload']['template_name'] );
		$this->assert_same( 'APPROVED', $delivered['payload']['status'] );

		$repeated = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$this->assert_same( 'duplicate', $repeated['events'][0]['routing_status'] );
		$this->assert_count( 1, $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ] );
	}

	public function test_meta_template_category_webhook_updates_template_and_routes_by_template_phone() {
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::getTemplatesTable(),
			[
				'id'                    => 71,
				'tenant_id'             => 1,
				'whatsapp_account_id'   => 5,
				'router_phone_number_id'=> 10,
				'meta_template_id'      => '1531915351929970',
				'waba_id'               => 'meta-waba-1',
				'name'                  => 'appointment_reminder_v2',
				'language'              => 'pt_BR',
				'category'              => 'UTILITY',
				'status'                => 'APPROVED',
				'components_json'       => wp_json_encode( [ [ 'type' => 'BODY', 'text' => 'Ola {{1}}' ] ] ),
				'body_text'             => 'Ola {{1}}',
				'created_at'            => current_time( 'mysql', true ),
				'updated_at'            => current_time( 'mysql', true ),
			]
		);
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'time'    => 1781545071,
					'changes' => [
						[
							'field' => 'template_category_update',
							'value' => [
								'message_template_id'       => 1531915351929970,
								'message_template_language' => 'pt_BR',
								'message_template_name'     => 'appointment_reminder_v2',
								'new_category'              => 'MARKETING',
								'previous_category'         => 'UTILITY',
							],
						],
					],
				],
			],
		];

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$template = $GLOBALS['wpdb']->tables[ TableNameResolver::getTemplatesTable() ][0];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_true( $result['success'] );
		$this->assert_same( 'template_category_updated', $event['event_type'] );
		$this->assert_same( 10, (int) $event['whatsapp_phone_number_row_id'] );
		$this->assert_same( 'MARKETING', $template['category'] );
		$this->assert_same( 'template_category_updated', $delivered['event_type'] );
		$this->assert_same( 'MARKETING', $delivered['payload']['template_category'] );
		$this->assert_same( 'UTILITY', $delivered['payload']['previous_template_category'] );
	}

	public function test_inbound_media_is_enriched_with_meta_download_url() {
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'url'               => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=image-media',
					'mime_type'         => 'image/jpeg',
					'sha256'            => 'resolved-image-sha',
					'file_size'         => 45678,
					'messaging_product' => 'whatsapp',
				],
			],
		];
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'metadata' => [
									'phone_number_id'      => 'meta-phone-1',
									'display_phone_number' => '+55 31 90000-0001',
								],
								'messages' => [
									[
										'id'    => 'wamid-media-1',
										'from'  => '5531999990003',
										'type'  => 'image',
										'image' => [
											'id'        => 'image-media-id',
											'mime_type' => 'image/jpeg',
											'sha256'    => 'incoming-image-sha',
											'caption'   => 'foto',
										],
									],
								],
							],
						],
					],
				],
			],
		];

		( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$normalized = json_decode( $event['normalized_payload'], true );
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_same( 'image-media-id', $normalized['media']['id'] );
		$this->assert_same( 'resolved', $normalized['media']['url_status'] );
		$this->assert_same( 'not_enabled', $normalized['media']['storage_status'] );
		$this->assert_same( 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=image-media', $normalized['media']['download_url'] );
		$this->assert_true( $normalized['media']['url_requires_authorization'] );
		$this->assert_same( 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=image-media', $normalized['payload']['messages'][0]['image']['download_url'] );
		$this->assert_same( 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=image-media', $delivered['payload']['media']['download_url'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_gets'][0]['url'], '/v25.0/image-media-id' ) );
		$this->assert_same( 'Bearer waba-token', $GLOBALS['was_test_http_gets'][0]['args']['headers']['Authorization'] );
	}

	public function test_route_payload_reuses_public_url_from_media_already_downloaded_by_inbox() {
		global $wpdb;

		$wpdb->insert(
			TableNameResolver::get_table_name( 'media' ),
			[
				'id'              => 91,
				'tenant_id'       => 1,
				'conversation_id' => 30,
				'message_id'      => 31,
				'meta_media_id'   => 'image-media-local',
				'media_type'      => 'image',
				'mime_type'       => 'image/jpeg',
				'direction'       => 'inbound',
				'public_url'      => 'https://wordpress.test/uploads/was-inbox-image.jpg',
				'storage_path'    => '/var/www/uploads/was-inbox-image.jpg',
				'file_size'       => 1234,
				'status'          => 'downloaded',
				'created_at'      => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			]
		);

		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'metadata' => [ 'phone_number_id' => 'meta-phone-1' ],
								'messages' => [
									[
										'id'    => 'wamid-local-media',
										'from'  => '5531999000001',
										'type'  => 'image',
										'image' => [ 'id' => 'image-media-local', 'mime_type' => 'image/jpeg' ],
									],
								],
							],
						],
					],
				],
			],
		];

		( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_same( 'https://wordpress.test/uploads/was-inbox-image.jpg', $delivered['payload']['media']['public_url'] );
		$this->assert_same( 'https://wordpress.test/uploads/was-inbox-image.jpg', $delivered['payload']['media']['download_url'] );
		$this->assert_false( $delivered['payload']['media']['url_requires_authorization'] );
		$this->assert_same( 'stored', $delivered['payload']['media']['storage_status'] );
		$this->assert_count( 0, $GLOBALS['was_test_http_gets'] );
	}

	public function test_webhook_downloads_media_locally_before_route_receives_public_url() {
		global $wpdb;

		$wpdb->insert(
			TableNameResolver::get_table_name( 'contacts' ),
			[
				'id'                => 40,
				'tenant_id'         => 1,
				'wa_id'             => '5531999000005',
				'phone'             => '5531999000005',
				'normalized_phone'  => '5531999000005',
				'phone_status'      => 'confirmed_by_wa_id',
				'profile_name'      => 'Cliente da rota',
				'name_locked'       => 0,
				'created_at'        => current_time( 'mysql', true ),
				'updated_at'        => current_time( 'mysql', true ),
			]
		);

		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'url'       => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=full-flow-image',
					'mime_type' => 'image/jpeg',
				],
			],
			[
				'code' => 200,
				'body' => 'image-from-facebook',
			],
		];

		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'metadata' => [ 'phone_number_id' => 'meta-phone-1' ],
								'contacts' => [ [ 'wa_id' => '5531999000005', 'profile' => [ 'name' => 'Cliente da rota' ] ] ],
								'messages' => [
									[
										'id'    => 'wamid-full-flow-image',
										'from'  => '5531999000005',
										'timestamp' => '1784214718',
										'type'  => 'image',
										'image' => [ 'id' => 'full-flow-image-id', 'mime_type' => 'image/jpeg' ],
									],
								],
							],
						],
					],
				],
			],
		];

		( new WebhookProcessor() )->process( $payload );
		( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );

		$media = $wpdb->tables[ TableNameResolver::get_table_name( 'media' ) ][0];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_same( 'downloaded', $media['status'] );
		$this->assert_same( 'https://wordpress.test/uploads/full-flow-image-id.jpg', $media['public_url'] );
		$this->assert_same( 'image-from-facebook', $GLOBALS['was_test_uploads'][0]['bits'] );
		$this->assert_same( 'https://wordpress.test/uploads/full-flow-image-id.jpg', $delivered['payload']['media']['public_url'] );
		$this->assert_same( 'https://wordpress.test/uploads/full-flow-image-id.jpg', $delivered['payload']['media']['download_url'] );
		$diagnostics = ( new \WAS\Inbox\MessageRepository() )->get_route_diagnostics( 'wamid-full-flow-image', 1 );
		$this->assert_count( 1, $diagnostics );
		$this->assert_true( $diagnostics[0]['public_url_sent'] );
		$this->assert_same( 'https://wordpress.test/uploads/full-flow-image-id.jpg', $diagnostics[0]['public_url'] );
		$this->assert_count( 2, $GLOBALS['was_test_http_gets'] );
	}

	public function test_inbound_media_can_be_stored_in_wordpress_uploads() {
		update_option( 'was_router_media_storage_enabled', true );
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'url'       => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=audio-media',
					'mime_type' => 'audio/ogg; codecs=opus',
					'sha256'    => 'resolved-audio-sha',
					'file_size' => 9876,
				],
			],
			[
				'code' => 200,
				'body' => 'stored-media-bytes',
			],
		];
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'messages',
							'value' => [
								'metadata' => [ 'phone_number_id' => 'meta-phone-1' ],
								'messages' => [
									[
										'id'    => 'wamid-media-audio',
										'from'  => '5531999990004',
										'type'  => 'audio',
										'audio' => [
											'id'        => 'audio-media-id',
											'mime_type' => 'audio/ogg',
											'sha256'    => 'incoming-audio-sha',
											'voice'     => true,
										],
									],
								],
							],
						],
					],
				],
			],
		];

		( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$normalized = json_decode( $event['normalized_payload'], true );

		$this->assert_same( 'stored', $normalized['media']['url_status'] );
		$this->assert_same( 'stored', $normalized['media']['storage_status'] );
		$this->assert_false( $normalized['media']['url_requires_authorization'] );
		$this->assert_true( str_contains( $normalized['media']['download_url'], 'https://wordpress.test/uploads/was-router-meta-phone-1-audio-media-id.ogg' ) );
		$this->assert_same( 'stored-media-bytes', $GLOBALS['was_test_uploads'][0]['bits'] );
		$this->assert_same( 'Bearer waba-token', $GLOBALS['was_test_http_gets'][1]['args']['headers']['Authorization'] );
	}

	public function test_smb_audio_echo_media_is_enriched_and_stored_in_nested_payload() {
		update_option( 'was_router_media_storage_enabled', true );
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'url'               => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=audio-media',
					'mime_type'         => 'audio/ogg; codecs=opus',
					'sha256'            => 'resolved-audio-sha',
					'file_size'         => 9876,
					'messaging_product' => 'whatsapp',
				],
			],
			[
				'code' => 200,
				'body' => 'stored-media-bytes',
			],
		];
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => 'meta-waba-1',
					'changes' => [
						[
							'field' => 'smb_message_echoes',
							'value' => [
								'message_echoes' => [
									[
										'from'      => '553197121855',
										'id'        => 'wamid-audio-echo',
										'timestamp' => '1780348360',
										'to'        => '553171183457',
										'type'      => 'audio',
										'audio'     => [
											'id'        => 'audio-media-id',
											'mime_type' => 'audio/ogg',
											'sha256'    => 'incoming-audio-sha',
											'voice'     => true,
										],
									],
								],
								'messaging_product' => 'whatsapp',
								'metadata'          => [
									'display_phone_number' => '+55 31 90000-0001',
									'phone_number_id'      => 'meta-phone-1',
								],
							],
						],
					],
				],
			],
		];

		( new WebhookRouterService() )->process_meta_payload( $payload, wp_json_encode( $payload ), true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$normalized = json_decode( $event['normalized_payload'], true );
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$nested_audio = $normalized['payload']['message_echoes'][0]['audio'];

		$this->assert_same( 'message_echo', $event['event_type'] );
		$this->assert_same( 'audio', $event['message_type'] );
		$this->assert_same( 'stored', $normalized['media']['storage_status'] );
		$this->assert_same( 'stored', $nested_audio['storage_status'] );
		$this->assert_same( strlen( 'stored-media-bytes' ), $nested_audio['file_size'] );
		$this->assert_true( str_contains( $nested_audio['download_url'], 'https://wordpress.test/uploads/was-router-meta-phone-1-audio-media-id.ogg' ) );
		$this->assert_true( str_contains( $normalized['message']['audio']['download_url'], 'https://wordpress.test/uploads/was-router-meta-phone-1-audio-media-id.ogg' ) );
		$this->assert_same( 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=audio-media', $nested_audio['meta_download_url'] );
		$this->assert_false( $delivered['payload']['media']['url_requires_authorization'] );
		$this->assert_true( str_contains( $delivered['payload']['media']['download_url'], 'https://wordpress.test/uploads/was-router-meta-phone-1-audio-media-id.ogg' ) );
		$this->assert_same( 'Bearer waba-token', $GLOBALS['was_test_http_gets'][0]['args']['headers']['Authorization'] );
		$this->assert_same( 'Bearer waba-token', $GLOBALS['was_test_http_gets'][1]['args']['headers']['Authorization'] );
		$this->assert_same( 'stored-media-bytes', $GLOBALS['was_test_uploads'][0]['bits'] );
	}
}

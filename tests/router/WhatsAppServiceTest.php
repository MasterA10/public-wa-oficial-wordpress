<?php

use WAS\Core\TableNameResolver;
use WAS\Router\WhatsAppService;

class WhatsAppServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
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

	public function test_send_text_message_uses_official_meta_payload_and_router_aliases() {
		$result = ( new WhatsAppService() )->send_message(
			[
				'phone_number_id' => 10,
				'to_number'       => '55 (11) 88888-8888',
				'message_type'    => 'text',
				'payload'         => [
					'text' => [
						'body'        => 'Ola pelo Router',
						'preview_url' => true,
					],
				],
			]
		);

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 'sent', $result['status'] );
		$this->assert_same( 'wamid.router', $result['meta_message_id'] );
		$this->assert_same( 'wamid.router', $result['wa_message_id'] );

		$call = $GLOBALS['was_test_http_posts'][0];
		$body = json_decode( $call['args']['body'], true );
		$this->assert_true( str_contains( $call['url'], '/v25.0/meta-phone-1/messages' ) );
		$this->assert_same( 'Bearer waba-token', $call['args']['headers']['Authorization'] );
		$this->assert_same( 'whatsapp', $body['messaging_product'] );
		$this->assert_same( '5511888888888', $body['to'] );
		$this->assert_same( 'text', $body['type'] );
		$this->assert_same( 'Ola pelo Router', $body['text']['body'] );
	}

	public function test_send_message_automatically_shows_typing_for_an_existing_conversation() {
		global $wpdb;

		$wpdb->insert( TableNameResolver::get_table_name( 'contacts' ), [
			'id'              => 30,
			'tenant_id'       => 1,
			'wa_id'           => '5511999999999',
			'phone'           => '5511999999999',
			'normalized_phone' => '5511999999999',
			'phone_status'    => 'confirmed_by_wa_id',
			'created_at'      => current_time( 'mysql', true ),
			'updated_at'      => current_time( 'mysql', true ),
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'conversations' ), [
			'id'                         => 40,
			'tenant_id'                  => 1,
			'contact_id'                 => 30,
			'phone_number_id'            => 'meta-phone-1',
			'last_inbound_wa_message_id' => 'wamid.router-inbound',
			'status'                     => 'open',
			'created_at'                 => current_time( 'mysql', true ),
			'updated_at'                 => current_time( 'mysql', true ),
		] );

		$result = ( new WhatsAppService() )->send_message( [
			'phone_number_id' => 10,
			'to_number'       => '5511999999999',
			'message_type'    => 'text',
			'payload'         => [ 'text' => [ 'body' => 'Mensagem com typing' ] ],
		] );

		$typing = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$message = json_decode( $GLOBALS['was_test_http_posts'][1]['args']['body'], true );

		$this->assert_true( $result['success'] );
		$this->assert_same( 'read', $typing['status'] );
		$this->assert_same( 'wamid.router-inbound', $typing['message_id'] );
		$this->assert_same( 'text', $typing['typing_indicator']['type'] );
		$this->assert_same( 'Mensagem com typing', $message['text']['body'] );
	}

	public function test_send_interactive_message_preserves_official_payload_without_router_fields() {
		( new WhatsAppService() )->send_message(
			[
				'phone_number_id' => 10,
				'to_number'       => '5511888888888',
				'message_type'    => 'interactive',
				'payload'         => [
					'interactive' => [
						'type' => 'button',
						'body' => [ 'text' => 'Escolha uma opcao' ],
						'action' => [
							'buttons' => [
								[ 'type' => 'reply', 'reply' => [ 'id' => 'agendar', 'title' => 'Agendar' ] ],
							],
						],
					],
				],
			]
		);

		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_same( 'interactive', $body['type'] );
		$this->assert_same( 'button', $body['interactive']['type'] );
		$this->assert_false( array_key_exists( 'phone_number_id', $body ) );
		$this->assert_false( array_key_exists( 'to_number', $body ) );
		$this->assert_false( array_key_exists( 'payload', $body ) );
	}

	public function test_send_image_link_preserves_official_media_payload_without_router_fields() {
		( new WhatsAppService() )->send_message(
			[
				'phone_number_id' => 10,
				'to_number'       => '5511999999999',
				'message_type'    => 'image',
				'payload'         => [
					'image' => [
						'link'    => 'https://cdn.test/foto.jpg',
						'caption' => 'Foto do produto',
					],
				],
			]
		);

		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_same( 'whatsapp', $body['messaging_product'] );
		$this->assert_same( '5511999999999', $body['to'] );
		$this->assert_same( 'image', $body['type'] );
		$this->assert_same( 'https://cdn.test/foto.jpg', $body['image']['link'] );
		$this->assert_same( 'Foto do produto', $body['image']['caption'] );
		$this->assert_false( array_key_exists( 'phone_number_id', $body ) );
		$this->assert_false( array_key_exists( 'to_number', $body ) );
		$this->assert_false( array_key_exists( 'payload', $body ) );
	}

	public function test_send_template_message_preserves_official_template_payload_without_router_fields() {
		( new WhatsAppService() )->send_message(
			[
				'phone_number_id' => 10,
				'to_number'       => '5511999999999',
				'message_type'    => 'template',
				'payload'         => [
					'template' => [
						'name'     => 'hello_world',
						'language' => [ 'code' => 'en_US' ],
					],
				],
			]
		);

		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_same( 'whatsapp', $body['messaging_product'] );
		$this->assert_same( '5511999999999', $body['to'] );
		$this->assert_same( 'template', $body['type'] );
		$this->assert_same( 'hello_world', $body['template']['name'] );
		$this->assert_same( 'en_US', $body['template']['language']['code'] );
		$this->assert_false( array_key_exists( 'phone_number_id', $body ) );
		$this->assert_false( array_key_exists( 'to_number', $body ) );
		$this->assert_false( array_key_exists( 'payload', $body ) );
	}

	public function test_send_read_receipt_allows_empty_to_number_and_uses_official_meta_payload() {
		$result = ( new WhatsAppService() )->send_message(
			[
				'phone_number_id' => 10,
				'to_number'       => '',
				'message_type'    => 'read',
				'payload'         => [
					'status'     => 'read',
					'message_id' => 'wamid.inbound',
				],
				'idempotency_key' => 'read:wamid.inbound',
			]
		);

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 'sent', $result['status'] );
		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$outbound = $GLOBALS['wpdb']->tables[ TableNameResolver::getOutboundMessagesTable() ][0];
		$this->assert_same( 'whatsapp', $body['messaging_product'] );
		$this->assert_same( 'read', $body['status'] );
		$this->assert_same( 'wamid.inbound', $body['message_id'] );
		$this->assert_false( array_key_exists( 'to', $body ) );
		$this->assert_false( array_key_exists( 'type', $body ) );
		$this->assert_same( 'read', $outbound['message_type'] );
		$this->assert_same( '', $outbound['to_number'] );
	}

	public function test_send_typing_indicator_allows_empty_to_number_and_uses_official_meta_payload() {
		$result = ( new WhatsAppService() )->send_message(
			[
				'phone_number_id' => 10,
				'to_number'       => '',
				'message_type'    => 'typing_indicator',
				'payload'         => [
					'status'           => 'read',
					'message_id'       => 'wamid.inbound',
					'typing_indicator' => [ 'type' => 'text' ],
				],
				'idempotency_key' => 'typing:wamid.inbound',
			]
		);

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 'sent', $result['status'] );
		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$outbound = $GLOBALS['wpdb']->tables[ TableNameResolver::getOutboundMessagesTable() ][0];
		$this->assert_same( 'whatsapp', $body['messaging_product'] );
		$this->assert_same( 'read', $body['status'] );
		$this->assert_same( 'wamid.inbound', $body['message_id'] );
		$this->assert_same( 'text', $body['typing_indicator']['type'] );
		$this->assert_false( array_key_exists( 'to', $body ) );
		$this->assert_false( array_key_exists( 'type', $body ) );
		$this->assert_same( 'typing_indicator', $outbound['message_type'] );
		$this->assert_same( '', $outbound['to_number'] );
	}

	public function test_send_message_idempotency_reuses_record_and_does_not_call_meta_twice() {
		$service = new WhatsAppService();
		$payload = [
			'phone_number_id'  => 10,
			'to_number'        => '5511888888888',
			'message_type'     => 'text',
			'idempotency_key'  => 'send:agenda:1',
			'payload'          => [ 'text' => [ 'body' => 'Uma vez so' ] ],
		];

		$first = $service->send_message( $payload );
		$second = $service->send_message( $payload );

		$this->assert_same( $first['id'], $second['id'] );
		$this->assert_same( 'wamid.router', $second['wa_message_id'] );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
	}

	public function test_send_message_requires_internal_phone_number_id_for_waba_router_sender_contract() {
		$result = ( new WhatsAppService() )->send_message(
			[
				'to_number'    => '5511999999999',
				'message_type' => 'text',
				'payload'      => [ 'text' => [ 'body' => 'Ola' ] ],
			]
		);

		$this->assert_true( is_wp_error( $result ) );
		$this->assert_same( 'invalid_send_payload', $result->get_error_code() );
		$this->assert_same( 400, $result->get_error_data()['status'] );
		$this->assert_count( 0, $GLOBALS['was_test_http_posts'] );
	}

	public function test_http_200_failed_status_marks_outbound_as_failed() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'id' => 90, 'status' => 'failed', 'meta_message_id' => null ],
		];

		$result = ( new WhatsAppService() )->send_message(
			[
				'phone_number_id' => 10,
				'to_number'       => '5511888888888',
				'message_type'    => 'text',
				'payload'         => [ 'text' => [ 'body' => 'Vai falhar' ] ],
			]
		);

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 'failed', $result['status'] );
		$this->assert_false( $result['success'] );
		$this->assert_null( $result['wa_message_id'] );
	}

	public function test_waba_sync_returns_synced_phone_numbers_for_agenda_master_onboarding() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [
				'data' => [
					[
						'id'                   => '799574893248433',
						'display_phone_number' => '+55 31 99999-9999',
						'verified_name'        => 'Agenda Master',
						'quality_rating'       => 'GREEN',
						'status'               => 'CONNECTED',
					],
				],
			],
		];

		$result = ( new WhatsAppService() )->sync_waba_phone_numbers( 7 );
		$item = $result['synced'][0];

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_true( $result['success'] );
		$this->assert_same( '', $result['error'] );
		$this->assert_same( 7, $result['waba_id'] );
		$this->assert_same( 7, $result['router_waba_id'] );
		$this->assert_same( 'meta-waba-1', $result['meta_waba_id'] );
		$this->assert_same( 1, $result['count'] );
		$this->assert_count( 1, $result['phone_numbers'] );
		$this->assert_same( $item, $result['phone_numbers'][0] );
		$this->assert_same( '799574893248433', $item['meta_phone_number_id'] );
		$this->assert_same( '799574893248433', $item['whatsapp_phone_number_id'] );
		$this->assert_same( '+55 31 99999-9999', $item['display_phone_number'] );
		$this->assert_same( 'CONNECTED', $item['meta_status'] );
		$this->assert_same( $item['id'], $item['router_phone_number_id'] );
		$this->assert_same( $item['id'], $item['phone_number_id'] );
	}

	public function test_phone_status_sync_meta_error_returns_check_error_payload_for_agenda_master() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 400,
			'body' => [
				'error' => [
					'message' => 'Meta retornou erro ao consultar o numero.',
					'code'    => 100,
				],
			],
		];

		$result = ( new WhatsAppService() )->get_phone_status( 10, true );

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 10, $result['id'] );
		$this->assert_same( 10, $result['router_phone_number_id'] );
		$this->assert_same( 7, $result['router_waba_id'] );
		$this->assert_same( 'meta-phone-1', $result['meta_phone_number_id'] );
		$this->assert_same( 'meta-phone-1', $result['whatsapp_phone_number_id'] );
		$this->assert_same( 'check_error', $result['connection_state'] );
		$this->assert_same( 'Erro ao verificar', $result['connection_label'] );
		$this->assert_same( 'Status desconhecido', $result['quality_label'] );
		$this->assert_same( 'gray', $result['quality_color'] );
		$this->assert_same( 0, $result['quality_score'] );
		$this->assert_same( 'Meta retornou erro ao consultar o numero.', $result['last_status_error'] );
		$this->assert_not_null( $result['checked_at'] );
	}

	public function test_phone_status_sync_queues_agenda_master_status_changed_webhook() {
		global $wpdb;
		$wpdb->update(
			TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ),
			[
				'display_phone_number'      => '+55 31 9712-1855',
				'verified_name'             => 'Alex Agenda',
				'quality_rating'            => 'GREEN',
				'meta_status'               => 'CONNECTED',
				'code_verification_status'  => 'NOT_VERIFIED',
				'platform_type'             => 'CLOUD_API',
				'throughput_json'           => wp_json_encode( [ 'level' => 'STANDARD' ] ),
				'last_status_fingerprint'   => 'old-fingerprint',
			],
			[ 'id' => 10 ]
		);
		( new \WAS\Router\RouteRepository() )->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'name'            => 'Agenda status',
				'target_url'      => 'https://agenda.test/whatsapp/webhooks/waba-router/',
				'secret'          => 'route-secret',
				'is_active'       => true,
			]
		);
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => [
					'id'                       => 'meta-phone-1',
					'display_phone_number'     => '+55 31 9712-1855',
					'verified_name'            => 'Alex Alves',
					'quality_rating'           => 'YELLOW',
					'status'                   => 'DISCONNECTED',
					'code_verification_status' => 'NOT_VERIFIED',
					'platform_type'            => 'CLOUD_API',
					'throughput'               => [ 'level' => 'STANDARD' ],
				],
			],
			[
				'code' => 200,
				'body' => [ 'ok' => true ],
			],
		];

		$result = ( new WhatsAppService() )->get_phone_status( 10, true );
		$event = $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ][0];
		$delivered = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$payload = $delivered['payload'];

		$this->assert_same( 'disconnected', $result['connection_state'] );
		$this->assert_same( 'phone_number_status_changed', $event['event_type'] );
		$this->assert_same( 'phone_number_status', $event['message_type'] );
		$this->assert_same( 'phone_number_status_changed', $delivered['event_type'] );
		$this->assert_same( 'phone_number_status', $delivered['message_type'] );
		$this->assert_same( 10, $delivered['phone_number_id'] );
		$this->assert_same( 'phone_number_status_changed', $payload['event_type'] );
		$this->assert_same( 10, $payload['router_phone_number_id'] );
		$this->assert_same( 7, $payload['router_waba_id'] );
		$this->assert_same( 'meta-phone-1', $payload['meta_phone_number_id'] );
		$this->assert_same( 'DISCONNECTED', $payload['meta_status'] );
		$this->assert_same( 'disconnected', $payload['connection_state'] );
		$this->assert_same( 'YELLOW', $payload['quality_rating'] );
		$this->assert_same( 'Atencao', $payload['quality_label'] );
		$this->assert_same( 'yellow', $payload['quality_color'] );
		$this->assert_same( 60, $payload['quality_score'] );
		$this->assert_same( 'CONNECTED', $payload['previous']['meta_status'] );
		$this->assert_same( 'connected', $payload['previous']['connection_state'] );
		$this->assert_same( 'route-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );
	}
}

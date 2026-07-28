<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\REST\WebhookController;
use WAS\Router\RouteRepository;

class WebhookControllerTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;

		TenantContext::set_tenant_id( 1 );
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_apps' ), [
			'id'            => 1,
			'app_id'        => 'meta-app-1',
			'app_secret'    => TokenVault::encrypt( 'meta-app-secret' ),
			'graph_version' => 'v25.0',
			'verify_token'  => 'verify-token',
			'status'        => 'active',
			'is_default'    => 1,
			'created_at'    => current_time( 'mysql', true ),
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [
			'id'         => 5,
			'tenant_id'  => 1,
			'meta_app_id'=> 1,
			'waba_id'    => 'meta-waba-1',
			'name'       => 'WABA',
			'status'     => 'active',
			'created_at' => current_time( 'mysql', true ),
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [
			'id'                  => 10,
			'tenant_id'           => 1,
			'whatsapp_account_id' => 5,
			'phone_number_id'     => 'meta-phone-1',
			'display_phone_number' => '553171183457',
			'status'              => 'active',
			'phone_status'        => 'CONNECTED',
			'created_at'          => current_time( 'mysql', true ),
		] );
		( new RouteRepository() )->create_or_update( [
			'tenant_id'       => 1,
			'phone_number_id' => 10,
			'name'            => 'External app',
			'target_url'      => 'https://external.test/webhook',
			'secret'          => 'route-secret',
			'is_active'       => true,
		] );
	}

	public function test_public_meta_webhook_validates_signature_processes_and_delivers_event() {
		$payload = $this->payload();
		$raw_body = wp_json_encode( $payload );
		$request = new WP_REST_Request( 'POST', '/meta/webhook' );
		$request->set_body( $raw_body );
		$request->set_header( 'X-Hub-Signature-256', 'sha256=' . hash_hmac( 'sha256', $raw_body, 'meta-app-secret' ) );

		$response = ( new WebhookController() )->receive_event( $request );
		$data = $response->get_data();
		$event = null;
		foreach ( $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ] ?? [] as $candidate ) {
			if ( 'message_received' === ( $candidate['event_type'] ?? '' ) ) {
				$event = $candidate;
				break;
			}
		}
		$logs = $GLOBALS['wpdb']->tables[ TableNameResolver::getAuditLogsTable() ] ?? [];
		$messages = $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'messages' ) ] ?? [];

		$this->assert_same( 200, $response->get_status() );
		$this->assert_true( $data['success'] );
		$this->assert_same( 1, (int) $event['tenant_id'] );
		$this->assert_same( 'message_received', $event['event_type'] );
		$this->assert_count( 1, $messages );
		$this->assert_same( 'inbound', $messages[0]['direction'] );
		$this->assert_same( 'Webhook público', $messages[0]['text_body'] );
		$this->assert_same( 'confirmed_by_wa_id', $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'contacts' ) ][0]['phone_status'] );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
		$this->assert_same( 'https://external.test/webhook', $GLOBALS['was_test_http_posts'][0]['url'] );
		$this->assert_same( 'route-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );

		$actions = array_map( fn( $log ) => $log['action'] ?? '', $logs );
		$this->assert_true( in_array( 'WEBHOOK_EVENT', $actions, true ) );
		$this->assert_true( in_array( 'webhook_event_processed', $actions, true ) );
		$this->assert_true( in_array( 'webhook_forwarded', $actions, true ) );
	}

	public function test_public_meta_webhook_rejects_invalid_signature_before_processing() {
		$raw_body = wp_json_encode( $this->payload() );
		$request = new WP_REST_Request( 'POST', '/meta/webhook' );
		$request->set_body( $raw_body );
		$request->set_header( 'X-Hub-Signature-256', 'sha256=' . hash_hmac( 'sha256', $raw_body, 'wrong-secret' ) );

		$response = ( new WebhookController() )->receive_event( $request );
		$data = $response->get_data();

		$this->assert_same( 403, $response->get_status() );
		$this->assert_same( 'Invalid signature', $data['message'] );
		$this->assert_count( 0, $GLOBALS['was_test_http_posts'] );
		$this->assert_count( 0, $GLOBALS['wpdb']->tables[ TableNameResolver::getWebhookEventsTable() ] ?? [] );
	}

	private function payload() {
		return [
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
								'contacts'          => [ [ 'profile' => [ 'name' => 'Cliente' ], 'wa_id' => '5531999000001' ] ],
								'messages'          => [ [ 'id' => 'wamid-controller-test', 'from' => '5531999000001', 'timestamp' => '1710000000', 'type' => 'text', 'text' => [ 'body' => 'Webhook público' ] ] ],
							],
						],
					],
				],
			],
		];
	}
}

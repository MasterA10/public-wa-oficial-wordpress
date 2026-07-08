<?php

use WAS\Core\TableNameResolver;
use WAS\Router\AdminRouterService;
use WAS\Router\OutboxService;
use WAS\Router\RouteRepository;

class OutboxServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 5, 'tenant_id' => 1, 'meta_app_id' => 1, 'waba_id' => 'meta-waba-1', 'name' => 'WABA', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [ 'id' => 10, 'tenant_id' => 1, 'whatsapp_account_id' => 5, 'phone_number_id' => 'meta-phone-1', 'display_phone_number' => '+55 31 90000-0001', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
	}

	public function test_failed_destination_delivery_retries_then_moves_to_dead_letter() {
		$route = $this->create_route();
		$event_id = $this->insert_message_event();
		$delivery_ids = ( new OutboxService() )->enqueue_for_event( $event_id, [ $route ] );
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 500,
				'body' => 'temporary failure',
			],
			[
				'code' => 500,
				'body' => 'still failing',
			],
		];
		$service = new OutboxService();

		$first = $service->process_delivery( $delivery_ids[0] );
		$after_first = $this->delivery_by_id( $delivery_ids[0] );

		$second = $service->process_delivery( $delivery_ids[0] );
		$after_second = $this->delivery_by_id( $delivery_ids[0] );

		$this->assert_false( $first );
		$this->assert_same( 'pending', $after_first['status'] );
		$this->assert_same( 1, (int) $after_first['attempts'] );
		$this->assert_same( 500, (int) $after_first['response_status'] );
		$this->assert_same( 'temporary failure', $after_first['response_body'] );
		$this->assert_same( 'http_500', $after_first['last_error'] );
		$this->assert_not_null( $after_first['next_attempt_at'] );

		$this->assert_false( $second );
		$this->assert_same( 'dead_letter', $after_second['status'] );
		$this->assert_same( 2, (int) $after_second['attempts'] );
		$this->assert_same( 500, (int) $after_second['response_status'] );
		$this->assert_same( 'still failing', $after_second['response_body'] );
		$this->assert_same( 'http_500', $after_second['last_error'] );
		$this->assert_null( $after_second['next_attempt_at'] );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
		$this->assert_same( 'route-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );
		$this->assert_same( 2, $GLOBALS['was_test_http_posts'][0]['args']['timeout'] );
	}

	public function test_admin_retry_delivery_processes_dead_letter_immediately() {
		$route = $this->create_route();
		$event_id = $this->insert_message_event();
		$delivery_ids = ( new OutboxService() )->enqueue_for_event( $event_id, [ $route ] );
		$GLOBALS['wpdb']->update(
			TableNameResolver::getOutboxDeliveriesTable(),
			[
				'status'          => 'dead_letter',
				'attempts'        => 2,
				'next_attempt_at' => null,
				'last_error'      => 'http_500',
			],
			[ 'id' => $delivery_ids[0] ]
		);
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'ok' => true, 'status' => 'debounced' ],
		];

		$delivery = ( new AdminRouterService() )->retry_delivery( $delivery_ids[0] );

		$this->assert_same( 'delivered', $delivery->status );
		$this->assert_same( 3, (int) $delivery->attempts );
		$this->assert_same( 200, (int) $delivery->response_status );
		$this->assert_null( $delivery->last_error );
		$this->assert_not_null( $delivery->delivered_at );
		$this->assert_count( 1, $GLOBALS['was_test_http_posts'] );
		$this->assert_same( 'route-secret', $GLOBALS['was_test_http_posts'][0]['args']['headers']['x-waba-router-secret'] );
	}

	public function test_admin_cancel_delivery_does_not_call_destination() {
		$route = $this->create_route();
		$event_id = $this->insert_message_event();
		$delivery_ids = ( new OutboxService() )->enqueue_for_event( $event_id, [ $route ] );

		$delivery = ( new AdminRouterService() )->cancel_delivery( $delivery_ids[0] );

		$this->assert_same( 'cancelled', $delivery->status );
		$this->assert_null( $delivery->next_attempt_at );
		$this->assert_same( 'cancelled_by_api', $delivery->last_error );
		$this->assert_count( 0, $GLOBALS['was_test_http_posts'] );
	}

	private function create_route() {
		$route_id = ( new RouteRepository() )->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'name'            => 'Agenda webhook',
				'target_url'      => 'https://agenda.test/whatsapp/webhooks/waba-router/',
				'secret'          => 'route-secret',
				'max_retries'     => 2,
				'timeout_ms'      => 1500,
				'is_active'       => true,
			]
		);

		return $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::getRoutesTable() . ' WHERE id = ' . (int) $route_id . ' LIMIT 1' );
	}

	private function insert_message_event() {
		global $wpdb;
		$wpdb->insert(
			TableNameResolver::getWebhookEventsTable(),
			[
				'tenant_id'                    => 1,
				'whatsapp_account_id'          => 5,
				'phone_number_id'              => 'meta-phone-1',
				'whatsapp_phone_number_row_id' => 10,
				'event_type'                   => 'message_received',
				'message_type'                 => 'text',
				'wa_message_id'                => 'wamid-outbox-retry',
				'wa_from'                      => '5531999000001',
				'payload'                      => wp_json_encode( [ 'entry' => [ [ 'id' => 'meta-waba-1' ] ] ] ),
				'normalized_payload'           => wp_json_encode(
					[
						'event_type' => 'message_received',
						'message'    => [ 'text' => [ 'body' => 'Oi' ] ],
					]
				),
				'idempotency_key'              => 'event:outbox-retry',
				'routing_status'               => 'queued',
				'processing_status'            => 'processed',
				'received_at'                  => current_time( 'mysql', true ),
				'created_at'                   => current_time( 'mysql', true ),
			]
		);

		return (int) $wpdb->insert_id;
	}

	private function delivery_by_id( $delivery_id ) {
		foreach ( $GLOBALS['wpdb']->tables[ TableNameResolver::getOutboxDeliveriesTable() ] ?? [] as $delivery ) {
			if ( (int) $delivery['id'] === (int) $delivery_id ) {
				return $delivery;
			}
		}
		return null;
	}
}

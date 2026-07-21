<?php

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;
use WAS\Router\AdminRouterService;
use WAS\Router\OutboxService;
use WAS\Router\RouteRepository;

class OutboxServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_tenant_id( 1 );
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

	public function test_successful_delivery_logs_that_webhook_was_delivered_to_route() {
		$route = $this->create_route();
		$event_id = $this->insert_message_event();
		$delivery_ids = ( new OutboxService() )->enqueue_for_event( $event_id, [ $route ] );
		$GLOBALS['was_test_http_response'] = [
			'code' => 202,
			'body' => 'accepted',
		];

		$result = ( new OutboxService() )->process_delivery( $delivery_ids[0] );
		$logs = $GLOBALS['wpdb']->tables[ TableNameResolver::getAuditLogsTable() ] ?? [];
		$matching_logs = [];

		foreach ( $logs as $log ) {
			if ( 'SYSTEM_INFO' !== ( $log['action'] ?? '' ) ) {
				continue;
			}

			$metadata = json_decode( $log['metadata'] ?? '{}', true );
			if ( 'Webhook entregue para a rota.' === ( $metadata['error_message'] ?? '' ) ) {
				$matching_logs[] = $metadata;
			}
		}

		$this->assert_true( $result );
		$this->assert_count( 1, $matching_logs );
		$context = $matching_logs[0]['context'];
		$this->assert_same( (int) $event_id, (int) $context['event_id'] );
		$this->assert_same( (int) $delivery_ids[0], (int) $context['delivery_id'] );
		$this->assert_same( (int) $route->id, (int) $context['route_id'] );
		$this->assert_same( $route->name, $context['route_name'] );
		$this->assert_same( $route->target_url, $context['target_url'] );
		$this->assert_same( 202, (int) $context['response_status'] );
		$this->assert_same( 'delivered', $context['delivery_status'] );
	}

	public function test_webhook_forwarding_log_has_a_dedicated_event_for_success_and_failure() {
		$success_route = $this->create_route();
		$failure_route_id = ( new RouteRepository() )->create_or_update(
			[
				'tenant_id'       => 1,
				'phone_number_id' => 10,
				'name'            => 'Agenda webhook failure',
				'target_url'      => 'https://failure.test/webhook',
				'secret'          => 'failure-secret',
				'max_retries'     => 2,
				'is_active'       => true,
			]
		);
		$failure_route = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::getRoutesTable() . ' WHERE id = ' . (int) $failure_route_id . ' LIMIT 1' );
		$event_id = $this->insert_message_event();
		$delivery_ids = ( new OutboxService() )->enqueue_for_event( $event_id, [ $success_route, $failure_route ] );
		$GLOBALS['was_test_http_response_queue'] = [
			[
				'code' => 200,
				'body' => 'ok',
			],
			[
				'code' => 503,
				'body' => 'destination unavailable',
			],
		];

		$service = new OutboxService();
		$service->process_delivery( $delivery_ids[0] );
		$service->process_delivery( $delivery_ids[1] );

		$logs = $GLOBALS['wpdb']->tables[ TableNameResolver::getAuditLogsTable() ] ?? [];
		$forwarding_logs = [];
		foreach ( $logs as $log ) {
			if ( 'webhook_forwarded' !== ( $log['action'] ?? '' ) ) {
				continue;
			}
			$forwarding_logs[] = [
				'entity_id' => (int) $log['entity_id'],
				'metadata'  => json_decode( $log['metadata'] ?? '{}', true ),
			];
		}

		$this->assert_count( 2, $forwarding_logs );
		$by_status = [];
		foreach ( $forwarding_logs as $log ) {
			$by_status[ $log['metadata']['status'] ] = $log;
		}
		$this->assert_array_has_key( 'delivered', $by_status );
		$this->assert_array_has_key( 'failed', $by_status );
		$this->assert_same( (int) $success_route->id, $by_status['delivered']['entity_id'] );
		$this->assert_same( (int) $failure_route->id, $by_status['failed']['entity_id'] );
		$this->assert_same( 200, (int) $by_status['delivered']['metadata']['response_status'] );
		$this->assert_same( 503, (int) $by_status['failed']['metadata']['response_status'] );
		$this->assert_same( 'http_503', $by_status['failed']['metadata']['error'] );
		$this->assert_same( $failure_route->target_url, $by_status['failed']['metadata']['target_url'] );
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

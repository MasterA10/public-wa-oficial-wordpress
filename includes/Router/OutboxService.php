<?php

namespace WAS\Router;

use WAS\Core\SystemLogger;
use WAS\Core\TableNameResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OutboxService {

	public function enqueue_for_event( $event_id, array $routes ) {
		global $wpdb;
		$table = TableNameResolver::getOutboxDeliveriesTable();
		$created = [];

		foreach ( $routes as $route ) {
			$wpdb->insert(
				$table,
				[
					'event_id'        => (int) $event_id,
					'route_id'        => (int) $route->id,
					'status'          => 'pending',
					'attempts'        => 0,
					'next_attempt_at' => current_time( 'mysql', true ),
					'created_at'      => current_time( 'mysql', true ),
				]
			);

			if ( $wpdb->insert_id ) {
				$created[] = (int) $wpdb->insert_id;
			}
		}

		return $created;
	}

	public function process_due( $limit = 10 ) {
		global $wpdb;
		$table = TableNameResolver::getOutboxDeliveriesTable();
		$now = current_time( 'mysql', true );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= %s) ORDER BY created_at ASC LIMIT %d",
				$now,
				(int) $limit
			)
		);

		foreach ( $rows as $row ) {
			$this->process_delivery( (int) $row->id );
		}

		return count( $rows );
	}

	public function process_delivery( $delivery_id ) {
		global $wpdb;

		$deliveries_table = TableNameResolver::getOutboxDeliveriesTable();
		$events_table = TableNameResolver::getWebhookEventsTable();
		$routes_table = TableNameResolver::getRoutesTable();

		$delivery = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $deliveries_table WHERE id = %d LIMIT 1",
				(int) $delivery_id
			)
		);

		if ( ! $delivery || 'pending' !== $delivery->status ) {
			return false;
		}

		$route = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $routes_table WHERE id = %d LIMIT 1",
				(int) $delivery->route_id
			)
		);

		$event = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $events_table WHERE id = %d LIMIT 1",
				(int) $delivery->event_id
			)
		);

		if ( ! $route || ! $event ) {
			$wpdb->update(
				$deliveries_table,
				[
					'status'     => 'dead_letter',
					'last_error' => 'route_or_event_not_found',
				],
				[ 'id' => (int) $delivery->id ]
			);
			return false;
		}

		$attempts = (int) $delivery->attempts + 1;
		$wpdb->update(
			$deliveries_table,
			[
				'status'   => 'processing',
				'attempts' => $attempts,
			],
			[ 'id' => (int) $delivery->id ]
		);

		$payload = $this->build_payload( $delivery, $event );
		$secret = SecretVault::decrypt( $route->secret_encrypted );
		$headers = [ 'Content-Type' => 'application/json' ];
		if ( $secret ) {
			$headers['x-waba-router-secret'] = $secret;
		}

		$response = wp_remote_post(
			$route->target_url,
			[
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
				'timeout' => max( 1, (int) ceil( (int) $route->timeout_ms / 1000 ) ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $this->mark_retry_or_dead_letter( $delivery, $route, $attempts, $response->get_error_message() );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $status_code >= 200 && $status_code < 300 ) {
			$wpdb->update(
				$deliveries_table,
				[
					'status'          => 'delivered',
					'response_status' => $status_code,
					'response_body'   => substr( $body, 0, 65535 ),
					'delivered_at'    => current_time( 'mysql', true ),
					'last_error'      => null,
				],
				[ 'id' => (int) $delivery->id ]
			);

			SystemLogger::logInfo(
				'Webhook entregue para a rota.',
				[
					'event_id'        => (int) $event->id,
					'delivery_id'     => (int) $delivery->id,
					'route_id'        => (int) $route->id,
					'route_name'      => (string) $route->name,
					'target_url'      => (string) $route->target_url,
					'event_type'      => (string) $event->event_type,
					'message_type'    => (string) $event->message_type,
					'wa_message_id'   => (string) $event->wa_message_id,
					'phone_number_id' => $event->whatsapp_phone_number_row_id ? (int) $event->whatsapp_phone_number_row_id : null,
					'response_status' => $status_code,
					'delivery_status' => 'delivered',
				]
			);
			return true;
		}

		return $this->mark_retry_or_dead_letter( $delivery, $route, $attempts, 'http_' . $status_code, $status_code, $body );
	}

	private function build_payload( $delivery, $event ) {
		return [
			'event_id'        => (int) $event->id,
			'delivery_id'     => (int) $delivery->id,
			'event_type'      => $event->event_type,
			'message_type'    => $event->message_type,
			'wa_message_id'   => $event->wa_message_id,
			'wa_from'         => $event->wa_from,
			'phone_number_id' => $event->whatsapp_phone_number_row_id ? (int) $event->whatsapp_phone_number_row_id : null,
			'route_id'        => (int) $delivery->route_id,
			'payload'         => json_decode( $event->normalized_payload ?: '{}', true ),
			'raw_payload'     => json_decode( $event->payload ?: '{}', true ),
		];
	}

	private function mark_retry_or_dead_letter( $delivery, $route, $attempts, $error, $status_code = null, $body = null ) {
		global $wpdb;

		$table = TableNameResolver::getOutboxDeliveriesTable();
		$max_retries = max( 0, (int) $route->max_retries );
		$terminal = $attempts >= $max_retries;
		$delay = min( 3600, max( 60, ( 2 ** max( 0, $attempts - 1 ) ) * 60 ) );

		$wpdb->update(
			$table,
			[
				'status'          => $terminal ? 'dead_letter' : 'pending',
				'next_attempt_at' => $terminal ? null : gmdate( 'Y-m-d H:i:s', time() + $delay ),
				'last_error'      => substr( (string) $error, 0, 1000 ),
				'response_status' => $status_code,
				'response_body'   => null === $body ? null : substr( (string) $body, 0, 65535 ),
			],
			[ 'id' => (int) $delivery->id ]
		);

		return false;
	}
}

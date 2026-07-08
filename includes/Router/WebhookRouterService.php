<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebhookRouterService {

	public function process_meta_payload( array $payload, $raw_body = '', $signature_valid = false ) {
		$items = $this->extract_event_items( $payload );
		$results = [];

		foreach ( $items as $item ) {
			$results[] = $this->persist_and_route_event( $payload, $item, (bool) $signature_valid );
		}

		return [
			'success' => true,
			'events'  => $results,
		];
	}

	public function record_synthetic_event( array $item ) {
		$payload = [
			'object' => 'whatsapp_business_account',
			'entry'  => [
				[
					'id'      => $item['waba_id'] ?? null,
					'changes' => [
						[
							'field' => $item['event_type'] ?? 'synthetic',
							'value' => $item['normalized_payload'] ?? [],
						],
					],
				],
			],
		];

		return $this->persist_and_route_event( $payload, $item, true );
	}

	private function extract_event_items( array $payload ) {
		$items = [];
		$entries = $payload['entry'] ?? [];

		foreach ( $entries as $entry_index => $entry ) {
			$meta_waba_id = isset( $entry['id'] ) ? (string) $entry['id'] : null;
			$changes = $entry['changes'] ?? [];

			foreach ( $changes as $change_index => $change ) {
				$field = $change['field'] ?? '';
				$value = $change['value'] ?? [];
				$metadata = $value['metadata'] ?? [];
				$meta_phone_number_id = isset( $metadata['phone_number_id'] ) ? (string) $metadata['phone_number_id'] : null;
				if ( ! $meta_phone_number_id && isset( $value['phone_number_id'] ) ) {
					$meta_phone_number_id = (string) $value['phone_number_id'];
				}
				$change_had_items = false;

				foreach ( $value['messages'] ?? [] as $message_index => $message ) {
					$change_had_items = true;
					$items[] = [
						'event_type'           => 'message_received',
						'message_type'         => $message['type'] ?? 'unknown',
						'wa_message_id'        => $message['id'] ?? null,
						'wa_from'              => $message['from'] ?? null,
						'meta_waba_id'         => $meta_waba_id,
						'meta_phone_number_id' => $meta_phone_number_id,
						'normalized_payload'   => [
							'field'    => $field,
							'metadata' => $metadata,
							'contact'  => $value['contacts'][ $message_index ] ?? $value['contacts'][0] ?? null,
							'message'  => $message,
							'payload'  => [
								'messaging_product' => $value['messaging_product'] ?? 'whatsapp',
								'metadata'          => $metadata,
								'contacts'          => $value['contacts'] ?? [],
								'messages'          => [ $message ],
							],
						],
						'item_index'           => "$entry_index:$change_index:message:$message_index",
					];
				}

				foreach ( $value['statuses'] ?? [] as $status_index => $status ) {
					$change_had_items = true;
					$items[] = [
						'event_type'           => 'message_status',
						'message_type'         => $status['status'] ?? 'unknown',
						'wa_message_id'        => $status['id'] ?? null,
						'wa_from'              => $status['recipient_id'] ?? null,
						'meta_waba_id'         => $meta_waba_id,
						'meta_phone_number_id' => $meta_phone_number_id,
						'normalized_payload'   => [
							'field'          => $field,
							'metadata'       => $metadata,
							'status'         => $status['status'] ?? 'unknown',
							'message_status' => $status['status'] ?? 'unknown',
							'wa_message_id'  => $status['id'] ?? null,
							'recipient_id'   => $status['recipient_id'] ?? null,
							'timestamp'      => $status['timestamp'] ?? null,
							'errors'         => $status['errors'] ?? [],
							'status_payload' => $status,
							'payload'        => $status,
						],
						'item_index'           => "$entry_index:$change_index:status:$status_index",
					];
				}

				$echoes = $value['message_echoes'] ?? $value['smb_message_echoes'] ?? $value['echoes'] ?? [];
				foreach ( $echoes as $echo_index => $echo ) {
					$change_had_items = true;
					$items[] = [
						'event_type'           => 'message_echo',
						'message_type'         => $echo['type'] ?? 'text',
						'wa_message_id'        => $echo['id'] ?? null,
						'wa_from'              => $echo['from'] ?? null,
						'meta_waba_id'         => $meta_waba_id,
						'meta_phone_number_id' => $meta_phone_number_id,
						'normalized_payload'   => [
							'field'    => $field,
							'metadata' => $metadata,
							'contact'  => $value['contacts'][ $echo_index ] ?? $value['contacts'][0] ?? null,
							'message'  => $echo,
							'payload'  => array_merge(
								$echo,
								[
									'messaging_product' => $value['messaging_product'] ?? 'whatsapp',
									'metadata'          => $metadata,
									'contacts'          => $value['contacts'] ?? [],
									'message_echoes'    => [ $echo ],
								]
							),
							'wa_to'    => $echo['to'] ?? $echo['recipient'] ?? null,
							'wa_from'  => $echo['from'] ?? null,
							'to_number'=> $echo['to'] ?? $echo['recipient'] ?? null,
							'from_number' => $echo['from'] ?? null,
						],
						'item_index'           => "$entry_index:$change_index:echo:$echo_index",
					];
				}

				if ( 'message_template_status_update' === $field ) {
					$change_had_items = true;
					$status = strtoupper( (string) ( $value['event'] ?? $value['status'] ?? 'UNKNOWN' ) );
					$template_id = isset( $value['message_template_id'] ) ? (string) $value['message_template_id'] : null;
					$template_name = $value['message_template_name'] ?? $value['name'] ?? null;
					$template_language = $value['message_template_language'] ?? $value['language'] ?? null;
					$template_category = $value['message_template_category'] ?? $value['category'] ?? null;
					$items[] = [
						'event_type'           => 'template_status_updated',
						'message_type'         => $status,
						'wa_message_id'        => $template_id,
						'wa_from'              => null,
						'meta_waba_id'         => $meta_waba_id,
						'meta_phone_number_id' => $meta_phone_number_id,
						'normalized_payload'   => [
							'event_type'        => 'template_status_updated',
							'meta_event_type'   => $field,
							'status'            => $status,
							'template_status'   => $status,
							'template_id'       => $template_id,
							'meta_template_id'  => $template_id,
							'template_name'     => $template_name,
							'name'              => $template_name,
							'template_language' => $template_language,
							'template_category' => $template_category,
							'meta_waba_id'      => $meta_waba_id,
							'reason'            => $value['reason'] ?? null,
							'raw_template'      => $value,
						],
						'item_index'           => "$entry_index:$change_index:template",
					];
				}

				if ( 'template_category_update' === $field ) {
					$change_had_items = true;
					$template_id = isset( $value['message_template_id'] ) ? (string) $value['message_template_id'] : null;
					$template_name = $value['message_template_name'] ?? $value['name'] ?? null;
					$template_language = $value['message_template_language'] ?? $value['language'] ?? null;
					$new_category = $value['new_category'] ?? $value['message_template_category'] ?? $value['category'] ?? null;
					$items[] = [
						'event_type'           => 'template_category_updated',
						'message_type'         => $new_category,
						'wa_message_id'        => $template_id,
						'wa_from'              => null,
						'meta_waba_id'         => $meta_waba_id,
						'meta_phone_number_id' => $meta_phone_number_id,
						'normalized_payload'   => [
							'event_type'                 => 'template_category_updated',
							'meta_event_type'            => $field,
							'template_id'                => $template_id,
							'meta_template_id'           => $template_id,
							'template_name'              => $template_name,
							'name'                       => $template_name,
							'template_language'          => $template_language,
							'template_category'          => $new_category,
							'previous_template_category' => $value['previous_category'] ?? null,
							'meta_waba_id'               => $meta_waba_id,
							'raw_template'               => $value,
						],
						'item_index'           => "$entry_index:$change_index:template-category",
					];
				}

				if ( 'account_update' === $field ) {
					$change_had_items = true;
					$waba_info = $value['waba_info'] ?? [];
					$account_waba_id = isset( $waba_info['waba_id'] ) ? (string) $waba_info['waba_id'] : $meta_waba_id;
					$items[] = [
						'event_type'           => 'account_update',
						'message_type'         => 'account_update',
						'wa_message_id'        => $account_waba_id,
						'wa_from'              => null,
						'meta_waba_id'         => $account_waba_id,
						'meta_phone_number_id' => $meta_phone_number_id,
						'normalized_payload'   => [
							'event_type'        => 'account_update',
							'meta_event_type'   => $field,
							'meta_waba_id'      => $account_waba_id,
							'owner_business_id' => $waba_info['owner_business_id'] ?? null,
							'partner_app_id'    => $waba_info['partner_app_id'] ?? null,
							'waba_info'         => $waba_info,
							'raw_account_update'=> $value,
						],
						'item_index'           => "$entry_index:$change_index:account-update",
					];
				}

				if ( in_array( $field, [ 'phone_number_quality_update', 'phone_number_name_update', 'security' ], true ) ) {
					$change_had_items = true;
					$operational_message_type = $value['event'] ?? $value['current_quality_update_event'] ?? $value['name_status'] ?? $field;
					$items[] = [
						'event_type'           => $field,
						'message_type'         => $operational_message_type,
						'wa_message_id'        => $meta_phone_number_id,
						'wa_from'              => null,
						'meta_waba_id'         => $meta_waba_id,
						'meta_phone_number_id' => $meta_phone_number_id,
						'normalized_payload'   => [
							'event_type'            => $field,
							'meta_event_type'       => $field,
							'phone_number_id'       => $meta_phone_number_id,
							'meta_phone_number_id'  => $meta_phone_number_id,
							'display_phone_number'  => $value['display_phone_number'] ?? ( $metadata['display_phone_number'] ?? null ),
							'event'                 => $value['event'] ?? null,
							'quality_update_event'  => $value['current_quality_update_event'] ?? null,
							'name_status'           => $value['name_status'] ?? null,
							'decision'              => $value['decision'] ?? null,
							'raw_operational'       => $value,
						],
						'item_index'           => "$entry_index:$change_index:operational",
					];
				}

				if ( ! $change_had_items ) {
					$items[] = [
						'event_type'           => 'unknown',
						'message_type'         => $field ?: null,
						'wa_message_id'        => null,
						'wa_from'              => null,
						'meta_waba_id'         => $meta_waba_id,
						'meta_phone_number_id' => $meta_phone_number_id,
						'normalized_payload'   => [
							'field' => $field,
							'value' => $value,
						],
						'item_index'           => "$entry_index:$change_index:unknown",
					];
				}
			}
		}

		if ( empty( $items ) ) {
			$items[] = [
				'event_type'           => 'unknown',
				'message_type'         => null,
				'wa_message_id'        => null,
				'wa_from'              => null,
				'meta_waba_id'         => null,
				'meta_phone_number_id' => null,
				'normalized_payload'   => $payload,
				'item_index'           => 'payload',
			];
		}

		return $items;
	}

	private function persist_and_route_event( array $raw_payload, array $item, $signature_valid ) {
		global $wpdb;

		$events_table = TableNameResolver::getWebhookEventsTable();
		$phone = $this->find_phone_by_meta_id( $item['meta_phone_number_id'] ?? null );
		$account = $phone ? $this->find_account_by_id( (int) $phone->whatsapp_account_id ) : $this->find_account_by_waba_id( $item['meta_waba_id'] ?? null );
		$tenant_id = $phone->tenant_id ?? $account->tenant_id ?? null;
		$idempotency_key = $this->make_idempotency_key( $item, $raw_payload );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $events_table WHERE idempotency_key = %s LIMIT 1",
				$idempotency_key
			)
		);

		if ( $existing_id ) {
			return [
				'event_id'       => (int) $existing_id,
				'routing_status' => 'duplicate',
				'deliveries'     => [],
			];
		}

		$template_context = $this->apply_template_context( $item, $account, $phone );
		if ( $template_context ) {
			$item = $template_context['item'];
			$phone = $template_context['phone'] ?: $phone;
			$account = $template_context['account'] ?: $account;
			$tenant_id = $phone->tenant_id ?? $account->tenant_id ?? $tenant_id;
		}

		$item = $this->enrich_media_item( $item, $phone, $account );

		$routes = $this->routes_for_item( $item, $tenant_id, $phone, $account );
		$routes = array_values(
			array_filter(
				$routes,
				function ( $route ) use ( $item ) {
					return $this->route_matches_item( $route, $item );
				}
			)
		);

		$routing_status = empty( $routes ) ? 'unrouted' : 'queued';
		$routing_note = empty( $routes ) ? 'no_active_route_for_event' : null;

		$wpdb->insert(
			$events_table,
			[
				'tenant_id'                    => $tenant_id,
				'waba_id'                      => $item['meta_waba_id'] ?? null,
				'phone_number_id'              => $item['meta_phone_number_id'] ?? null,
				'whatsapp_account_id'          => $account->id ?? null,
				'whatsapp_phone_number_row_id' => $phone->id ?? null,
				'event_type'                   => $item['event_type'] ?? 'unknown',
				'message_type'                 => $item['message_type'] ?? null,
				'wa_message_id'                => $item['wa_message_id'] ?? null,
				'wa_from'                      => $item['wa_from'] ?? null,
				'payload'                      => wp_json_encode( $raw_payload ),
				'normalized_payload'           => wp_json_encode( $item['normalized_payload'] ?? [] ),
				'processing_status'            => 'pending',
				'signature_valid'              => $signature_valid ? 1 : 0,
				'idempotency_key'              => $idempotency_key,
				'routing_status'               => $routing_status,
				'routing_note'                 => $routing_note,
				'received_at'                  => current_time( 'mysql', true ),
			]
		);

		$event_id = (int) $wpdb->insert_id;
		$deliveries = [];

		if ( $event_id && 'account_update' === ( $item['event_type'] ?? '' ) ) {
			$queued = $this->queue_onboarding_from_account_update( $event_id, $item, $account );
			$wpdb->update(
				$events_table,
				[
					'processing_status' => 'processed',
					'processed_at'      => current_time( 'mysql', true ),
				],
				[ 'id' => $event_id ]
			);
			return [
				'event_id'       => $event_id,
				'routing_status' => $queued['routing_status'],
				'deliveries'     => [],
			];
		}

		if ( $event_id && $routes ) {
			$outbox = new OutboxService();
			$deliveries = $outbox->enqueue_for_event( $event_id, $routes );
			$outbox->process_due( count( $deliveries ) ?: 10 );
		}

		$wpdb->update(
			$events_table,
			[
				'processing_status' => 'processed',
				'processed_at'      => current_time( 'mysql', true ),
			],
			[ 'id' => $event_id ]
		);

		return [
			'event_id'       => $event_id,
			'routing_status' => $routing_status,
			'deliveries'     => $deliveries,
		];
	}

	private function queue_onboarding_from_account_update( $event_id, array $item, $account ) {
		global $wpdb;

		$payload = $item['normalized_payload'] ?? [];
		$meta_waba_id = (string) ( $payload['meta_waba_id'] ?? ( $payload['waba_info']['waba_id'] ?? '' ) );
		$owner_business_id = $payload['owner_business_id'] ?? ( $payload['waba_info']['owner_business_id'] ?? null );
		$partner_app_id = $payload['partner_app_id'] ?? ( $payload['waba_info']['partner_app_id'] ?? null );
		$registrations = $this->find_account_update_registrations( $meta_waba_id, $account, $partner_app_id );
		$events_table = TableNameResolver::getWebhookEventsTable();

		if ( ! $registrations ) {
			$wpdb->update(
				$events_table,
				[
					'routing_status' => 'stored',
					'routing_note'   => 'account_update_no_matching_onboarding_registration',
				],
				[ 'id' => (int) $event_id ]
			);
			return [ 'routing_status' => 'stored', 'job_ids' => [] ];
		}

		$job_ids = [];
		$service = new OnboardingService();
		foreach ( $registrations as $registration ) {
			$wpdb->update(
				TableNameResolver::getOnboardingRegistrationsTable(),
				[
					'meta_waba_id'      => $meta_waba_id,
					'owner_business_id' => $owner_business_id ?: ( $registration->owner_business_id ?? null ),
					'business_id'       => $owner_business_id ?: ( $registration->business_id ?? null ),
					'updated_at'        => current_time( 'mysql', true ),
				],
				[ 'id' => (int) $registration->id ]
			);
			$job_ids[] = $service->enqueue_reconciliation_job( (int) $registration->id );
		}

		$job_ids = array_values( array_unique( array_filter( array_map( 'intval', $job_ids ) ) ) );
		$wpdb->update(
			$events_table,
			[
				'routing_status' => 'onboarding_queued',
				'routing_note'   => 'account_update_onboarding_reconciliation_job_ids=' . implode( ',', $job_ids ),
			],
			[ 'id' => (int) $event_id ]
		);

		return [ 'routing_status' => 'onboarding_queued', 'job_ids' => $job_ids ];
	}

	private function find_account_update_registrations( $meta_waba_id, $account, $partner_app_id = null ) {
		if ( ! $meta_waba_id ) {
			return [];
		}

		global $wpdb;
		$table = TableNameResolver::getOnboardingRegistrationsTable();
		$rows = $wpdb->get_results( "SELECT * FROM $table WHERE status = 'onboarding' ORDER BY created_at DESC LIMIT 500" );

		$matches = array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $meta_waba_id ) {
					return (string) ( $row->meta_waba_id ?? '' ) === (string) $meta_waba_id;
				}
			)
		);
		if ( $matches ) {
			return $matches;
		}

		if ( $account ) {
			$matches = array_values(
				array_filter(
					$rows,
					function ( $row ) use ( $account, $meta_waba_id ) {
						$row_waba = (string) ( $row->meta_waba_id ?? '' );
						return (int) ( $row->tenant_id ?? 0 ) === (int) $account->tenant_id
							&& (int) ( $row->meta_app_id ?? 0 ) === (int) $account->meta_app_id
							&& ( '' === $row_waba || (string) $meta_waba_id === $row_waba );
					}
				)
			);
			if ( $matches ) {
				return $matches;
			}
		}

		if ( $partner_app_id ) {
			$meta_app = $this->find_meta_app_by_app_id( (string) $partner_app_id );
			if ( $meta_app ) {
				return array_values(
					array_filter(
						$rows,
						function ( $row ) use ( $meta_app, $meta_waba_id ) {
							return (int) ( $row->meta_app_id ?? 0 ) === (int) $meta_app->id
								&& (string) ( $row->meta_waba_id ?? '' ) === (string) $meta_waba_id;
						}
					)
				);
			}
		}

		return [];
	}

	private function routes_for_item( array $item, $tenant_id, $phone, $account ) {
		global $wpdb;

		$repository = new RouteRepository();
		if ( $phone ) {
			return $repository->active_for_phone( (int) $phone->id, (int) $phone->tenant_id );
		}

		if ( ! $account ) {
			return [];
		}

		$phones_table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$phones = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM $phones_table WHERE whatsapp_account_id = %d AND tenant_id = %d",
				(int) $account->id,
				(int) $account->tenant_id
			)
		);

		$routes = [];
		foreach ( $phones as $phone_row ) {
			$routes = array_merge( $routes, $repository->active_for_phone( (int) $phone_row->id, (int) $account->tenant_id ) );
		}

		return $routes;
	}

	private function apply_template_context( array $item, $account, $phone ) {
		if ( empty( $item['event_type'] ) || ! in_array( $item['event_type'], [ 'template_status_updated', 'template_category_updated' ], true ) ) {
			return null;
		}

		$template = $this->find_template_for_event( $account, $item );
		if ( ! $template ) {
			return [
				'item'    => $item,
				'phone'   => $phone,
				'account' => $account,
			];
		}

		$resolved_phone = $phone;
		if ( ! $resolved_phone && ! empty( $template->router_phone_number_id ) ) {
			$resolved_phone = $this->find_phone_by_id( (int) $template->router_phone_number_id );
		}
		$resolved_account = $account;
		if ( ! $resolved_account && ! empty( $template->whatsapp_account_id ) ) {
			$resolved_account = $this->find_account_by_id( (int) $template->whatsapp_account_id );
		}

		$item['meta_phone_number_id'] = $item['meta_phone_number_id'] ?? ( $resolved_phone->phone_number_id ?? null );
		$item['normalized_payload'] = array_merge(
			$item['normalized_payload'] ?? [],
			[
				'template_id'            => (int) $template->id,
				'router_template_id'     => (int) $template->id,
				'router_waba_id'         => (int) $template->whatsapp_account_id,
				'router_phone_number_id' => ! empty( $template->router_phone_number_id ) ? (int) $template->router_phone_number_id : null,
				'meta_template_id'       => $item['normalized_payload']['meta_template_id'] ?? $template->meta_template_id ?? null,
				'meta_waba_id'           => $template->waba_id,
				'template_name'          => $item['normalized_payload']['template_name'] ?? $template->name,
				'template_language'      => $item['normalized_payload']['template_language'] ?? $template->language,
				'template_category'      => $item['normalized_payload']['template_category'] ?? $template->category,
			]
		);

		$this->update_template_from_event( $template, $item );

		return [
			'item'    => $item,
			'phone'   => $resolved_phone,
			'account' => $resolved_account,
		];
	}

	private function find_template_for_event( $account, array $item ) {
		if ( ! $account ) {
			return null;
		}

		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		$payload = $item['normalized_payload'] ?? [];
		$meta_template_id = isset( $payload['meta_template_id'] ) ? (string) $payload['meta_template_id'] : '';
		$name = isset( $payload['template_name'] ) ? (string) $payload['template_name'] : '';
		$language = isset( $payload['template_language'] ) ? (string) $payload['template_language'] : '';

		$templates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE whatsapp_account_id = %d AND deleted_at IS NULL ORDER BY id DESC LIMIT 500",
				(int) $account->id
			)
		);

		foreach ( $templates as $template ) {
			if ( $meta_template_id && (string) $template->meta_template_id === $meta_template_id ) {
				return $template;
			}
		}

		foreach ( $templates as $template ) {
			if ( $name && (string) $template->name === $name && ( ! $language || (string) $template->language === $language ) ) {
				return $template;
			}
		}

		return null;
	}

	private function update_template_from_event( $template, array $item ) {
		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		$payload = $item['normalized_payload'] ?? [];
		$data = [ 'updated_at' => current_time( 'mysql', true ) ];

		if ( 'template_status_updated' === ( $item['event_type'] ?? '' ) ) {
			$status = strtoupper( (string) ( $payload['status'] ?? $payload['template_status'] ?? 'UNKNOWN' ) );
			$data['status'] = $status;
			$data['last_status_check_at'] = current_time( 'mysql', true );
			$data['last_status_error'] = null;
			if ( ! empty( $payload['template_category'] ) ) {
				$data['category'] = sanitize_text_field( $payload['template_category'] );
			}
			if ( ! empty( $payload['meta_template_id'] ) ) {
				$data['meta_template_id'] = sanitize_text_field( $payload['meta_template_id'] );
			}
			if ( 'APPROVED' === $status ) {
				$data['approved_at'] = ! empty( $template->approved_at ) ? $template->approved_at : current_time( 'mysql', true );
				$data['approved_notified_at'] = ! empty( $template->approved_notified_at ) ? $template->approved_notified_at : current_time( 'mysql', true );
				$item['normalized_payload']['approved_at'] = $data['approved_at'];
			}
			if ( in_array( $status, [ 'REJECTED', 'FAILED' ], true ) ) {
				$data['rejected_at'] = current_time( 'mysql', true );
				$data['rejection_reason'] = sanitize_text_field( $payload['reason'] ?? $template->rejection_reason ?? '' );
			}
		}

		if ( 'template_category_updated' === ( $item['event_type'] ?? '' ) && ! empty( $payload['template_category'] ) ) {
			$data['category'] = sanitize_text_field( $payload['template_category'] );
		}

		if ( count( $data ) > 1 ) {
			$wpdb->update( $table, $data, [ 'id' => (int) $template->id ] );
		}
	}

	private function enrich_media_item( array $item, $phone, $account ) {
		$media_types = [ 'audio', 'document', 'image', 'sticker', 'video' ];
		$message_type = $item['message_type'] ?? '';
		if ( ! in_array( $message_type, $media_types, true ) ) {
			return $item;
		}

		$payload = $item['normalized_payload'] ?? [];
		$message = $payload['message'] ?? $payload['payload'] ?? [];
		if ( ! is_array( $message ) ) {
			return $item;
		}

		$media = $message[ $message_type ] ?? null;
		if ( ! is_array( $media ) || empty( $media['id'] ) ) {
			return $item;
		}

		$media = array_merge(
			[
				'type'       => $message_type,
				'url_status' => 'pending',
			],
			$media
		);

		if ( ! $phone || ! $account ) {
			$media['url_status'] = 'missing_phone_context';
			return $this->attach_media_payload( $item, $media, $message_type );
		}

		$token = ( new TokenService() )->get_active_token( (int) $phone->tenant_id, (int) $account->id );
		if ( ! $token ) {
			$media['url_status'] = 'missing_access_token';
			return $this->attach_media_payload( $item, $media, $message_type );
		}

		$result = ( new MetaApiClient() )->get( 'media.get', [ 'media_id' => $media['id'] ], [], $token );
		if ( empty( $result['success'] ) || empty( $result['url'] ) ) {
			$media['url_status'] = 'resolve_failed';
			$media['url_error'] = $result['error'] ?? 'missing_media_url';
			return $this->attach_media_payload( $item, $media, $message_type );
		}

		$media = array_merge(
			$media,
			[
				'meta_url'                        => $result['url'],
				'meta_download_url'               => $result['url'],
				'meta_url_status'                 => 'resolved',
				'meta_url_requires_authorization' => true,
				'url'                             => $result['url'],
				'download_url'                    => $result['url'],
				'url_status'                      => 'resolved',
				'url_requires_authorization'      => true,
				'url_expires_in_seconds'          => 300,
				'storage_status'                  => 'not_enabled',
				'file_size'                       => $result['file_size'] ?? null,
				'resolved_mime_type'              => $result['mime_type'] ?? null,
				'resolved_sha256'                 => $result['sha256'] ?? null,
				'messaging_product'               => $result['messaging_product'] ?? null,
			]
		);

		if ( (bool) get_option( 'was_router_media_storage_enabled', false ) ) {
			$media = $this->store_media_locally( $media, $token, $phone );
		}

		return $this->attach_media_payload( $item, $media, $message_type );
	}

	private function store_media_locally( array $media, $token, $phone ) {
		if ( empty( $media['meta_download_url'] ) || ! function_exists( 'wp_upload_bits' ) ) {
			$media['storage_status'] = 'storage_unavailable';
			return $media;
		}

		$response = wp_remote_get(
			$media['meta_download_url'],
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
				'timeout' => 60,
			]
		);
		if ( is_wp_error( $response ) ) {
			$media['storage_status'] = 'download_failed';
			$media['storage_error'] = $response->get_error_message();
			return $media;
		}

		$content = wp_remote_retrieve_body( $response );
		$filename = sanitize_file_name( $this->media_filename( $media, $phone ) );
		$upload = wp_upload_bits( $filename, null, $content );
		if ( ! empty( $upload['error'] ) ) {
			$media['storage_status'] = 'upload_failed';
			$media['storage_error'] = $upload['error'];
			return $media;
		}

		$media['url'] = $upload['url'] ?? $media['url'];
		$media['download_url'] = $upload['url'] ?? $media['download_url'];
		$media['url_status'] = 'stored';
		$media['url_requires_authorization'] = false;
		$media['storage_status'] = 'stored';
		$media['storage_backend'] = 'wordpress';
		$media['storage_path'] = $upload['file'] ?? null;
		$media['file_size'] = strlen( (string) $content );
		$media['content_sha256'] = hash( 'sha256', (string) $content );

		return $media;
	}

	private function attach_media_payload( array $item, array $media, $message_type ) {
		$item['normalized_payload']['media'] = $media;
		if ( isset( $item['normalized_payload']['message'][ $message_type ] ) ) {
			$item['normalized_payload']['message'][ $message_type ] = $media;
		}
		if ( isset( $item['normalized_payload']['payload']['messages'][0][ $message_type ] ) ) {
			$item['normalized_payload']['payload']['messages'][0][ $message_type ] = $media;
		}
		if ( isset( $item['normalized_payload']['payload']['message_echoes'][0][ $message_type ] ) ) {
			$item['normalized_payload']['payload']['message_echoes'][0][ $message_type ] = $media;
		}
		if ( isset( $item['normalized_payload']['payload'][ $message_type ] ) ) {
			$item['normalized_payload']['payload'][ $message_type ] = $media;
		}
		return $item;
	}

	private function media_filename( array $media, $phone ) {
		$id = preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) ( $media['id'] ?? 'media' ) );
		$phone_id = preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) ( $phone->phone_number_id ?? 'phone' ) );
		return 'was-router-' . $phone_id . '-' . $id . $this->media_extension( $media );
	}

	private function media_extension( array $media ) {
		if ( ! empty( $media['filename'] ) ) {
			$extension = pathinfo( (string) $media['filename'], PATHINFO_EXTENSION );
			if ( $extension ) {
				return '.' . strtolower( substr( $extension, 0, 20 ) );
			}
		}

		$mime = strtolower( trim( explode( ';', (string) ( $media['resolved_mime_type'] ?? $media['mime_type'] ?? '' ) )[0] ) );
		$map = [
			'audio/aac' => '.aac',
			'audio/amr' => '.amr',
			'audio/m4a' => '.m4a',
			'audio/mp4' => '.m4a',
			'audio/mpeg' => '.mp3',
			'audio/mp3' => '.mp3',
			'audio/ogg' => '.ogg',
			'audio/opus' => '.opus',
			'audio/wav' => '.wav',
			'application/pdf' => '.pdf',
			'image/gif' => '.gif',
			'image/jpeg' => '.jpg',
			'image/png' => '.png',
			'image/webp' => '.webp',
			'text/plain' => '.txt',
			'video/3gpp' => '.3gp',
			'video/mp4' => '.mp4',
			'video/quicktime' => '.mov',
			'video/webm' => '.webm',
		];
		if ( isset( $map[ $mime ] ) ) {
			return $map[ $mime ];
		}

		$fallback = [
			'audio' => '.ogg',
			'document' => '.bin',
			'image' => '.jpg',
			'sticker' => '.webp',
			'video' => '.mp4',
		];
		return $fallback[ $media['type'] ?? '' ] ?? '.bin';
	}

	private function route_matches_item( $route, array $item ) {
		if ( empty( $route->event_filters_json ) ) {
			return true;
		}

		$filters = json_decode( $route->event_filters_json, true );
		if ( ! is_array( $filters ) ) {
			return true;
		}

		if ( ! empty( $filters['event_types'] ) && ! in_array( $item['event_type'] ?? '', (array) $filters['event_types'], true ) ) {
			return false;
		}

		if ( ! empty( $filters['message_types'] ) && ! in_array( $item['message_type'] ?? '', (array) $filters['message_types'], true ) ) {
			return false;
		}

		return true;
	}

	private function make_idempotency_key( array $item, array $raw_payload ) {
		$parts = [
			$item['meta_waba_id'] ?? '',
			$item['meta_phone_number_id'] ?? '',
			$item['event_type'] ?? '',
			$item['message_type'] ?? '',
			$item['wa_message_id'] ?? '',
			$item['wa_from'] ?? '',
			$item['item_index'] ?? '',
		];

		if ( ! empty( $item['wa_message_id'] ) ) {
			return hash( 'sha256', implode( '|', $parts ) );
		}

		return hash( 'sha256', implode( '|', $parts ) . '|' . wp_json_encode( $raw_payload ) );
	}

	private function find_phone_by_meta_id( $meta_phone_number_id ) {
		if ( ! $meta_phone_number_id ) {
			return null;
		}

		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE phone_number_id = %s LIMIT 1",
				(string) $meta_phone_number_id
			)
		);
	}

	private function find_phone_by_id( $id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function find_account_by_waba_id( $meta_waba_id ) {
		if ( ! $meta_waba_id ) {
			return null;
		}

		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE waba_id = %s LIMIT 1",
				(string) $meta_waba_id
			)
		);
	}

	private function find_account_by_id( $id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function find_meta_app_by_app_id( $app_id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'meta_apps' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE app_id = %s LIMIT 1", (string) $app_id ) );
	}
}

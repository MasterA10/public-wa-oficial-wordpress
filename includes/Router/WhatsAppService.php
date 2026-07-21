<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WhatsAppService {

	public function send_message( array $payload, $actor = null ) {
		global $wpdb;

		$internal_phone_id = (int) ( $payload['phone_number_id'] ?? 0 );
		$to_number = preg_replace( '/\D+/', '', (string) ( $payload['to_number'] ?? $payload['to'] ?? '' ) );
		$message_type = sanitize_text_field( $payload['message_type'] ?? $payload['type'] ?? 'text' );
		$message_payload = $payload['payload'] ?? [];
		$idempotency_key = isset( $payload['idempotency_key'] ) ? sanitize_text_field( $payload['idempotency_key'] ) : null;
		$operational_message = in_array( $message_type, [ 'read', 'typing_indicator' ], true );
		$conversation_id = (int) ( $payload['conversation_id'] ?? 0 );

		if ( ! $internal_phone_id || ! $message_type || ! is_array( $message_payload ) || ( ! $operational_message && ! $to_number ) ) {
			return new WP_Error( 'invalid_send_payload', 'phone_number_id, to_number, message_type e payload sao obrigatorios.', [ 'status' => 400 ] );
		}

		if ( $idempotency_key ) {
			$existing = $this->find_outbound_by_idempotency_key( $idempotency_key );
			if ( $existing ) {
				return [
					'success'         => 'sent' === $existing->status,
					'status'          => $existing->status,
					'id'              => (int) $existing->id,
					'phone_number_id' => (int) $existing->phone_number_id,
					'meta_message_id' => $existing->meta_message_id,
					'wa_message_id'   => $existing->meta_message_id,
					'meta_response'   => json_decode( $existing->meta_response ?: '{}', true ),
				];
			}
		}

		$phone = $this->find_phone( $internal_phone_id );
		if ( ! $phone ) {
			return new WP_Error( 'phone_number_not_found', 'Numero interno do Router nao encontrado.', [ 'status' => 404 ] );
		}

		if ( 'template' === strtolower( $message_type ) ) {
			$template = $this->find_approved_template( (int) $phone->tenant_id, (int) $phone->whatsapp_account_id, $message_payload );
			if ( is_wp_error( $template ) ) {
				return $template;
			}
			if ( $template ) {
				$validated = $this->validated_template_payload( $template, $message_payload['template'] );
				if ( is_wp_error( $validated ) ) {
					return $validated;
				}
				$message_payload = [ 'template' => $validated ];
			}
			if ( false === $template ) {
				return new WP_Error( 'template_not_approved', 'O template precisa existir na WABA deste numero e estar aprovado.', [ 'status' => 409 ] );
			}
		}

		$token = ( new TokenService() )->get_active_token( (int) $phone->tenant_id, (int) $phone->whatsapp_account_id );
		if ( ! $token ) {
			return new WP_Error( 'meta_token_not_found', 'Token Meta ativo nao encontrado para a WABA.', [ 'status' => 409 ] );
		}

		$meta_body = $operational_message
			? array_merge(
				[
					'messaging_product' => 'whatsapp',
				],
				$message_payload
			)
			: array_merge(
				[
					'messaging_product' => 'whatsapp',
					'to'                => $to_number,
					'type'              => $message_type,
				],
				$message_payload
			);

		if ( ! $operational_message && ! $conversation_id ) {
			$conversation_id = $this->find_conversation_for_typing( $phone, $to_number );
		}

		if ( ! $operational_message && $conversation_id ) {
			$this->show_typing_before_send( $conversation_id );
		}

		$result = ( new MetaApiClient() )->postJson(
			'messages.send',
			[ 'phone_number_id' => $phone->phone_number_id ],
			$meta_body,
			$token
		);

		$meta_message_id = $result['messages'][0]['id'] ?? $result['wa_message_id'] ?? $result['message_id'] ?? $result['meta_message_id'] ?? null;
		$status = ! empty( $result['success'] ) && 'failed' !== strtolower( (string) ( $result['status'] ?? '' ) ) ? 'sent' : 'failed';
		$outbound_id = $this->insert_outbound_message(
			[
				'tenant_id'       => (int) $phone->tenant_id,
				'phone_number_id' => $internal_phone_id,
				'to_number'       => $to_number,
				'message_type'    => $message_type,
				'idempotency_key' => $idempotency_key,
				'request_payload' => $meta_body,
				'meta_response'   => $result,
				'meta_message_id' => $meta_message_id,
				'status'          => $status,
				'requested_by'    => is_object( $actor ) ? ( $actor->name ?? $actor->user_id ?? $actor->type ?? null ) : null,
			]
		);

		return [
			'success'         => 'sent' === $status,
			'status'          => $status,
			'id'              => $outbound_id,
			'phone_number_id' => $internal_phone_id,
			'meta_message_id' => $meta_message_id,
			'wa_message_id'   => $meta_message_id,
			'meta_response'   => $result,
		];
	}

	private function show_typing_before_send( $conversation_id ) {
		try {
			$result = ( new \WAS\WhatsApp\TypingIndicatorService() )->show_typing( (int) $conversation_id );
			if ( empty( $result['success'] ) && empty( $result['skipped'] ) ) {
				\WAS\Core\SystemLogger::logWarning( 'WhatsAppService: Não foi possível exibir o typing antes do envio.', [
					'conversation_id' => $conversation_id,
					'error'           => $result['error'] ?? 'unknown',
				] );
			}
		} catch ( \Throwable $e ) {
			\WAS\Core\SystemLogger::logException( $e, [
				'context'         => 'WhatsAppService::show_typing_before_send',
				'conversation_id' => $conversation_id,
			] );
		}
	}

	private function find_conversation_for_typing( $phone, $to_number ) {
		if ( ! $phone || ! $to_number ) {
			return 0;
		}

		\WAS\Auth\TenantContext::set_tenant_id( (int) $phone->tenant_id );
		$contact = ( new \WAS\Inbox\ContactRepository() )->find_by_wa_id(
			(int) $phone->tenant_id,
			(string) $to_number
		);
		if ( ! $contact ) {
			return 0;
		}

		$conversation = ( new \WAS\Inbox\ConversationRepository() )->find_open_conversation_for_contact(
			(int) $contact->id,
			(string) $phone->phone_number_id
		);

		return $conversation ? (int) $conversation->id : 0;
	}

	public function get_phone_status( $internal_phone_id, $sync = true ) {
		$phone = $this->find_phone( (int) $internal_phone_id );
		if ( ! $phone ) {
			return new WP_Error( 'phone_number_not_found', 'Numero interno do Router nao encontrado.', [ 'status' => 404 ] );
		}

		if ( $sync ) {
			$this->sync_phone_status( $phone );
			$phone = $this->find_phone( (int) $internal_phone_id );
		}

		$connection_state = $this->connection_state( $phone );
		$quality_rating = $phone->quality_rating ?? null;

		return [
			'id'                       => (int) $phone->id,
			'tenant_id'                => (int) $phone->tenant_id,
			'waba_id'                  => (int) $phone->whatsapp_account_id,
			'router_waba_id'           => (int) $phone->whatsapp_account_id,
			'router_phone_number_id'   => (int) $phone->id,
			'meta_phone_number_id'     => $phone->phone_number_id,
			'whatsapp_phone_number_id' => $phone->phone_number_id,
			'display_phone_number'     => $phone->display_phone_number ?? null,
			'verified_name'            => $phone->verified_name ?? null,
			'quality_rating'           => $quality_rating,
			'quality_label'            => $this->quality_label( $quality_rating ),
			'quality_color'            => $this->quality_color( $quality_rating ),
			'quality_score'            => $this->quality_score( $quality_rating ),
			'messaging_limit_tier'     => $phone->messaging_limit_tier ?? null,
			'meta_status'              => $phone->meta_status ?? null,
			'code_verification_status' => $phone->code_verification_status ?? null,
			'platform_type'            => $phone->platform_type ?? null,
			'connection_state'         => $connection_state,
			'connection_label'         => $this->connection_label( $connection_state ),
			'last_status_check_at'     => $phone->last_status_check_at ?? null,
			'checked_at'               => $phone->last_status_check_at ?? null,
			'last_status_changed_at'   => $phone->last_status_changed_at ?? null,
			'last_status_error'        => $phone->last_status_error ?? null,
		];
	}

	public function sync_waba_phone_numbers( $internal_waba_id ) {
		global $wpdb;

		$account = $this->find_account( (int) $internal_waba_id );
		if ( ! $account ) {
			return new WP_Error( 'waba_not_found', 'WABA interna nao encontrada.', [ 'status' => 404 ] );
		}

		$token = ( new TokenService() )->get_active_token( (int) $account->tenant_id, (int) $account->id );
		if ( ! $token ) {
			return new WP_Error( 'meta_token_not_found', 'Token Meta ativo nao encontrado para a WABA.', [ 'status' => 409 ] );
		}

		$result = ( new MetaApiClient() )->get(
			'waba.phone_numbers',
			[ 'waba_id' => $account->waba_id ],
			[],
			$token
		);

		if ( empty( $result['success'] ) ) {
			$wpdb->update(
				TableNameResolver::get_table_name( 'whatsapp_accounts' ),
				[
					'last_sync_error' => $result['error'] ?? 'meta_sync_failed',
					'last_sync_at'    => current_time( 'mysql', true ),
				],
				[ 'id' => (int) $account->id ]
			);
			return new WP_Error( 'meta_sync_failed', $result['error'] ?? 'Falha ao sincronizar numeros da WABA.', [ 'status' => 502 ] );
		}

		$numbers = $result['data'] ?? [];
		$synced = [];
		foreach ( $numbers as $number ) {
			$internal_phone_id = $this->upsert_phone_from_meta( $account, $number );
			if ( $internal_phone_id ) {
				$meta_phone_id = (string) ( $number['id'] ?? $number['phone_number_id'] ?? '' );
				$synced[] = [
					'id'                       => $internal_phone_id,
					'router_phone_number_id'   => $internal_phone_id,
					'phone_number_id'          => $internal_phone_id,
					'meta_phone_number_id'     => $meta_phone_id,
					'whatsapp_phone_number_id' => $meta_phone_id,
					'display_phone_number'     => $number['display_phone_number'] ?? $meta_phone_id,
					'verified_name'            => $number['verified_name'] ?? null,
					'quality_rating'           => $number['quality_rating'] ?? null,
					'meta_status'              => $number['status'] ?? null,
				];
			}
		}

		$wpdb->update(
			TableNameResolver::get_table_name( 'whatsapp_accounts' ),
			[
				'last_sync_error' => null,
				'last_sync_at'    => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $account->id ]
		);

		return [
			'success'       => true,
			'error'         => '',
			'waba_id'       => (int) $account->id,
			'router_waba_id'=> (int) $account->id,
			'meta_waba_id'  => $account->waba_id,
			'count'         => count( $synced ),
			'synced'        => $synced,
			'phone_numbers' => $synced,
		];
	}

	private function sync_phone_status( $phone ) {
		global $wpdb;

		$token = ( new TokenService() )->get_active_token( (int) $phone->tenant_id, (int) $phone->whatsapp_account_id );
		if ( ! $token ) {
			return false;
		}

		$result = ( new MetaApiClient() )->get(
			'phone.get',
			[ 'phone_number_id' => $phone->phone_number_id ],
			[
				'fields' => 'id,display_phone_number,verified_name,quality_rating,status,code_verification_status,platform_type,throughput',
			],
			$token
		);

		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		if ( empty( $result['success'] ) ) {
			$wpdb->update(
				$table,
				[
					'last_status_check_at' => current_time( 'mysql', true ),
					'last_status_error'    => $result['error'] ?? 'status_sync_failed',
				],
				[ 'id' => (int) $phone->id ]
			);
			return false;
		}

		$fingerprint_payload = [
			'quality_rating'           => $result['quality_rating'] ?? null,
			'status'                   => $result['status'] ?? null,
			'code_verification_status' => $result['code_verification_status'] ?? null,
			'platform_type'            => $result['platform_type'] ?? null,
			'throughput'               => $result['throughput'] ?? null,
		];
		$fingerprint = hash( 'sha256', wp_json_encode( $fingerprint_payload ) );
		$changed = $fingerprint !== (string) $phone->last_status_fingerprint;

		$wpdb->update(
			$table,
			[
				'display_phone_number'      => $result['display_phone_number'] ?? $phone->display_phone_number,
				'verified_name'             => $result['verified_name'] ?? $phone->verified_name,
				'quality_rating'            => $result['quality_rating'] ?? null,
				'meta_status'               => $result['status'] ?? null,
				'code_verification_status'  => $result['code_verification_status'] ?? null,
				'platform_type'             => $result['platform_type'] ?? null,
				'throughput_json'           => isset( $result['throughput'] ) ? wp_json_encode( $result['throughput'] ) : null,
				'last_status_check_at'      => current_time( 'mysql', true ),
				'last_status_changed_at'    => $changed ? current_time( 'mysql', true ) : $phone->last_status_changed_at,
				'last_status_error'         => null,
				'last_status_fingerprint'   => $fingerprint,
				'last_status_response_json' => wp_json_encode( $result ),
				'updated_at'                => current_time( 'mysql', true ),
			],
			[ 'id' => (int) $phone->id ]
		);

		if ( $changed ) {
			$account = $this->find_account( (int) $phone->whatsapp_account_id );
			$throughput = isset( $result['throughput'] ) && is_array( $result['throughput'] ) ? $result['throughput'] : null;
			$previous_throughput = ! empty( $phone->throughput_json ) ? json_decode( $phone->throughput_json, true ) : null;
			$quality = strtoupper( (string) ( $result['quality_rating'] ?? 'UNKNOWN' ) );
			( new WebhookRouterService() )->record_synthetic_event(
				[
					'event_type'           => 'phone_number_status_changed',
					'message_type'         => 'phone_number_status',
					'meta_waba_id'         => $account->waba_id ?? null,
					'meta_phone_number_id' => $phone->phone_number_id,
					'wa_message_id'        => null,
					'wa_from'              => null,
					'normalized_payload'   => [
						'event_type'               => 'phone_number_status_changed',
						'router_phone_number_id'   => (int) $phone->id,
						'router_waba_id'           => (int) $phone->whatsapp_account_id,
						'phone_number_id'          => (int) $phone->id,
						'meta_phone_number_id'     => $phone->phone_number_id,
						'display_phone_number'     => $result['display_phone_number'] ?? $phone->display_phone_number,
						'verified_name'            => $result['verified_name'] ?? $phone->verified_name,
						'meta_status'              => $result['status'] ?? null,
						'connection_state'         => $this->connection_state_from_status( $result['status'] ?? null ),
						'quality_rating'           => $quality,
						'quality_label'            => $this->quality_label( $quality ),
						'quality_color'            => $this->quality_color( $quality ),
						'quality_score'            => $this->quality_score( $quality ),
						'code_verification_status' => $result['code_verification_status'] ?? null,
						'platform_type'            => $result['platform_type'] ?? null,
						'throughput'               => $throughput,
						'previous'                 => [
							'display_phone_number'     => $phone->display_phone_number,
							'verified_name'            => $phone->verified_name,
							'quality_rating'           => $phone->quality_rating,
							'meta_status'              => $phone->meta_status,
							'connection_state'         => $this->connection_state( $phone ),
							'code_verification_status' => $phone->code_verification_status,
							'platform_type'            => $phone->platform_type,
							'throughput'               => is_array( $previous_throughput ) ? $previous_throughput : null,
							'fingerprint'              => $phone->last_status_fingerprint,
						],
						'current'                  => $fingerprint_payload,
						'checked_at'               => gmdate( 'c' ),
						'changed_at'               => gmdate( 'c' ),
					],
					'item_index'           => 'phone-status:' . (int) $phone->id . ':' . $fingerprint,
				]
			);
		}

		return true;
	}

	private function insert_outbound_message( array $data ) {
		global $wpdb;
		$table = TableNameResolver::getOutboundMessagesTable();
		$wpdb->insert(
			$table,
			[
				'tenant_id'       => (int) $data['tenant_id'],
				'phone_number_id' => (int) $data['phone_number_id'],
				'to_number'       => $data['to_number'],
				'message_type'    => $data['message_type'],
				'idempotency_key' => $data['idempotency_key'],
				'request_payload' => wp_json_encode( $data['request_payload'] ),
				'meta_response'   => wp_json_encode( $data['meta_response'] ),
				'meta_message_id' => $data['meta_message_id'],
				'status'          => $data['status'],
				'requested_by'    => $data['requested_by'],
				'created_at'      => current_time( 'mysql', true ),
			]
		);

		return (int) $wpdb->insert_id;
	}

	private function find_approved_template( $tenant_id, $account_id, array $message_payload ) {
		$template_payload = $message_payload['template'] ?? [];
		$name = sanitize_text_field( $template_payload['name'] ?? '' );
		$language = sanitize_text_field( $template_payload['language']['code'] ?? $template_payload['language'] ?? '' );
		if ( ! $name || ! $language ) {
			return null;
		}
		global $wpdb;
		$table = TableNameResolver::getTemplatesTable();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE tenant_id = %d AND whatsapp_account_id = %d AND name = %s AND language = %s AND deleted_at IS NULL LIMIT 1", (int) $tenant_id, (int) $account_id, $name, $language ) );
		if ( ! $row ) {
			// Compatibilidade com integrações legadas que ainda não sincronizaram
			// o catálogo local. O backend continua validando quando há registro.
			return null;
		}
		return 'APPROVED' === strtoupper( (string) $row->status ) ? $row : false;
	}

	private function validated_template_payload( $template, array $requested ) {
		$payload = [
			'name' => $template->name,
			'language' => [ 'code' => $template->language ],
			'components' => is_array( $requested['components'] ?? null ) ? $requested['components'] : [],
		];
		$stored = json_decode( $template->components_json ?? '[]', true );
		$stored = is_array( $stored ) ? $stored : [];
		$expected = 0;
		foreach ( $stored as $component ) {
			if ( 'BODY' === strtoupper( $component['type'] ?? '' ) ) {
				$expected = substr_count( (string) ( $component['text'] ?? '' ), '{{' );
			}
		}
		$actual = 0;
		foreach ( $payload['components'] as $component ) {
			if ( 'BODY' === strtoupper( $component['type'] ?? '' ) ) {
				$actual = count( $component['parameters'] ?? [] );
			}
		}
		if ( $expected && $expected !== $actual ) {
			return new WP_Error( 'invalid_template_parameters', 'Quantidade de parametros do corpo do template invalida.', [ 'status' => 422 ] );
		}
		return $payload;
	}

	private function upsert_phone_from_meta( $account, array $number ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$meta_phone_id = (string) ( $number['id'] ?? $number['phone_number_id'] ?? '' );

		if ( ! $meta_phone_id ) {
			return 0;
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE phone_number_id = %s LIMIT 1", $meta_phone_id )
		);

		$data = [
			'tenant_id'               => (int) $account->tenant_id,
			'whatsapp_account_id'     => (int) $account->id,
			'phone_number_id'         => $meta_phone_id,
			'display_phone_number'    => $number['display_phone_number'] ?? $meta_phone_id,
			'verified_name'           => $number['verified_name'] ?? null,
			'quality_rating'          => $number['quality_rating'] ?? null,
			'messaging_limit_tier'    => $number['messaging_limit_tier'] ?? $number['messaging_limit'] ?? null,
			'status'                  => 'active',
			'meta_status'             => $number['status'] ?? null,
			'updated_at'              => current_time( 'mysql', true ),
		];

		if ( $existing ) {
			$wpdb->update( $table, $data, [ 'id' => (int) $existing->id ] );
			return (int) $existing->id;
		}

		$data['created_at'] = current_time( 'mysql', true );
		$data['is_default'] = $this->tenant_has_phone( (int) $account->tenant_id ) ? 0 : 1;
		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	private function find_outbound_by_idempotency_key( $idempotency_key ) {
		global $wpdb;
		$table = TableNameResolver::getOutboundMessagesTable();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE idempotency_key = %s LIMIT 1", $idempotency_key ) );
	}

	private function find_phone( $id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function find_account( $id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}

	private function tenant_has_phone( $tenant_id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE tenant_id = %d LIMIT 1", (int) $tenant_id ) );
	}

	private function connection_state( $phone ) {
		if ( ! empty( $phone->last_status_error ) ) {
			return 'check_error';
		}

		return $this->connection_state_from_status( $phone->meta_status ?? null );
	}

	private function connection_state_from_status( $meta_status ) {
		$value = strtoupper( trim( (string) $meta_status ) );
		if ( in_array( $value, [ 'CONNECTED', 'ACTIVE', 'READY', 'VERIFIED' ], true ) ) {
			return 'connected';
		}

		if ( in_array( $value, [ 'DISCONNECTED', 'DISABLED', 'BLOCKED', 'BANNED', 'RESTRICTED', 'ERROR', 'EXPIRED', 'UNVERIFIED' ], true ) ) {
			return 'disconnected';
		}

		return 'unknown';
	}

	private function connection_label( $state ) {
		$map = [
			'connected'    => 'Conectado',
			'disconnected' => 'Desconectado',
			'check_error'  => 'Erro ao verificar',
			'unknown'      => 'Status desconhecido',
		];
		return $map[ (string) $state ] ?? 'Status desconhecido';
	}

	private function quality_label( $quality ) {
		$map = [
			'GREEN'   => 'Boa qualidade',
			'YELLOW'  => 'Atencao',
			'RED'     => 'Baixa qualidade',
			'UNKNOWN' => 'Status desconhecido',
		];
		return $map[ strtoupper( (string) $quality ) ] ?? 'Status desconhecido';
	}

	private function quality_color( $quality ) {
		$map = [
			'GREEN'   => 'green',
			'YELLOW'  => 'yellow',
			'RED'     => 'red',
			'UNKNOWN' => 'gray',
		];
		return $map[ strtoupper( (string) $quality ) ] ?? 'gray';
	}

	private function quality_score( $quality ) {
		$map = [
			'GREEN'   => 100,
			'YELLOW'  => 60,
			'RED'     => 20,
			'UNKNOWN' => 0,
		];
		return $map[ strtoupper( (string) $quality ) ] ?? 0;
	}
}

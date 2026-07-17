<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes an external, Meta-shaped send webhook and dispatches it through
 * the same phone/WABA-aware service used by the Router API.
 */
class ExternalSendWebhookService {

	public function send( array $input ) {
		$normalized = $this->normalize( $input );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$phone = $this->find_phone( $normalized['phone_number_id'] );
		if ( ! $phone ) {
			return new WP_Error( 'phone_number_not_found', 'phone_number_id da Meta nao esta cadastrado.', [ 'status' => 404 ] );
		}

		if ( ! empty( $normalized['tenant_id'] ) && (int) $normalized['tenant_id'] !== (int) $phone->tenant_id ) {
			return new WP_Error( 'phone_tenant_mismatch', 'O phone_number_id nao pertence ao tenant informado.', [ 'status' => 403 ] );
		}

		if ( ! empty( $normalized['waba_id'] ) ) {
			$account = $this->find_account( (int) $phone->whatsapp_account_id );
			if ( ! $account || (string) $account->waba_id !== (string) $normalized['waba_id'] ) {
				return new WP_Error( 'phone_waba_mismatch', 'O phone_number_id nao pertence a WABA informada.', [ 'status' => 403 ] );
			}
		}

		$result = ( new WhatsAppService() )->send_message(
			[
				'phone_number_id' => (int) $phone->id,
				'to_number'       => $normalized['to'],
				'message_type'    => $normalized['message_type'],
				'payload'         => $normalized['payload'],
				'idempotency_key' => $normalized['idempotency_key'],
			],
			(object) [ 'type' => 'external_webhook', 'name' => 'external-send-webhook' ]
		);

		if ( ! is_wp_error( $result ) && is_array( $result ) ) {
			$result['meta_phone_number_id'] = $phone->phone_number_id;
		}

		return $result;
	}

	public function normalize( array $input ) {
		$value = [];
		$message = [];
		$metadata = [];

		if ( ! empty( $input['entry'] ) && is_array( $input['entry'] ) ) {
			$change = $input['entry'][0]['changes'][0] ?? [];
			$value = is_array( $change['value'] ?? null ) ? $change['value'] : [];
			$metadata = is_array( $value['metadata'] ?? null ) ? $value['metadata'] : [];
			$message = is_array( $value['messages'][0] ?? null ) ? $value['messages'][0] : [];
		}

		$phone_number_id = $input['phone_number_id']
			?? $input['meta_phone_number_id']
			?? $metadata['phone_number_id']
			?? $value['phone_number_id']
			?? '';
		$to = $input['to']
			?? $input['to_number']
			?? $value['to']
			?? $message['to']
			?? $message['recipient_id']
			?? '';
		$message_type = sanitize_text_field( $input['message_type'] ?? $input['type'] ?? $message['type'] ?? 'text' );

		if ( isset( $input['payload'] ) ) {
			$message_payload = $input['payload'];
		} elseif ( isset( $input[ $message_type ] ) ) {
			$message_payload = [ $message_type => $input[ $message_type ] ];
		} elseif ( isset( $message[ $message_type ] ) ) {
			$message_payload = [ $message_type => $message[ $message_type ] ];
		} else {
			$message_payload = [];
		}

		if ( ! is_array( $message_payload ) || ! $phone_number_id || ! $to || ! $message_type ) {
			return new WP_Error(
				'invalid_external_send_payload',
				'Informe phone_number_id, to, type e o objeto da mensagem (por exemplo, text).',
				[ 'status' => 400 ]
			);
		}

		return [
			'tenant_id'       => (int) ( $input['tenant_id'] ?? $value['tenant_id'] ?? 0 ),
			'waba_id'         => sanitize_text_field( $input['waba_id'] ?? $value['waba_id'] ?? $input['meta_waba_id'] ?? '' ),
			'phone_number_id' => sanitize_text_field( $phone_number_id ),
			'to'              => preg_replace( '/\D+/', '', (string) $to ),
			'message_type'    => $message_type,
			'payload'         => $message_payload,
			'idempotency_key' => sanitize_text_field( $input['idempotency_key'] ?? $message['id'] ?? '' ) ?: null,
		];
	}

	private function find_phone( $meta_phone_number_id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE phone_number_id = %s LIMIT 1",
			(string) $meta_phone_number_id
		) );
	}

	private function find_account( $id ) {
		global $wpdb;
		$table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $id ) );
	}
}

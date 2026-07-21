<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;
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

		// Textos and public-link media go through the same Inbox services used by
		// the chat. This keeps window validation, local persistence, phone/token
		// resolution and media upload behavior identical to the operator UI.
		TenantContext::set_tenant_id( (int) $phone->tenant_id );
		$inbox_result = $this->send_using_inbox_services( $normalized, $phone );
		if ( null !== $inbox_result ) {
			if ( ! is_wp_error( $inbox_result ) && is_array( $inbox_result ) ) {
				$inbox_result['meta_phone_number_id'] = $phone->phone_number_id;
			}
			return $inbox_result;
		}

		$router_payload = [
				'phone_number_id' => (int) $phone->id,
				'to_number'       => $normalized['to'],
				'message_type'    => $normalized['message_type'],
				'payload'         => $normalized['payload'],
				'idempotency_key' => $normalized['idempotency_key'],
			];
		$conversation = $this->find_or_create_conversation( $phone, $normalized['to'] );
		if ( ! is_wp_error( $conversation ) && $conversation ) {
			$router_payload['conversation_id'] = (int) $conversation->id;
		}

		$result = ( new WhatsAppService() )->send_message(
			$router_payload,
			(object) [ 'type' => 'external_webhook', 'name' => 'external-send-webhook' ]
		);

		if ( ! is_wp_error( $result ) && is_array( $result ) ) {
			$result['meta_phone_number_id'] = $phone->phone_number_id;
		}

		return $result;
	}

	/**
	 * Reuse the Inbox/chat send path where the external payload contains a
	 * message type supported by the chat composer.
	 *
	 * Returns null for payloads that must remain on the Router transport (for
	 * example a pre-uploaded Meta media id or a template). This preserves the
	 * existing Meta-like contract while making text and public-link media use
	 * exactly the same services as the chat.
	 */
	private function send_using_inbox_services( array $normalized, $phone ) {
		$type = strtolower( (string) $normalized['message_type'] );
		if ( 'text' !== $type && ! in_array( $type, [ 'image', 'audio', 'document', 'video' ], true ) ) {
			return null;
		}

		$conversation = $this->find_or_create_conversation( $phone, $normalized['to'] );
		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		if ( 'text' === $type ) {
			$text = (string) ( $normalized['payload']['text']['body'] ?? '' );
			if ( '' === trim( $text ) ) {
				return new WP_Error( 'invalid_external_text', 'O payload text.body e obrigatorio.', [ 'status' => 400 ] );
			}

			$result = ( new \WAS\Inbox\OutboundMessageService() )->send_text( (int) $conversation->id, $text );
			if ( ! empty( $result['success'] ) ) {
				return $result;
			}

			return new WP_Error(
				'external_chat_send_failed',
				$result['error'] ?? 'Falha ao enviar texto pelo serviço do chat.',
				[ 'status' => 502, 'chat_result' => $result ]
			);
		}

		$media = is_array( $normalized['payload'][ $type ] ?? null ) ? $normalized['payload'][ $type ] : [];
		$link = (string) ( $media['link'] ?? '' );
		if ( '' === $link ) {
			// A media id already hosted by Meta is intentionally left on the
			// Router transport; the chat composer starts with a local file.
			return null;
		}

		$download = $this->download_external_media( $link, $media );
		if ( is_wp_error( $download ) ) {
			return $download;
		}

		try {
			$result = ( new \WAS\WhatsApp\OutboundMediaService() )->send_media(
				(int) $conversation->id,
				$download['path'],
				$download['mime_type'],
				$type,
				(string) ( $media['caption'] ?? '' ),
				(string) ( $media['filename'] ?? $download['filename'] )
			);
		} catch ( \Throwable $e ) {
			return new WP_Error( 'external_chat_media_send_failed', $e->getMessage(), [ 'status' => 502 ] );
		} finally {
			if ( ! empty( $download['path'] ) && file_exists( $download['path'] ) ) {
				@unlink( $download['path'] );
			}
		}

		if ( ! empty( $result['success'] ) ) {
			return $result;
		}

		return new WP_Error(
			'external_chat_media_send_failed',
			$result['error'] ?? 'Falha ao enviar mídia pelo serviço do chat.',
			[ 'status' => 502, 'chat_result' => $result ]
		);
	}

	private function find_or_create_conversation( $phone, $to ) {
		$contacts = new \WAS\Inbox\ContactRepository();
		$contact = $contacts->find_or_create_by_wa_id( (string) $to, '', (string) $to );
		if ( ! $contact ) {
			return new WP_Error( 'external_contact_failed', 'Nao foi possivel criar ou localizar o contato.', [ 'status' => 500 ] );
		}

		$conversation = ( new \WAS\Inbox\ConversationRepository() )->find_or_create_open_conversation(
			(int) $contact->id,
			(string) $phone->phone_number_id
		);
		if ( ! $conversation ) {
			return new WP_Error( 'external_conversation_failed', 'Nao foi possivel criar ou localizar a conversa.', [ 'status' => 500 ] );
		}

		return $conversation;
	}

	private function download_external_media( $url, array $media ) {
		$parts = wp_parse_url( $url );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return new WP_Error( 'external_media_url_invalid', 'A mídia externa precisa usar uma URL HTTP ou HTTPS.', [ 'status' => 400 ] );
		}

		$response = wp_remote_get( $url, [ 'timeout' => 60 ] );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'external_media_download_failed', $response->get_error_message(), [ 'status' => 502 ] );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 || '' === $body ) {
			return new WP_Error( 'external_media_download_failed', 'Falha ao baixar mídia externa (HTTP ' . $code . ').', [ 'status' => 502 ] );
		}

		$filename = sanitize_file_name( (string) ( $media['filename'] ?? basename( (string) ( $parts['path'] ?? '' ) ) ?: 'external-media' ) );
		$mime_type = strtolower( trim( explode( ';', (string) ( $media['mime_type'] ?? '' ), 2 )[0] ) );
		if ( ! $mime_type ) {
			$mime_type = $this->mime_from_filename( $filename );
		}

		$path = tempnam( sys_get_temp_dir(), 'was-external-' );
		if ( ! $path || false === file_put_contents( $path, $body ) ) {
			return new WP_Error( 'external_media_storage_failed', 'Nao foi possivel salvar a mídia externa temporariamente.', [ 'status' => 500 ] );
		}

		return [ 'path' => $path, 'filename' => $filename, 'mime_type' => $mime_type ];
	}

	private function mime_from_filename( $filename ) {
		$map = [
			'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
			'ogg' => 'audio/ogg', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4',
			'mp4' => 'video/mp4', '3gp' => 'video/3gp', 'pdf' => 'application/pdf',
			'txt' => 'text/plain', 'doc' => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls' => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		];
		return $map[ strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ] ?? 'application/octet-stream';
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

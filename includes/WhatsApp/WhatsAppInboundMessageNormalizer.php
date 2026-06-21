<?php
/**
 * WhatsAppInboundMessageNormalizer class.
 *
 * @package WAS\WhatsApp
 */

namespace WAS\WhatsApp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for normalizing inbound WhatsApp messages of various types.
 */
class WhatsAppInboundMessageNormalizer {

	/**
	 * Normalize a message object from the webhook.
	 *
	 * @param array $message The raw message object.
	 * @return array
	 */
	public function normalize( array $message ) {
		$type = $message['type'] ?? 'unknown';

		$base = [
			'wa_message_id'          => $message['id'] ?? null,
			'from'                   => $message['from'] ?? null,
			'timestamp'              => $message['timestamp'] ?? null,
			'message_type'           => $type,
			'reply_to_wa_message_id' => $message['context']['id'] ?? $message['context']['message_id'] ?? null,
			'context_payload'        => isset( $message['context'] ) ? wp_json_encode( $message['context'] ) : null,
			'referral'               => $this->extractReferral( $message ),
			'raw_payload'            => wp_json_encode( $message ),
		];

		switch ( $type ) {
			case 'text':
				return array_merge( $base, $this->normalizeText( $message ) );
			case 'button':
				return array_merge( $base, $this->normalizeButton( $message ) );
			case 'interactive':
				return array_merge( $base, $this->normalizeInteractive( $message ) );
			case 'image':
			case 'audio':
			case 'video':
			case 'document':
			case 'sticker':
				return array_merge( $base, $this->normalizeMedia( $message, $type ) );
			case 'reaction':
				return array_merge( $base, $this->normalizeReaction( $message ) );
			case 'location':
				return array_merge( $base, $this->normalizeLocation( $message ) );
			case 'contacts':
				return array_merge( $base, $this->normalizeContacts( $message ) );
			case 'order':
				return array_merge( $base, $this->normalizeOrder( $message ) );
			default:
				return array_merge( $base, $this->normalizeUnsupported( $message ) );
		}
	}

	private function extractReferral( array $message ) {
		if ( empty( $message['referral'] ) ) {
			return null;
		}

		$ref = $message['referral'];
		return [
			'source_type'   => $ref['source_type'] ?? null,
			'source_id'     => $ref['source_id'] ?? null,
			'source_url'    => $ref['source_url'] ?? null,
			'headline'      => $ref['headline'] ?? null,
			'body'          => $ref['body'] ?? null,
			'media_type'    => $ref['media_type'] ?? null,
			'image_url'     => $ref['image_url'] ?? null,
			'video_url'     => $ref['video_url'] ?? null,
			'thumbnail_url' => $ref['thumbnail_url'] ?? null,
			'ctwa_clid'     => $ref['ctwa_clid'] ?? null,
			'raw_referral'  => wp_json_encode( $ref ),
		];
	}

	private function normalizeText( array $message ) {
		return [
			'text_body' => $message['text']['body'] ?? '',
		];
	}

	private function normalizeButton( array $message ) {
		$text    = $message['button']['text'] ?? '';
		$payload = $message['button']['payload'] ?? '';

		return [
			'text_body'      => $text ?: ( $payload ?: 'Resposta de botão' ),
			'button_text'    => $text,
			'button_payload' => $payload,
		];
	}

	private function normalizeInteractive( array $message ) {
		$interactive     = $message['interactive'] ?? [];
		$interactiveType = $interactive['type'] ?? null;

		if ( 'button_reply' === $interactiveType ) {
			$reply = $interactive['button_reply'] ?? [];

			return [
				'interactive_type'  => 'button_reply',
				'interactive_id'    => $reply['id'] ?? null,
				'interactive_title' => $reply['title'] ?? null,
				'text_body'         => $reply['title'] ?? 'Resposta de botão',
			];
		}

		if ( 'list_reply' === $interactiveType ) {
			$reply = $interactive['list_reply'] ?? [];

			return [
				'interactive_type'        => 'list_reply',
				'interactive_id'          => $reply['id'] ?? null,
				'interactive_title'       => $reply['title'] ?? null,
				'interactive_description' => $reply['description'] ?? null,
				'text_body'               => $reply['title'] ?? 'Resposta de lista',
			];
		}

		return [
			'interactive_type' => $interactiveType,
			'text_body'        => 'Mensagem interativa',
		];
	}

	private function normalizeMedia( array $message, $type ) {
		return [
			'text_body'     => $message[ $type ]['caption'] ?? $message[ $type ]['filename'] ?? null,
			'meta_media_id' => $message[ $type ]['id'] ?? null,
			'mime_type'     => $message[ $type ]['mime_type'] ?? null,
			'sha256'        => $message[ $type ]['sha256'] ?? null,
		];
	}

	private function normalizeReaction( array $message ) {
		$reaction = $message['reaction'] ?? [];

		return [
			'text_body'              => $reaction['emoji'] ?? 'Reação',
			'reply_to_wa_message_id' => $reaction['message_id'] ?? null,
		];
	}

	private function normalizeLocation( array $message ) {
		$loc = $message['location'] ?? [];
		return [
			'latitude'         => $loc['latitude'] ?? null,
			'longitude'        => $loc['longitude'] ?? null,
			'location_name'    => $loc['name'] ?? null,
			'location_address' => $loc['address'] ?? null,
			'text_body'        => '📍 Localização: ' . ( $loc['name'] ?? $loc['address'] ?? 'Enviada' ),
		];
	}

	private function normalizeContacts( array $message ) {
		$contacts = $message['contacts'] ?? [];
		$names    = [];
		foreach ( $contacts as $c ) {
			$names[] = $c['name']['formatted_name'] ?? 'Contato';
		}
		return [
			'contacts_json' => wp_json_encode( $contacts ),
			'text_body'     => '👤 Contato(s): ' . implode( ', ', $names ),
		];
	}

	private function normalizeOrder( array $message ) {
		$order = $message['order'] ?? [];
		return [
			'order_json' => wp_json_encode( $order ),
			'text_body'  => '🛒 Novo Pedido (Catálogo)',
		];
	}

	private function normalizeUnsupported( array $message ) {
		return [
			'text_body' => 'Tipo de mensagem ainda não suportado: ' . ( $message['type'] ?? 'unknown' ),
		];
	}
}

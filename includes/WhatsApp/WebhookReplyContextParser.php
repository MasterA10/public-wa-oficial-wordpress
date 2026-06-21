<?php
/**
 * WebhookReplyContextParser class.
 *
 * @package WAS\WhatsApp
 */

namespace WAS\WhatsApp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parser for extracting reply context from webhook payloads.
 */
class WebhookReplyContextParser {

	/**
	 * Extract reply context from a message object.
	 *
	 * @param array $message The message object from the webhook.
	 * @return array
	 */
	public function extract( array $message ) {
		$context = $message['context'] ?? null;

		if ( ! $context || ! is_array( $context ) ) {
			return [
				'has_context'            => false,
				'reply_to_wa_message_id' => null,
				'context_from'           => null,
				'context_payload'        => null,
			];
		}

		$replyToWaMessageId = $context['id']
			?? $context['message_id']
			?? null;

		return [
			'has_context'            => true,
			'reply_to_wa_message_id' => $replyToWaMessageId,
			'context_from'           => $context['from'] ?? null,
			'context_payload'        => wp_json_encode( $context ),
		];
	}
}

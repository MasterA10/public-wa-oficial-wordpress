<?php
/**
 * WebhookSubscriptionService class.
 *
 * @package WAS\WhatsApp
 */

namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing WABA webhook subscriptions.
 */
class WebhookSubscriptionService {

	const SUBSCRIBED_FIELDS = [
		'messages',
		'smb_message_echoes',
		'account_update',
		'phone_number_quality_update',
		'phone_number_name_update',
		'message_template_status_update',
		'template_category_update',
	];

	/**
	 * Meta API Client.
	 *
	 * @var MetaApiClient
	 */
	private $api_client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_client = new MetaApiClient();
	}

	/**
	 * Subscribe a WABA to the application's webhooks.
	 *
	 * @param string $waba_id      WABA ID.
	 * @param string $access_token Access Token.
	 * @param array  $options      Optional override_callback_uri and verify_token.
	 * @return array
	 */
	public function subscribeWaba( $waba_id, $access_token, array $options = [] ) {
		$body = [
			'subscribed_fields' => self::SUBSCRIBED_FIELDS,
		];
		if ( ! empty( $options['override_callback_uri'] ) ) {
			$body['override_callback_uri'] = esc_url_raw( $options['override_callback_uri'] );
		}
		if ( ! empty( $options['verify_token'] ) ) {
			$body['verify_token'] = sanitize_text_field( $options['verify_token'] );
		}

		return $this->api_client->postJson(
			'waba.subscribe_webhooks',
			[ 'waba_id' => $waba_id ],
			$body,
			$access_token
		);
	}
}

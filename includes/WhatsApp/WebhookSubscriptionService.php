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
	 * @return array
	 */
	public function subscribeWaba( $waba_id, $access_token ) {
		return $this->api_client->postJson(
			'waba.subscribe_webhooks',
			[ 'waba_id' => $waba_id ],
			[],
			$access_token
		);
	}
}

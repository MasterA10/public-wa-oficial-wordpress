<?php

use WAS\WhatsApp\WebhookSubscriptionService;

class WebhookSubscriptionServiceTest extends WAS_Router_TestCase {

	public function test_subscribe_waba_requests_router_compatible_meta_webhook_fields() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'success' => true ],
		];

		$result = ( new WebhookSubscriptionService() )->subscribeWaba(
			'meta-waba-1',
			'waba-token',
			[
				'override_callback_uri' => 'https://router.test/webhooks/meta',
				'verify_token'          => 'verify-router',
			]
		);
		$call = $GLOBALS['was_test_http_posts'][0];
		$body = json_decode( $call['args']['body'], true );

		$this->assert_true( $result['success'] );
		$this->assert_true( str_contains( $call['url'], '/v25.0/meta-waba-1/subscribed_apps' ) );
		$this->assert_same( 'Bearer waba-token', $call['args']['headers']['Authorization'] );
		$this->assert_same(
			[
				'messages',
				'smb_message_echoes',
				'account_update',
				'phone_number_quality_update',
				'phone_number_name_update',
				'message_template_status_update',
				'template_category_update',
			],
			$body['subscribed_fields']
		);
		$this->assert_same( 'https://router.test/webhooks/meta', $body['override_callback_uri'] );
		$this->assert_same( 'verify-router', $body['verify_token'] );
	}
}

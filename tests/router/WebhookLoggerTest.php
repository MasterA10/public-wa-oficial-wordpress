<?php

use WAS\Core\TableNameResolver;
use WAS\WhatsApp\WebhookLogger;

class WebhookLoggerTest extends WAS_Router_TestCase {

	public function test_verification_and_event_logs_keep_type_method_payload_and_response() {
		WebhookLogger::log_verification( [ 'hub_challenge' => 'challenge' ], 200, 'challenge' );
		WebhookLogger::log_event( [ 'object' => 'whatsapp_business_account' ], [ 'X-Hub-Signature-256' => 'sha256=test' ], 200, 'Success' );

		$rows = $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'audit_logs' ) ] ?? [];
		$this->assert_count( 2, $rows );
		$this->assert_same( 'WEBHOOK_VERIFICATION', $rows[0]['action'] );
		$this->assert_same( 'WEBHOOK_EVENT', $rows[1]['action'] );
		$metadata = json_decode( $rows[1]['metadata'], true );
		$this->assert_same( 'POST', $metadata['method'] );
		$this->assert_same( 'whatsapp_business_account', $metadata['request_data']['object'] );
		$this->assert_same( 200, $metadata['response_code'] );
	}
}

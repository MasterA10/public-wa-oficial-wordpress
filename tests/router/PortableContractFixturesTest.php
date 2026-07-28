<?php

use WAS\Router\ExternalSendWebhookService;
use WAS\Router\WebhookRouterService;
use WAS\Core\TableNameResolver;
use WAS\Meta\MetaOAuthService;
use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenVault;
use WAS\Templates\TemplateMetaService;
use WAS\WhatsApp\WhatsAppInboundMessageNormalizer;
use WAS\WhatsApp\WebhookSubscriptionService;

/**
 * Runs the language-neutral contract fixtures as a loop.
 *
 * A future implementation in another language can consume the same JSON and
 * assert the same normalized values without depending on WordPress classes.
 */
class PortableContractFixturesTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_apps' ), [
			'id'            => 1,
			'app_id'        => 'app-contract',
			'app_secret'    => TokenVault::encrypt( 'app-contract-secret' ),
			'graph_version' => 'v25.0',
			'status'        => 'active',
			'is_default'    => 1,
			'created_at'    => current_time( 'mysql', true ),
		] );
	}

	public function test_meta_and_middleware_contract_fixtures_are_executable() {
		$path = __DIR__ . '/../contracts/meta-message-contracts.json';
		$document = json_decode( file_get_contents( $path ), true );

		$this->assert_same( 1, $document['version'] );
		$this->assert_true( is_array( $document['cases'] ) );
		$this->assert_true( count( $document['cases'] ) >= 6 );

		$normalizer = new WhatsAppInboundMessageNormalizer();
		$router = new WebhookRouterService();
		$extractor = new ReflectionMethod( WebhookRouterService::class, 'extract_event_items' );
		$extractor->setAccessible( true );
		$external = new ExternalSendWebhookService();

		foreach ( $document['cases'] as $case ) {
			$this->assert_true( ! empty( $case['id'] ), 'Fixture without id.' );
			$this->assert_true( ! empty( $case['kind'] ), 'Fixture without kind.' );

			if ( in_array( $case['kind'], [ 'received', 'echo' ], true ) ) {
				$value = $case['meta_webhook']['entry'][0]['changes'][0]['value'];
				$messages = $case['kind'] === 'echo'
					? ( $value['message_echoes'] ?? $value['smb_message_echoes'] ?? [] )
					: ( $value['messages'] ?? [] );
				$normalized = $normalizer->normalize( $messages[0] );
				$this->assert_subset( $case['expected_normalized'], $normalized, $case['id'] . ' normalized message' );

				$events = $extractor->invoke( $router, $case['meta_webhook'] );
				$this->assert_count( 1, $events, $case['id'] . ' event count' );
				$this->assert_subset( $case['expected_event'], $events[0], $case['id'] . ' routed event' );
				continue;
			}

			if ( 'status' === $case['kind'] ) {
				$events = $extractor->invoke( $router, $case['meta_webhook'] );
				$this->assert_count( 1, $events, $case['id'] . ' status count' );
				$expected_event = $case['expected_event'];
				$status = $expected_event['status'] ?? null;
				unset( $expected_event['status'] );
				$this->assert_subset( $expected_event, $events[0], $case['id'] . ' status event' );
				if ( null !== $status ) {
					$this->assert_same( $status, $events[0]['normalized_payload']['status'], $case['id'] . ' status value' );
				}
				continue;
			}

			if ( 'outbound' === $case['kind'] ) {
				$normalized = $external->normalize( $case['external_request'] );
				$this->assert_false( is_wp_error( $normalized ), $case['id'] . ' outbound normalization failed' );
				$this->assert_subset( $case['expected_normalized'], $normalized, $case['id'] . ' outbound normalization' );
				$meta_request = array_merge(
					[
						'messaging_product' => 'whatsapp',
						'to'                => $normalized['to'],
						'type'              => $normalized['message_type'],
					],
					$normalized['payload']
				);
				$this->assert_subset( $case['expected_meta_request'] ?? [], $meta_request, $case['id'] . ' Meta request' );
				if ( isset( $case['expected_public_media'] ) ) {
					$this->assert_true( $case['expected_public_media']['must_download_before_meta_send'] );
				}
				continue;
			}

			if ( 'meta_operation' === $case['kind'] ) {
				$this->run_meta_operation_fixture( $case );
				continue;
			}

			throw new RuntimeException( 'Unknown portable fixture kind: ' . $case['kind'] );
		}
	}

	private function run_meta_operation_fixture( array $case ) {
		$GLOBALS['was_test_http_posts'] = [];
		$GLOBALS['was_test_http_gets'] = [];
		$GLOBALS['was_test_http_response_queue'] = [];
		$response_body = [
			'code' => 200,
			'body' => [
				'success'      => true,
				'id'           => $case['expected_response']['id'] ?? 'meta-result',
				'access_token' => 'contract-access-token',
				'data'         => $case['expected_response']['data'] ?? [],
			],
		];
		$GLOBALS['was_test_http_response'] = $response_body;

		$input = $case['input'];
		switch ( $case['operation'] ) {
			case 'oauth_access_token':
				$result = ( new MetaOAuthService() )->exchangeCodeForToken( $input['code'] );
				$this->assert_true( $result['success'], $case['id'] . ' OAuth failed' );
				$call = $GLOBALS['was_test_http_gets'][0];
				break;
			case 'subscribe_webhooks':
				$result = ( new WebhookSubscriptionService() )->subscribeWaba(
					$input['waba_id'],
					$input['token'],
					[
						'override_callback_uri' => $input['override_callback_uri'],
						'verify_token'          => $input['verify_token'],
					]
				);
				$this->assert_true( $result['success'], $case['id'] . ' subscription failed' );
				$call = $GLOBALS['was_test_http_posts'][0];
				break;
			case 'templates_create':
				$result = ( new TemplateMetaService() )->create( $input['waba_id'], $input['payload'], $input['token'] );
				$this->assert_true( $result['success'], $case['id'] . ' template create failed' );
				$call = $GLOBALS['was_test_http_posts'][0];
				break;
			case 'templates_list':
				$result = ( new TemplateMetaService() )->list( $input['waba_id'], $input['token'] );
				$this->assert_true( $result['success'], $case['id'] . ' template list failed' );
				$call = $GLOBALS['was_test_http_gets'][0];
				break;
			case 'graph_get':
				$result = ( new MetaApiClient() )->get(
					$input['graph_operation'],
					$input['path_params'] ?? [],
					$input['query'] ?? [],
					$input['token']
				);
				$this->assert_true( $result['success'], $case['id'] . ' Graph GET failed' );
				$call = $GLOBALS['was_test_http_gets'][0];
				break;
			default:
				throw new RuntimeException( 'Unknown Meta operation fixture: ' . $case['operation'] );
		}

		$request = [
			'method' => $call['args']['method'] ?? ( $case['operation'] === 'oauth_access_token' ? 'GET' : ( isset( $call['args']['body'] ) ? 'POST' : 'GET' ) ),
			'path'   => parse_url( $call['url'], PHP_URL_PATH ),
		];
		$query = [];
		parse_str( (string) ( parse_url( $call['url'], PHP_URL_QUERY ) ?? '' ), $query );
		if ( $query ) {
			$request['query'] = $query;
		}
		if ( isset( $call['args']['body'] ) ) {
			$request['body'] = json_decode( $call['args']['body'], true );
		}

		$this->assert_subset( $case['expected_request'], $request, $case['id'] . ' Meta request' );
		$this->assert_subset( $case['expected_response'] ?? [], $result, $case['id'] . ' Meta response' );
		if ( $case['operation'] !== 'oauth_access_token' ) {
			$this->assert_same( 'Bearer ' . $input['token'], $call['args']['headers']['Authorization'], $case['id'] . ' bearer token' );
		}
	}

	private function assert_subset( array $expected, array $actual, $context ) {
		foreach ( $expected as $key => $expected_value ) {
			$this->assert_array_has_key( $key, $actual, $context . ' missing ' . $key );
			$actual_value = $actual[ $key ];
			if ( is_array( $expected_value ) ) {
				$this->assert_true( is_array( $actual_value ), $context . ' expected array at ' . $key );
				$this->assert_subset( $expected_value, $actual_value, $context . '.' . $key );
				continue;
			}
			$this->assert_same( $expected_value, $actual_value, $context . ' mismatch at ' . $key );
		}
	}
}

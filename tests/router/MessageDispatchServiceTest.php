<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\WhatsApp\MessageDispatchService;

class MessageDispatchServiceTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;

		TenantContext::set_tenant_id( 1 );
		$now = current_time( 'mysql', true );
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => $now ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_apps' ), [
			'id'            => 1,
			'app_id'        => 'app-production',
			'app_secret'    => 'app-secret-production',
			'graph_version' => 'v25.0',
			'status'        => 'active',
			'is_default'    => 1,
			'created_at'    => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 7, 'tenant_id' => 1, 'waba_id' => 'waba-default', 'created_at' => $now ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 8, 'tenant_id' => 1, 'waba_id' => 'waba-conversation', 'created_at' => $now ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [
			'id' => 10, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'phone_number_id' => 'meta-phone-default', 'is_default' => 1, 'created_at' => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [
			'id' => 11, 'tenant_id' => 1, 'whatsapp_account_id' => 8, 'phone_number_id' => 'meta-phone-conversation', 'is_default' => 0, 'created_at' => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [
			'id' => 20, 'tenant_id' => 1, 'whatsapp_account_id' => 8, 'access_token_encrypted' => TokenVault::encrypt( 'conversation-token' ), 'status' => 'active', 'created_at' => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'contacts' ), [
			'id' => 30, 'tenant_id' => 1, 'wa_id' => '553199919648', 'phone' => '553199919648', 'normalized_phone' => '553199919648', 'phone_status' => 'confirmed_by_wa_id', 'created_at' => $now,
		] );
		$wpdb->insert( TableNameResolver::get_table_name( 'conversations' ), [
			'id' => 40, 'tenant_id' => 1, 'contact_id' => 30, 'phone_number_id' => 'meta-phone-conversation', 'last_inbound_wa_message_id' => 'wamid.inbound-dispatch', 'status' => 'open', 'created_at' => $now,
		] );
	}

	public function test_send_text_uses_current_meta_client_and_returns_legacy_response_object() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 200,
			'body' => [ 'messages' => [ [ 'id' => 'wamid.dispatch-text' ] ] ],
		];

		$response = ( new MessageDispatchService() )->send_text( 'meta-phone-conversation', '553199919648', 'Mensagem legada', 'conversation-token' );

		$this->assert_true( $response->success );
		$this->assert_same( 'wamid.dispatch-text', $response->data['messages'][0]['id'] );
		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_posts'][0]['url'], '/v25.0/meta-phone-conversation/messages' ) );
		$this->assert_same( 'Bearer conversation-token', $GLOBALS['was_test_http_posts'][0]['args']['headers']['Authorization'] );
		$this->assert_same( '553199919648', $body['to'] );
		$this->assert_same( 'Mensagem legada', $body['text']['body'] );
	}

	public function test_send_message_uses_conversation_phone_and_typing_before_legacy_text_send() {
		$GLOBALS['was_test_http_response_queue'] = [
			[ 'code' => 200, 'body' => [ 'success' => true ] ],
			[ 'code' => 200, 'body' => [ 'messages' => [ [ 'id' => 'wamid.dispatch-conversation' ] ] ] ],
		];

		$result = ( new MessageDispatchService() )->send_message(
			'553199919648',
			'text',
			'Enviada pela conversa',
			1,
			'meta-phone-conversation',
			40
		);

		$this->assert_true( $result['success'] );
		$this->assert_same( 'wamid.dispatch-conversation', $result['wa_message_id'] );
		$this->assert_count( 2, $GLOBALS['was_test_http_posts'] );
		$typing = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$message = json_decode( $GLOBALS['was_test_http_posts'][1]['args']['body'], true );	
		$this->assert_same( 'read', $typing['status'] );
		$this->assert_same( 'wamid.inbound-dispatch', $typing['message_id'] );
		$this->assert_true( str_contains( $GLOBALS['was_test_http_posts'][1]['url'], '/v25.0/meta-phone-conversation/messages' ) );
		$this->assert_same( 'Enviada pela conversa', $message['text']['body'] );
	}

	public function test_send_template_builds_official_payload_and_maps_meta_error() {
		$GLOBALS['was_test_http_response'] = [
			'code' => 400,
			'body' => [ 'error' => [ 'message' => 'Template rejected', 'code' => 132000 ] ],
		];

		$response = ( new MessageDispatchService() )->send_template(
			'meta-phone-conversation',
			'553199919648',
			'order_update',
			'pt_BR',
			[ [ 'type' => 'body', 'parameters' => [ [ 'text' => 'Pedido 10' ] ] ] ],
			'conversation-token'
		);

		$this->assert_false( $response->success );
		$this->assert_same( 'Template rejected', $response->error['message'] );
		$this->assert_same( 132000, $response->error['code'] );
		$body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );
		$this->assert_same( 'template', $body['type'] );
		$this->assert_same( 'order_update', $body['template']['name'] );
		$this->assert_same( 'pt_BR', $body['template']['language']['code'] );
	}
}

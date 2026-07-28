<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\REST\InboxApiController;

class InboxControllerContractsTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_runtime_tenant_id( null );
		TenantContext::set_tenant_id( 1 );
		$_FILES = [];
		$wpdb->insert( TableNameResolver::get_table_name( 'tenants' ), [ 'id' => 1, 'name' => 'Agenda', 'slug' => 'agenda', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_accounts' ), [ 'id' => 7, 'tenant_id' => 1, 'waba_id' => 'waba-1', 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ), [ 'id' => 10, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'phone_number_id' => 'phone-1', 'status' => 'active', 'is_default' => 1, 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'meta_tokens' ), [ 'id' => 20, 'tenant_id' => 1, 'whatsapp_account_id' => 7, 'access_token_encrypted' => TokenVault::encrypt( 'waba-token' ), 'status' => 'active', 'created_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'contacts' ), [ 'id' => 30, 'tenant_id' => 1, 'wa_id' => '5531999000001', 'phone' => '5531999000001', 'normalized_phone' => '5531999000001', 'profile_name' => 'Ana', 'phone_status' => 'confirmed_by_wa_id' ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'conversations' ), [ 'id' => 40, 'tenant_id' => 1, 'contact_id' => 30, 'phone_number_id' => 'phone-1', 'status' => 'open', 'last_inbound_wa_message_id' => 'wamid-inbound-1', 'customer_service_window_expires_at' => gmdate( 'Y-m-d H:i:s', time() + 86400 ), 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ] );
		$wpdb->insert( TableNameResolver::get_table_name( 'messages' ), [ 'id' => 50, 'tenant_id' => 1, 'conversation_id' => 40, 'wa_message_id' => 'wamid-inbound-1', 'direction' => 'inbound', 'message_type' => 'text', 'text_body' => 'Olá', 'status' => 'received', 'reply_to_message_id' => null, 'referral_id' => null, 'created_at' => current_time( 'mysql', true ) ] );
		$GLOBALS['was_test_http_response'] = [ 'code' => 200, 'body' => [ 'messages' => [ [ 'id' => 'wamid-outbound-1' ] ] ] ];
	}

	public function test_chat_lists_conversations_and_detail_with_renderable_diagnostics_shape() {
		$controller = new InboxApiController();
		$list = $controller->get_conversations( new WP_REST_Request( 'GET', '/conversations' ) );
		$detail_request = new WP_REST_Request( 'GET', '/conversations/40' );
		$detail_request->set_param( 'id', 40 );
		$detail = $controller->get_conversation_detail( $detail_request );
		$message = $detail->get_data()['data']['messages'][0];

		$this->assert_same( 200, $list->get_status() );
		$this->assert_true( $list->get_data()['success'] );
		$this->assert_count( 1, $list->get_data()['data'] );
		$this->assert_same( 200, $detail->get_status() );
		$this->assert_same( 'Olá', $message->text_body );
		$this->assert_array_has_key( 'diagnostics', get_object_vars( $message ) );
		$this->assert_null( $message->diagnostics['media'] );
		$this->assert_same( [], $message->diagnostics['routes'] );
	}

	public function test_chat_poll_requires_after_id_and_returns_new_messages() {
		$controller = new InboxApiController();
		$missing_request = new WP_REST_Request( 'GET', '/conversations/40/poll' );
		$missing_request->set_param( 'id', 40 );
		$missing = $controller->poll_new_messages( $missing_request );

		$poll_request = new WP_REST_Request( 'GET', '/conversations/40/poll' );
		$poll_request->set_param( 'id', 40 );
		$poll_request->set_param( 'after_id', 49 );
		$poll = $controller->poll_new_messages( $poll_request );

		$this->assert_same( 400, $missing->get_status() );
		$this->assert_same( 'Parâmetro after_id é obrigatório', $missing->get_data()['message'] );
		$this->assert_same( 200, $poll->get_status() );
		$this->assert_true( $poll->get_data()['success'] );
		$this->assert_count( 1, $poll->get_data()['data'] );
	}

	public function test_chat_rejects_empty_text_and_missing_media_with_interpretable_errors() {
		$controller = new InboxApiController();
		$text_request = new WP_REST_Request( 'POST', '/conversations/40/messages/text' );
		$text_request->set_param( 'id', 40 );
		$text_request->set_param( 'text', '' );
		$empty_text = $controller->send_text_message( $text_request );

		$media_request = new WP_REST_Request( 'POST', '/conversations/40/messages/image' );
		$media_request->set_param( 'id', 40 );
		$media_request->set_param( 'media_type', 'image' );
		$missing_media = $controller->send_media_message( $media_request );

		$this->assert_same( 400, $empty_text->get_status() );
		$this->assert_same( 'O texto da mensagem é obrigatório', $empty_text->get_data()['message'] );
		$this->assert_same( 400, $missing_media->get_status() );
		$this->assert_true( str_contains( $missing_media->get_data()['message'], 'Nenhum arquivo enviado' ) );
		$this->assert_count( 0, $GLOBALS['was_test_http_posts'] );
	}

	public function test_chat_assigns_conversation_shows_window_and_sends_typing_indicator() {
		$controller = new InboxApiController();
		$assign_request = new WP_REST_Request( 'POST', '/conversations/40/assign' );
		$assign_request->set_param( 'id', 40 );
		$assign_request->set_param( 'user_id', 9 );
		$assigned = $controller->assign_conversation( $assign_request );

		$window_request = new WP_REST_Request( 'GET', '/conversations/40/window' );
		$window_request->set_param( 'id', 40 );
		$window = $controller->get_window_status( $window_request );

		$typing_request = new WP_REST_Request( 'POST', '/conversations/40/typing' );
		$typing_request->set_param( 'id', 40 );
		$typing = $controller->send_typing_indicator( $typing_request );
		$typing_body = json_decode( $GLOBALS['was_test_http_posts'][0]['args']['body'], true );

		$this->assert_same( 200, $assigned->get_status() );
		$this->assert_same( 9, (int) $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'conversations' ) . ' WHERE id = 40' )->assigned_user_id );
		$this->assert_same( 200, $window->get_status() );
		$this->assert_same( 'open', $window->get_data()['status'] );
		$this->assert_same( 200, $typing->get_status() );
		$this->assert_same( 'wamid-inbound-1', $typing_body['message_id'] );
		$this->assert_same( 'text', $typing_body['typing_indicator']['type'] );
	}
}

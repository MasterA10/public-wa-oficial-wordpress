<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Inbox\ConversationWindowService;

class ConversationWindowContractsTest extends WAS_Router_TestCase {

	protected function set_up() {
		global $wpdb;
		TenantContext::set_tenant_id( 1 );
		$wpdb->insert( TableNameResolver::get_table_name( 'conversations' ), [ 'id' => 40, 'tenant_id' => 1, 'status' => 'open', 'customer_service_window_expires_at' => gmdate( 'Y-m-d H:i:s', time() + 86400 ), 'created_at' => current_time( 'mysql', true ) ] );
	}

	public function test_window_state_reports_open_closing_soon_and_closed_contracts() {
		$service = new ConversationWindowService();
		$open = $service->getWindowState( (object) [ 'id' => 40, 'customer_service_window_expires_at' => gmdate( 'Y-m-d H:i:s', time() + 7200 ) ] );
		$soon = $service->getWindowState( (object) [ 'id' => 40, 'customer_service_window_expires_at' => gmdate( 'Y-m-d H:i:s', time() + 1800 ) ] );
		$closed = $service->getWindowState( (object) [ 'id' => 40, 'customer_service_window_expires_at' => gmdate( 'Y-m-d H:i:s', time() - 1 ) ] );

		$this->assert_same( 'open', $open['status'] );
		$this->assert_true( $open['can_send_freeform'] );
		$this->assert_same( 'closing_soon', $soon['status'] );
		$this->assert_true( $soon['is_open'] );
		$this->assert_same( 'closed', $closed['status'] );
		$this->assert_false( $closed['can_send_freeform'] );
	}

	public function test_assert_can_send_freeform_allows_open_and_throws_for_closed_window() {
		global $wpdb;
		$service = new ConversationWindowService();
		$service->assertCanSendFreeform( 1, 40 );

		$wpdb->update( TableNameResolver::get_table_name( 'conversations' ), [ 'customer_service_window_expires_at' => gmdate( 'Y-m-d H:i:s', time() - 1 ) ], [ 'id' => 40, 'tenant_id' => 1 ] );
		$exception = $this->assert_throws( fn() => $service->assertCanSendFreeform( 1, 40 ) );
		$this->assert_true( str_contains( $exception->getMessage(), 'janela de atendimento de 24 horas' ) );
	}

	public function test_refresh_from_inbound_renews_window_and_ignores_older_messages() {
		global $wpdb;
		$service = new ConversationWindowService();
		$new_timestamp = time();
		$service->refreshFromInboundMessage( 1, 40, 'wamid-new', $new_timestamp );
		$after_new = $wpdb->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'conversations' ) . ' WHERE id = 40' );
		$expires_after_new = $after_new->customer_service_window_expires_at;

		$service->refreshFromInboundMessage( 1, 40, 'wamid-old', $new_timestamp - 7200 );
		$after_old = $wpdb->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'conversations' ) . ' WHERE id = 40' );

		$this->assert_same( 'wamid-new', $after_new->last_inbound_wa_message_id );
		$this->assert_same( 'wamid-new', $after_old->last_inbound_wa_message_id );
		$this->assert_same( $expires_after_new, $after_old->customer_service_window_expires_at );
		$this->assert_same( 'open', $after_old->customer_service_window_status );
	}
}

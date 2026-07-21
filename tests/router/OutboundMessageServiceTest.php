<?php

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;
use WAS\Inbox\OutboundMessageService;
use WAS\Meta\TokenVault;

class OutboundMessageServiceTest extends WAS_Router_TestCase {

    protected function set_up() {
        global $wpdb;

        TenantContext::set_tenant_id(1);

        $wpdb->insert(TableNameResolver::get_table_name('tenants'), [
            'id' => 1,
            'name' => 'Agenda',
            'slug' => 'agenda',
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('whatsapp_accounts'), [
            'id' => 7,
            'tenant_id' => 1,
            'waba_id' => 'waba-primary',
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('whatsapp_accounts'), [
            'id' => 8,
            'tenant_id' => 1,
            'waba_id' => 'waba-conversation',
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('whatsapp_phone_numbers'), [
            'id' => 10,
            'tenant_id' => 1,
            'whatsapp_account_id' => 7,
            'phone_number_id' => 'meta-phone-primary',
            'is_default' => 1,
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('whatsapp_phone_numbers'), [
            'id' => 11,
            'tenant_id' => 1,
            'whatsapp_account_id' => 8,
            'phone_number_id' => 'meta-phone-conversation',
            'is_default' => 0,
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('meta_tokens'), [
            'id' => 20,
            'tenant_id' => 1,
            'whatsapp_account_id' => 8,
            'access_token_encrypted' => TokenVault::encrypt('conversation-token'),
            'status' => 'active',
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('contacts'), [
            'id' => 30,
            'tenant_id' => 1,
            'wa_id' => '5511999999999',
            'phone' => '5511999999999',
            'normalized_phone' => '5511999999999',
            'phone_status' => 'confirmed_by_wa_id',
            'profile_name' => 'Cliente',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('conversations'), [
            'id' => 40,
            'tenant_id' => 1,
            'contact_id' => 30,
            'phone_number_id' => 'meta-phone-conversation',
            'status' => 'open',
            'last_inbound_wa_message_id' => 'wamid.inbound-conversation',
            'customer_service_window_expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);
        $GLOBALS['was_test_http_response'] = [
            'code' => 200,
            'body' => [ 'messages' => [ [ 'id' => 'wamid-conversation-phone' ] ] ],
        ];
    }

    public function test_text_uses_the_phone_number_linked_to_the_conversation_not_the_tenant_default() {
        $result = (new OutboundMessageService())->send_text(40, 'Enviada pelo número correto');

        $typing_call = $GLOBALS['was_test_http_posts'][0];
        $call = $GLOBALS['was_test_http_posts'][1];
        $typing_body = json_decode($typing_call['args']['body'], true);
        $body = json_decode($call['args']['body'], true);

        $this->assert_true($result['success']);
        $this->assert_same('read', $typing_body['status']);
        $this->assert_same('wamid.inbound-conversation', $typing_body['message_id']);
        $this->assert_same('text', $typing_body['typing_indicator']['type']);
        $this->assert_true(str_contains($call['url'], '/v25.0/meta-phone-conversation/messages'));
        $this->assert_false(str_contains($call['url'], 'meta-phone-primary'));
        $this->assert_same('Bearer conversation-token', $call['args']['headers']['Authorization']);
        $this->assert_same('5511999999999', $body['to']);
        $this->assert_same('Enviada pelo número correto', $body['text']['body']);
    }
}

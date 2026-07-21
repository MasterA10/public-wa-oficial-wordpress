<?php

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;
use WAS\Router\RouterApiController;

class ExternalSendWebhookServiceTest extends WAS_Router_TestCase {

    protected function set_up() {
        global $wpdb;

        TenantContext::set_tenant_id(1);

        update_option('was_external_send_webhook_secret', 'external-secret');

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
            'phone_number_id' => 'meta-phone-webhook',
            'is_default' => 0,
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('meta_tokens'), [
            'id' => 20,
            'tenant_id' => 1,
            'whatsapp_account_id' => 8,
            'access_token_encrypted' => \WAS\Meta\TokenVault::encrypt('webhook-token'),
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
            'profile_name' => 'Cliente externo',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('conversations'), [
            'id' => 40,
            'tenant_id' => 1,
            'contact_id' => 30,
            'phone_number_id' => 'meta-phone-webhook',
            'status' => 'open',
            'customer_service_window_expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);
        $GLOBALS['was_test_http_response'] = [
            'code' => 200,
            'body' => [ 'messages' => [ [ 'id' => 'wamid-external-webhook' ] ] ],
        ];
    }

    public function test_meta_shaped_webhook_uses_the_declared_phone_number() {
        $request = new WP_REST_Request('POST', '/v1/webhooks/send');
        $request->set_body(wp_json_encode($this->payload()));
        $request->set_header('X-WAS-Webhook-Secret', 'external-secret');

        $response = (new RouterApiController())->receive_external_send_webhook($request);
        $call = $GLOBALS['was_test_http_posts'][0];
        $body = json_decode($call['args']['body'], true);
        $data = $response->get_data();

        $this->assert_same(200, $response->get_status());
        $this->assert_true($data['success']);
        $this->assert_same('meta-phone-webhook', $data['meta_phone_number_id']);
        $this->assert_true(str_contains($call['url'], '/v25.0/meta-phone-webhook/messages'));
        $this->assert_same('Bearer webhook-token', $call['args']['headers']['Authorization']);
        $this->assert_same('5511999999999', $body['to']);
        $this->assert_same('Mensagem recebida da outra aplicacao', $body['text']['body']);

        $local_messages = $GLOBALS['wpdb']->tables[TableNameResolver::get_table_name('messages')];
        $this->assert_count(1, $local_messages);
        $this->assert_same(1, (int) $local_messages[0]['tenant_id']);
        $this->assert_same(40, (int) $local_messages[0]['conversation_id']);
        $this->assert_same('outbound', $local_messages[0]['direction']);
        $this->assert_same('5511999999999', $GLOBALS['wpdb']->tables[TableNameResolver::get_table_name('contacts')][0]['wa_id']);
    }

    public function test_webhook_rejects_invalid_secret_before_sending() {
        $request = new WP_REST_Request('POST', '/v1/webhooks/send');
        $request->set_body(wp_json_encode($this->payload()));
        $request->set_header('X-WAS-Webhook-Secret', 'wrong-secret');

        $response = (new RouterApiController())->receive_external_send_webhook($request);
        $data = $response->get_data();

        $this->assert_same(401, $response->get_status());
        $this->assert_same('external_send_webhook_unauthorized', $data['error']);
        $this->assert_count(0, $GLOBALS['was_test_http_posts']);
    }

    public function test_meta_shaped_image_webhook_uses_declared_phone_and_tenant() {
        $payload = $this->payload();
        $message = &$payload['entry'][0]['changes'][0]['value']['messages'][0];
        unset($message['text']);
        $message['type'] = 'image';
        $message['image'] = [
            'id' => 'meta-image-from-external-app',
            'caption' => 'Imagem da obrigação',
        ];

        $request = new WP_REST_Request('POST', '/v1/webhooks/send');
        $request->set_body(wp_json_encode($payload));
        $request->set_header('X-WAS-Webhook-Secret', 'external-secret');

        $response = (new RouterApiController())->receive_external_send_webhook($request);
        $call = $GLOBALS['was_test_http_posts'][0];
        $body = json_decode($call['args']['body'], true);
        $data = $response->get_data();

        $this->assert_same(200, $response->get_status());
        $this->assert_true($data['success']);
        $this->assert_same('meta-phone-webhook', $data['meta_phone_number_id']);
        $this->assert_true(str_contains($call['url'], '/v25.0/meta-phone-webhook/messages'));
        $this->assert_same('Bearer webhook-token', $call['args']['headers']['Authorization']);
        $this->assert_same('5511999999999', $body['to']);
        $this->assert_same('image', $body['type']);
        $this->assert_same('meta-image-from-external-app', $body['image']['id']);

        $outbound = $GLOBALS['wpdb']->tables[TableNameResolver::getOutboundMessagesTable()][0];
        $this->assert_same(1, (int) $outbound['tenant_id']);
        $this->assert_same(11, (int) $outbound['phone_number_id']);
        $this->assert_same('image', $outbound['message_type']);
    }

    private function payload() {
        return [
            'object' => 'whatsapp_business_account',
            'tenant_id' => 1,
            'entry' => [[
                'id' => 'waba-conversation',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '5531990000000',
                            'phone_number_id' => 'meta-phone-webhook',
                        ],
                        'messages' => [[
                            'id' => 'external-message-1',
                            'to' => '5511999999999',
                            'timestamp' => '1784214718',
                            'type' => 'text',
                            'text' => [ 'body' => 'Mensagem recebida da outra aplicacao' ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}

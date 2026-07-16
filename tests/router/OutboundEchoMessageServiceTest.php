<?php

use WAS\Core\TableNameResolver;
use WAS\Meta\TokenVault;
use WAS\WhatsApp\WebhookProcessor;

class OutboundEchoMessageServiceTest extends WAS_Router_TestCase {

    protected function set_up() {
        global $wpdb;

        $wpdb->insert(TableNameResolver::get_table_name('tenants'), [
            'id' => 1,
            'name' => 'Agenda',
            'slug' => 'agenda',
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('whatsapp_accounts'), [
            'id' => 5,
            'tenant_id' => 1,
            'waba_id' => 'meta-waba-1',
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('whatsapp_phone_numbers'), [
            'id' => 10,
            'tenant_id' => 1,
            'whatsapp_account_id' => 5,
            'phone_number_id' => 'meta-phone-1',
            'display_phone_number' => '553171183457',
            'created_at' => current_time('mysql', true),
        ]);
        $wpdb->insert(TableNameResolver::get_table_name('contacts'), [
            'id' => 20,
            'tenant_id' => 1,
            'wa_id' => '553199919648',
            'phone' => '553199919648',
            'normalized_phone' => '553199919648',
            'phone_status' => 'confirmed_by_wa_id',
            'profile_name' => 'Cliente',
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);
    }

    public function test_text_echo_is_saved_as_outbound_for_the_recipient_conversation() {
        global $wpdb;

        (new WebhookProcessor())->process($this->payload([
            'id' => 'wamid-echo-text',
            'type' => 'text',
            'text' => ['body' => 'Mensagem pelo app'],
        ]));

        $messages = $wpdb->tables[TableNameResolver::get_table_name('messages')];
        $conversations = $wpdb->tables[TableNameResolver::get_table_name('conversations')];

        $this->assert_count(1, $messages);
        $this->assert_same('outbound', $messages[0]['direction']);
        $this->assert_same('Mensagem pelo app', $messages[0]['text_body']);
        $this->assert_same('wamid-echo-text', $messages[0]['wa_message_id']);
        $this->assert_same(20, (int) $conversations[0]['contact_id']);
    }

    public function test_echo_is_idempotent_when_the_crm_already_saved_the_same_wamid() {
        global $wpdb;

        $payload = $this->payload([
            'id' => 'wamid-echo-duplicate',
            'type' => 'text',
            'text' => ['body' => 'Uma vez'],
        ]);

        (new WebhookProcessor())->process($payload);
        (new WebhookProcessor())->process($payload);

        $messages = $wpdb->tables[TableNameResolver::get_table_name('messages')];
        $this->assert_count(1, $messages);
    }

    public function test_audio_echo_is_saved_as_outbound_and_media_is_downloaded() {
        global $wpdb;

        $wpdb->insert(TableNameResolver::get_table_name('meta_tokens'), [
            'id' => 30,
            'tenant_id' => 1,
            'whatsapp_account_id' => 5,
            'access_token_encrypted' => TokenVault::encrypt('waba-token'),
            'status' => 'active',
            'created_at' => current_time('mysql', true),
        ]);
        $GLOBALS['was_test_http_response_queue'] = [
            [
                'code' => 200,
                'body' => [
                    'url' => 'https://lookaside.fbsbx.com/media/audio',
                    'mime_type' => 'audio/ogg; codecs=opus',
                ],
            ],
            [
                'code' => 200,
                'body' => 'audio-bytes',
            ],
        ];

        (new WebhookProcessor())->process($this->payload([
            'id' => 'wamid-echo-audio',
            'type' => 'audio',
            'audio' => [
                'id' => 'media-audio-1',
                'mime_type' => 'audio/ogg; codecs=opus',
                'voice' => true,
            ],
        ]));

        $messages = $wpdb->tables[TableNameResolver::get_table_name('messages')];
        $media = $wpdb->tables[TableNameResolver::get_table_name('media')];

        $this->assert_count(1, $messages);
        $this->assert_same('outbound', $messages[0]['direction']);
        $this->assert_same('audio', $messages[0]['message_type']);
        $this->assert_count(1, $media);
        $this->assert_same('outbound', $media[0]['direction']);
        $this->assert_same('downloaded', $media[0]['status']);
        $this->assert_true(str_ends_with($GLOBALS['was_test_uploads'][0]['name'], '.ogg'));
    }

    private function payload(array $echo) {
        $echo['from'] = '553171183457';
        $echo['to'] = '553199919648';
        $echo['timestamp'] = '1784214718';

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'meta-waba-1',
                'changes' => [[
                    'field' => 'smb_message_echoes',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '553171183457',
                            'phone_number_id' => 'meta-phone-1',
                        ],
                        'contacts' => [[
                            'wa_id' => '553199919648',
                            'user_id' => 'BR.1545217153813830',
                        ]],
                        'message_echoes' => [$echo],
                    ],
                ]],
            ]],
        ];
    }
}

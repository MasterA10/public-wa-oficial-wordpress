<?php
namespace WAS\Inbox;

use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class InboundMessageService {
    private $contact_repo;
    private $conversation_repo;
    private $message_repo;

    public function __construct(
        ContactRepository $contact_repo,
        ConversationRepository $conversation_repo,
        MessageRepository $message_repo
    ) {
        $this->contact_repo = $contact_repo;
        $this->conversation_repo = $conversation_repo;
        $this->message_repo = $message_repo;
    }

    /**
     * Processa uma mensagem inbound normalizada.
     * 
     * @param array $dto DTO normalizado vindo do WebhookProcessor.
     * @return bool
     */
    public function handle($dto) {
        // 1. Garantir que o contexto do tenant está correto (resolvido pelo WebhookProcessor)
        if (isset($dto['tenant_id'])) {
            TenantContext::set_tenant_id($dto['tenant_id']);
        }

        // 2. Verificar duplicidade
        if ($this->message_repo->find_by_wa_message_id($dto['wa_message_id'])) {
            return true; // Já processada
        }

        // 3. Encontrar contato (já processado pelo WebhookProcessor via ContactService)
        $contact = $this->contact_repo->find_by_wa_id($dto['tenant_id'], $dto['from']);

        if ($contact) {
            // Se recebemos mensagem, o número é válido no WhatsApp
            if ($contact->phone_status !== 'confirmed_by_inbound' && $contact->phone_status !== 'confirmed_by_wa_id') {
                $this->contact_repo->confirm_wa_id($contact->id, $dto['from'], 'confirmed_by_inbound');
            }
        } else {
            return false;
        }

        // 4. Encontrar ou criar conversa aberta
        $conversation = $this->conversation_repo->find_or_create_open_conversation(
            $contact->id,
            $dto['phone_number_id'] ?? ''
        );

        if (!$conversation) {
            return false;
        }

        // 5. Salvar a mensagem
        $type = $dto['message_type'] ?? 'text';
        $body = $dto['text_body'] ?? '';

        // Resolver reply_to_message_id local (Citação)
        $reply_to_message_id = null;
        $reply_to_wa_message_id = $dto['reply_to_wa_message_id'] ?? null;

        if ($reply_to_wa_message_id) {
            $original_msg = $this->message_repo->find_by_wa_message_id($reply_to_wa_message_id);
            if ($original_msg) {
                $reply_to_message_id = $original_msg->id;
            }
        }

        $message_data = [
            'conversation_id'         => $conversation->id,
            'wa_message_id'           => $dto['wa_message_id'],
            'message_type'            => $type,
            'text_body'               => $body,
            'status'                  => 'received',
            'reply_to_message_id'     => $reply_to_message_id,
            'reply_to_wa_message_id'  => $reply_to_wa_message_id,
            'context_from'            => $dto['context_from'] ?? null,
            'context_payload'         => $dto['context_payload'] ?? null,
            'button_text'             => $dto['button_text'] ?? null,
            'button_payload'          => $dto['button_payload'] ?? null,
            'interactive_type'        => $dto['interactive_type'] ?? null,
            'interactive_id'          => $dto['interactive_id'] ?? null,
            'interactive_title'       => $dto['interactive_title'] ?? null,
            'interactive_description' => $dto['interactive_description'] ?? null,
            'latitude'                => $dto['latitude'] ?? null,
            'longitude'               => $dto['longitude'] ?? null,
            'location_name'           => $dto['location_name'] ?? null,
            'location_address'        => $dto['location_address'] ?? null,
            'contacts_json'           => $dto['contacts_json'] ?? null,
            'order_json'              => $dto['order_json'] ?? null,
            'raw_payload'             => isset($dto['raw_message']) ? wp_json_encode($dto['raw_message']) : null,
        ];

        // 5.1. Tratar Referral (Anúncios)
        if (!empty($dto['referral'])) {
            global $wpdb;
            $ref_table = \WAS\Core\TableNameResolver::getMessageReferralsTable();
            $ref_data = array_merge($dto['referral'], [
                'tenant_id'       => $dto['tenant_id'],
                'conversation_id' => $conversation->id,
                'created_at'      => current_time('mysql', 1),
            ]);
            
            $wpdb->insert($ref_table, $ref_data);
            $referral_id = $wpdb->insert_id;
            $message_data['referral_id'] = $referral_id;

            // Atualizar conversa com origem de anúncio
            $conv_table = \WAS\Core\TableNameResolver::get_table_name('conversations');
            $conv_update = [
                'origin_type'   => 'paid',
                'origin_source' => 'ctwa_ad',
                'last_referral_id' => $referral_id,
            ];
            if (!empty($dto['referral']['ctwa_clid'])) {
                $conv_update['ctwa_clid'] = $dto['referral']['ctwa_clid'];
            }
            // Se for o primeiro referral, marca como first_referral_id
            if (empty($conversation->first_referral_id)) {
                $conv_update['first_referral_id'] = $referral_id;
            }
            $wpdb->update($conv_table, $conv_update, ['id' => $conversation->id]);
        }

        $message_id = $this->message_repo->create_inbound($message_data);

        if ($message_id) {
            // 6. Se for mídia, baixar arquivo
            if (in_array($type, ['image', 'video', 'audio', 'document', 'sticker']) && !empty($dto['meta_media_id'])) {
                try {
                    $media_service = new \WAS\WhatsApp\InboundMediaService();
                    $media_service->handle_inbound_media(
                        $dto['tenant_id'],
                        $conversation->id,
                        $message_id,
                        $dto['meta_media_id'],
                        $type,
                        $dto['mime_type'] ?? ''
                    );
                } catch (\Throwable $e) {
                    \WAS\Core\SystemLogger::logException($e, ['context' => 'InboundMessageService::handle_media', 'message_id' => $message_id]);
                }
            }

            // 7. Atualizar timestamp da conversa e janela de atendimento
            \WAS\Core\SystemLogger::logInfo('InboundMessageService: Finalizando processamento, atualizando janela.', [
                'conversation_id' => $conversation->id,
                'wa_message_id'   => $dto['wa_message_id']
            ]);

            $this->conversation_repo->update_last_message_at($conversation->id);
            
            $window_service = new \WAS\Inbox\ConversationWindowService();
            $window_service->refreshFromInboundMessage(
                (int) $dto['tenant_id'],
                $conversation->id,
                $dto['wa_message_id'],
                $dto['timestamp'] ?? time()
            );
            
            return true;
        }

        return false;
    }
}

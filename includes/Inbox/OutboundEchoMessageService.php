<?php
namespace WAS\Inbox;

use WAS\Auth\TenantContext;
use WAS\WhatsApp\InboundMediaService;
use WAS\WhatsApp\WhatsAppInboundMessageNormalizer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persists messages sent from the WhatsApp Business App or a linked device.
 *
 * Meta calls these notifications message echoes. Their `from` value is the
 * business number, so the conversation recipient must be resolved from `to`.
 */
class OutboundEchoMessageService {
    private $contact_repo;
    private $conversation_repo;
    private $message_repo;

    public function __construct(
        ?ContactRepository $contact_repo = null,
        ?ConversationRepository $conversation_repo = null,
        ?MessageRepository $message_repo = null
    ) {
        $this->contact_repo      = $contact_repo ?: new ContactRepository();
        $this->conversation_repo = $conversation_repo ?: new ConversationRepository();
        $this->message_repo      = $message_repo ?: new MessageRepository();
    }

    /**
     * @param array $dto Normalized echo data plus tenant/phone context.
     * @return bool True when persisted or already present.
     */
    public function handle(array $dto) {
        if (!empty($dto['tenant_id'])) {
            TenantContext::set_tenant_id((int) $dto['tenant_id']);
        }

        $wa_message_id = $dto['wa_message_id'] ?? null;
        $recipient     = $dto['to'] ?? $dto['recipient'] ?? null;

        if (!$wa_message_id || !$recipient || !TenantContext::get_tenant_id()) {
            return false;
        }

        // The CRM already stores messages sent through its own API flow. The
        // later echo must not create a second row for the same WhatsApp ID.
        if ($this->message_repo->find_by_wa_message_id($wa_message_id)) {
            return true;
        }

        $contact = $this->contact_repo->find_by_wa_id(
            (int) $dto['tenant_id'],
            (string) $recipient
        );

        if (!$contact) {
            $contact = $this->contact_repo->find_or_create_by_wa_id(
                (string) $recipient,
                $dto['profile_name'] ?? '',
                (string) $recipient
            );
        }

        if (!$contact) {
            \WAS\Core\SystemLogger::logWarning('Echo outbound sem contato correspondente.', [
                'tenant_id' => $dto['tenant_id'] ?? null,
                'recipient' => $recipient,
                'wa_message_id' => $wa_message_id,
            ]);
            return false;
        }

        $conversation = $this->conversation_repo->find_or_create_open_conversation(
            $contact->id,
            $dto['phone_number_id'] ?? ''
        );

        if (!$conversation) {
            \WAS\Core\SystemLogger::logWarning('Echo outbound sem conversa correspondente.', [
                'tenant_id' => $dto['tenant_id'] ?? null,
                'recipient' => $recipient,
                'phone_number_id' => $dto['phone_number_id'] ?? null,
                'wa_message_id' => $wa_message_id,
            ]);
            return false;
        }

        $type = $dto['message_type'] ?? 'unknown';
        $message_data = [
            'conversation_id'         => $conversation->id,
            'wa_message_id'           => $wa_message_id,
            'message_type'            => $type,
            'text_body'               => $dto['text_body'] ?? '',
            'status'                  => 'sent',
            'reply_to_message_id'     => $this->resolve_reply_id($dto['reply_to_wa_message_id'] ?? null),
            'reply_to_wa_message_id'  => $dto['reply_to_wa_message_id'] ?? null,
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

        $message_id = $this->message_repo->create_outbound($message_data);
        if (!$message_id) {
            return false;
        }

        if (in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)
            && !empty($dto['meta_media_id'])) {
            try {
                (new InboundMediaService())->handle_inbound_media(
                    (int) $dto['tenant_id'],
                    (int) $conversation->id,
                    (int) $message_id,
                    $dto['meta_media_id'],
                    $type,
                    $dto['mime_type'] ?? '',
                    'outbound',
                    $dto['phone_number_id'] ?? null
                );
            } catch (\Throwable $e) {
                \WAS\Core\SystemLogger::logException($e, [
                    'context' => 'OutboundEchoMessageService::handle_media',
                    'message_id' => $message_id,
                ]);
            }
        }

        $this->conversation_repo->update_last_message_at($conversation->id);
        $this->conversation_repo->mark_outbound_sent($conversation->id);

        return true;
    }

    private function resolve_reply_id($wa_message_id) {
        if (!$wa_message_id) {
            return null;
        }

        $message = $this->message_repo->find_by_wa_message_id($wa_message_id);
        return $message ? (int) $message->id : null;
    }

    /**
     * Converts the raw Meta echo into the fields used by the Inbox schema.
     */
    public static function normalize(array $echo) {
        return (new WhatsAppInboundMessageNormalizer())->normalize($echo);
    }
}

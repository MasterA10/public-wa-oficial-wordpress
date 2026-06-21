<?php
namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\Inbox\MessageRepository;
use WAS\Core\SystemLogger;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço responsável por exibir o indicador de "digitando..." e marcar mensagens como lidas na Meta Cloud API.
 */
final class TypingIndicatorService {
    private $api_client;
    private $token_service;
    private $message_repo;
    private $phone_service;

    public function __construct() {
        $this->api_client    = new MetaApiClient();
        $this->token_service = new TokenService();
        $this->message_repo  = new MessageRepository();
        $this->phone_service = new PhoneNumberService();
    }

    /**
     * Exibe "digitando..." para a última mensagem recebida da conversa.
     *
     * @param int $conversationId
     * @param int|null $messageId Opcional. Se não passado, usa o último inbound da conversa.
     * @return array
     */
    public function show_typing(int $conversationId, ?int $messageId = null): array {
        $tenant_id = TenantContext::get_tenant_id();
        $conv_repo = new \WAS\Inbox\ConversationRepository();

        // 1. Busca a conversa
        $conversation = $conv_repo->get_by_id($conversationId);
        if (!$conversation) {
            return ['success' => false, 'error' => 'Conversa não encontrada'];
        }

        // 2. Determinar o wa_message_id a ser usado
        $wa_message_id = null;

        if ($messageId) {
            $message = $this->message_repo->find_by_id($messageId);
            if ($message && $message->direction === 'inbound') {
                $wa_message_id = $message->wa_message_id;
            }
        }

        if (!$wa_message_id) {
            $wa_message_id = $conversation->last_inbound_wa_message_id;
        }

        if (!$wa_message_id) {
            return ['success' => false, 'error' => 'Nenhuma mensagem recebida para acionar o indicador.'];
        }

        // 3. Validar Política de Cooldown
        if (!$this->can_send_typing($conversation)) {
            return ['success' => false, 'skipped' => true, 'reason' => 'cooldown'];
        }

        // 4. Busca configurações de envio
        $phone_number_id = $this->phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) {
            return ['success' => false, 'error' => 'Configurações de WhatsApp incompletas.'];
        }

        // 5. Monta o payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $wa_message_id,
            'typing_indicator'  => [
                'type' => 'text'
            ]
        ];

        $response = $this->api_client->postJson('messages.send', ['phone_number_id' => $phone_number_id], $payload, $token);

        if ($response['success'] ?? false) {
            $conv_repo->mark_typing_sent($conversationId);
            return ['success' => true];
        }

        return $response;
    }

    /**
     * Valida se o indicador pode ser enviado baseado em cooldowns.
     */
    private function can_send_typing($conversation): bool {
        $now = time();

        // 1. Cooldown entre indicadores (10 segundos)
        if (!empty($conversation->last_typing_sent_at)) {
            $last_typing = strtotime($conversation->last_typing_sent_at);
            if (($now - $last_typing) < 10) {
                return false;
            }
        }

        // 2. Cooldown após enviar mensagem (3 segundos)
        if (!empty($conversation->last_outbound_sent_at)) {
            $last_outbound = strtotime($conversation->last_outbound_sent_at);
            if (($now - $last_outbound) < 3) {
                return false;
            }
        }

        return true;
    }
}

<?php
namespace WAS\Inbox;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\WhatsApp\PhoneNumberService;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class OutboundMessageService {
    private $message_repo;
    private $conversation_repo;
    private $phone_service;
    private $token_service;
    private $api_client;

    public function __construct() {
        $this->message_repo = new MessageRepository();
        $this->conversation_repo = new ConversationRepository();
        $this->phone_service = new PhoneNumberService();
        $this->token_service = new TokenService();
        $this->api_client = new MetaApiClient();
    }

    /**
     * Envia uma mensagem de texto livre.
     */
    public function send_text($conversation_id, $text, $reply_to_message_id = null) {
        $tenant_id = TenantContext::get_tenant_id();

        \WAS\Core\SystemLogger::logInfo('OutboundMessageService::send_text iniciado.', [
            'conversation_id'      => $conversation_id,
            'tenant_id'            => $tenant_id,
            'text_length'          => strlen($text),
            'reply_to_message_id'  => $reply_to_message_id,
        ]);
        
        $conversation = $this->conversation_repo->get_by_id($conversation_id);
        if (!$conversation) {
            \WAS\Core\SystemLogger::logError('send_text: Conversa não encontrada.', ['conversation_id' => $conversation_id]);
            return ['success' => false, 'error' => 'Conversa não encontrada'];
        }

        $contact_repo = new \WAS\Inbox\ContactRepository();
        $contact = $contact_repo->get_by_id($conversation->contact_id);
        if (!$contact) return ['success' => false, 'error' => 'Contato não encontrado'];

        // 1. Validar Janela de Atendimento de 24 horas
        $window_service = new \WAS\Inbox\ConversationWindowService();
        try {
            $window_service->assertCanSendFreeform($tenant_id, $conversation_id);
        } catch (\RuntimeException $e) {
            \WAS\Core\SystemLogger::logWarning('send_text: Tentativa de envio fora da janela.', [
                'conversation_id' => $conversation_id,
                'error'           => $e->getMessage()
            ]);
            return [
                'success'    => false, 
                'error'      => $e->getMessage(),
                'error_code' => 'CUSTOMER_SERVICE_WINDOW_CLOSED'
            ];
        }

        $phone_number_id = $this->phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) {
            $missing_config = [];
            if (!$phone_number_id) $missing_config[] = 'WhatsApp Phone Number ID não configurado ou não é o padrão.';
            if (!$token) $missing_config[] = 'Meta Access Token não encontrado, expirado ou inválido.';
            
            $error_message = 'Falha de configuração ao tentar enviar mensagem: ' . implode(' ', $missing_config);

            \WAS\Core\SystemLogger::logError($error_message, [
                'action'          => 'send_text',
                'conversation_id' => $conversation_id,
                'contact_wa_id'   => $contact->wa_id,
                'tenant_id'       => $tenant_id,
                'has_phone'       => !!$phone_number_id,
                'has_token'       => !!$token
            ]);
            
            return ['success' => false, 'error' => $error_message];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $contact->wa_id,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $text,
            ],
        ];

        // Resolver Contexto de Resposta
        $reply_to_wa_message_id = null;
        if ($reply_to_message_id) {
            \WAS\Core\SystemLogger::logInfo('send_text: Resolvendo contexto de resposta...', [
                'reply_to_message_id' => $reply_to_message_id,
                'conversation_id'     => $conversation_id,
            ]);
            $original_msg = $this->message_repo->find_by_id($reply_to_message_id);
            if ($original_msg && !empty($original_msg->wa_message_id)) {
                $reply_to_wa_message_id = $original_msg->wa_message_id;
                $payload['context'] = [
                    'message_id' => $reply_to_wa_message_id
                ];
                \WAS\Core\SystemLogger::logInfo('send_text: Contexto de resposta resolvido com sucesso.', [
                    'reply_to_message_id'    => $reply_to_message_id,
                    'reply_to_wa_message_id' => $reply_to_wa_message_id,
                ]);
            } else {
                \WAS\Core\SystemLogger::logWarning('send_text: Mensagem original para reply NÃO encontrada ou sem wa_message_id.', [
                    'reply_to_message_id' => $reply_to_message_id,
                    'original_msg_found'  => !!$original_msg,
                    'wa_message_id'       => $original_msg->wa_message_id ?? null,
                ]);
            }
        }

        $response = $this->api_client->postJson(
            'messages.send',
            ['phone_number_id' => $phone_number_id],
            $payload,
            $token
        );

        if ($response['success']) {
            $wa_message_id = $response['messages'][0]['id'] ?? null;
            
            // Aprendizado: Se a Meta devolveu um wa_id (canônico), atualizamos o contato
            if (!empty($response['contacts'][0]['wa_id'])) {
                $confirmed_wa_id = $response['contacts'][0]['wa_id'];
                if ($confirmed_wa_id !== $contact->wa_id) {
                    $contact_repo->confirm_wa_id($contact->id, $confirmed_wa_id, 'confirmed_by_meta_response');
                }
            }

            \WAS\Compliance\AuditLogger::log('send_text_success', 'conversation', $conversation_id, [
                'wa_message_id'          => $wa_message_id,
                'is_reply'               => !!$reply_to_message_id,
                'reply_to_message_id'    => $reply_to_message_id,
                'reply_to_wa_message_id' => $reply_to_wa_message_id,
            ]);

            // Salvar no repositório local
            $saved_id = $this->message_repo->create_outbound([
                'conversation_id'        => $conversation_id,
                'wa_message_id'          => $wa_message_id,
                'message_type'           => 'text',
                'text_body'              => $text,
                'status'                 => 'sent',
                'reply_to_message_id'    => $reply_to_message_id,
                'reply_to_wa_message_id' => $reply_to_wa_message_id,
                'raw_payload'            => wp_json_encode($payload)
            ]);

            \WAS\Core\SystemLogger::logInfo('send_text: Mensagem salva no banco local.', [
                'local_id'               => $saved_id,
                'wa_message_id'          => $wa_message_id,
                'reply_to_message_id'    => $reply_to_message_id,
                'reply_to_wa_message_id' => $reply_to_wa_message_id,
            ]);

            $this->conversation_repo->update_last_message_at($conversation_id);
            $this->conversation_repo->mark_outbound_sent($conversation_id);
            
            return [
                'success'       => true, 
                'wa_message_id' => $wa_message_id, 
                'id'            => $saved_id,
                'data'          => ['id' => $saved_id]
            ];
        } else {
            \WAS\Core\SystemLogger::logError('A Meta API recusou o envio da mensagem de texto.', [
                'conversation_id' => $conversation_id,
                'tenant_id'       => $tenant_id,
                'contact_wa_id'   => $contact->wa_id,
                'api_error'       => $response['error'] ?? 'Erro desconhecido',
                'api_code'        => $response['code'] ?? null
            ]);
        }

        return $response;
    }
}

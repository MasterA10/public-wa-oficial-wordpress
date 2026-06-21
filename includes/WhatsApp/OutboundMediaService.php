<?php
namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\Inbox\MessageRepository;
use WAS\Inbox\ConversationRepository;
use WAS\Inbox\MediaRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class OutboundMediaService {
    private $api_client;
    private $token_service;
    private $message_repo;
    private $media_repo;
    private $conversation_repo;
    private $validation_service;

    public function __construct() {
        $this->api_client = new MetaApiClient();
        $this->token_service = new TokenService();
        $this->message_repo = new MessageRepository();
        $this->media_repo = new MediaRepository();
        $this->conversation_repo = new ConversationRepository();
        $this->validation_service = new MediaValidationService();
    }

    public function send_media($conversation_id, $filePath, $mimeType, $mediaType, $caption = '', $filename = '', $reply_to_message_id = null) {
        $tenant_id = TenantContext::get_tenant_id();
        
        $conversation = $this->conversation_repo->get_by_id($conversation_id);
        if (!$conversation) {
            \WAS\Core\SystemLogger::logError('Tentativa de envio de mídia em conversa inexistente.', ['id' => $conversation_id]);
            throw new \Exception('Conversa não encontrada.');
        }

        // 1. Validar Janela de Atendimento de 24 horas
        $window_service = new \WAS\Inbox\ConversationWindowService();
        try {
            $window_service->assertCanSendFreeform($tenant_id, $conversation_id);
        } catch (\RuntimeException $e) {
            \WAS\Core\SystemLogger::logWarning('send_media: Tentativa de envio fora da janela.', [
                'conversation_id' => $conversation_id,
                'error'           => $e->getMessage()
            ]);
            return [
                'success'    => false, 
                'error'      => $e->getMessage(),
                'error_code' => 'CUSTOMER_SERVICE_WINDOW_CLOSED'
            ];
        }

        try {
            $this->validation_service->validate($filePath, $mimeType, $mediaType);
        } catch (\Exception $e) {
            \WAS\Core\SystemLogger::logError('Falha na validação de mídia.', [
                'conversation_id' => $conversation_id,
                'media_type'      => $mediaType,
                'mime'            => $mimeType,
                'error'           => $e->getMessage()
            ]);
            throw $e;
        }

        $phone_service = new PhoneNumberService();
        $phone_number_id = $phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) {
            \WAS\Core\SystemLogger::logError('Configuração de envio de mídia incompleta.', ['tenant_id' => $tenant_id]);
            throw new \Exception('Configuração de envio incompleta.');
        }

        // Usar o nome real do arquivo (não o caminho temporário do PHP)
        $realFilename = $filename ?: basename($filePath);

        // 1. Salvar arquivo no WordPress Uploads para ter URL pública
        $upload_dir = wp_upload_dir();
        $was_dir = $upload_dir['basedir'] . '/was-media/' . $tenant_id;
        if (!file_exists($was_dir)) {
            wp_mkdir_p($was_dir);
        }
        $uniqueName = wp_unique_filename($was_dir, $realFilename);
        $localPath = $was_dir . '/' . $uniqueName;
        $publicUrl = $upload_dir['baseurl'] . '/was-media/' . $tenant_id . '/' . $uniqueName;

        copy($filePath, $localPath);

        // 2. Registrar mídia localmente
        \WAS\Core\SystemLogger::logInfo('Registrando mídia localmente...', ['conversation_id' => $conversation_id, 'media_type' => $mediaType, 'filename' => $realFilename]);
        $media_id = $this->media_repo->create([
            'conversation_id' => $conversation_id,
            'media_type'      => $mediaType,
            'mime_type'       => $mimeType,
            'filename'        => $realFilename,
            'original_filename' => $realFilename,
            'file_size'       => filesize($filePath),
            'storage_path'    => $localPath,
            'public_url'      => $publicUrl,
            'direction'       => 'outbound',
            'status'          => 'validated'
        ]);

        // 3. Upload para Meta
        \WAS\Core\SystemLogger::logInfo('Iniciando upload para Meta...', ['media_id' => $media_id, 'phone_number_id' => $phone_number_id]);
        $uploadResponse = $this->api_client->uploadMedia($phone_number_id, $filePath, $mimeType, $token);
        if (!$uploadResponse['success']) {
            $this->media_repo->update($media_id, ['status' => 'failed', 'error_message' => $uploadResponse['error']]);
            \WAS\Core\SystemLogger::logError('Falha no upload de mídia para a Meta.', [
                'media_id' => $media_id,
                'error'    => $uploadResponse['error'],
                'response' => $uploadResponse
            ]);
            return $uploadResponse;
        }

        $meta_media_id = $uploadResponse['id'];
        \WAS\Core\SystemLogger::logInfo('Upload concluído com sucesso.', ['media_id' => $media_id, 'meta_media_id' => $meta_media_id]);
        $this->media_repo->mark_uploaded($media_id, $meta_media_id);

        // 4. Resolver destinatário
        $contact_repo = new \WAS\Inbox\ContactRepository();
        $contact = $contact_repo->get_by_id($conversation->contact_id);
        if (!$contact) throw new \Exception('Contato não encontrado.');

        // 5. Montar Payload de Mensagem
        $mediaObject = ['id' => $meta_media_id];
        if ($caption && $mediaType !== 'audio') $mediaObject['caption'] = $caption;
        if ($realFilename && $mediaType === 'document') $mediaObject['filename'] = $realFilename;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $contact->wa_id,
            'type'              => $mediaType,
            $mediaType          => $mediaObject
        ];

        // Resolver Contexto de Resposta
        $reply_to_wa_message_id = null;
        if ($reply_to_message_id) {
            \WAS\Core\SystemLogger::logInfo('send_media: Resolvendo contexto de resposta...', [
                'reply_to_message_id' => $reply_to_message_id,
                'conversation_id'     => $conversation_id,
                'media_type'          => $mediaType,
            ]);
            $original_msg = $this->message_repo->find_by_id($reply_to_message_id);
            if ($original_msg && !empty($original_msg->wa_message_id)) {
                $reply_to_wa_message_id = $original_msg->wa_message_id;
                $payload['context'] = [
                    'message_id' => $reply_to_wa_message_id
                ];
                \WAS\Core\SystemLogger::logInfo('send_media: Contexto de resposta resolvido com sucesso.', [
                    'reply_to_message_id'    => $reply_to_message_id,
                    'reply_to_wa_message_id' => $reply_to_wa_message_id,
                ]);
            } else {
                \WAS\Core\SystemLogger::logWarning('send_media: Mensagem original para reply NÃO encontrada ou sem wa_message_id.', [
                    'reply_to_message_id' => $reply_to_message_id,
                    'original_msg_found'  => !!$original_msg,
                    'wa_message_id'       => $original_msg->wa_message_id ?? null,
                ]);
            }
        }

        // 6. Enviar Mensagem
        \WAS\Core\SystemLogger::logInfo('Enviando mensagem de mídia via Cloud API...', ['media_id' => $media_id, 'to' => $contact->wa_id]);
        $sendResponse = $this->api_client->postJson('messages.send', ['phone_number_id' => $phone_number_id], $payload, $token);
        if ($sendResponse['success']) {
            $wa_message_id = $sendResponse['messages'][0]['id'] ?? null;

            \WAS\Core\SystemLogger::logInfo('Mensagem de mídia enviada com sucesso.', [
                'media_id'               => $media_id,
                'wa_message_id'          => $wa_message_id,
                'is_reply'               => !!$reply_to_message_id,
                'reply_to_message_id'    => $reply_to_message_id,
                'reply_to_wa_message_id' => $reply_to_wa_message_id,
            ]);
            
            // 7. Registrar mensagem e vincular mídia
            $message_id = $this->message_repo->create_outbound([
                'conversation_id'        => $conversation_id,
                'wa_message_id'          => $wa_message_id,
                'message_type'           => $mediaType,
                'text_body'              => $caption ?: $realFilename,
                'status'                 => 'sent',
                'reply_to_message_id'    => $reply_to_message_id,
                'reply_to_wa_message_id' => $reply_to_wa_message_id,
                'raw_payload'            => wp_json_encode($payload)
            ]);

            $this->media_repo->attach_message($media_id, $message_id);
            $this->media_repo->update($media_id, ['status' => 'sent']);
            $this->conversation_repo->update_last_message_at($conversation_id);

            \WAS\Compliance\AuditLogger::log('media_sent', 'media', $media_id, [
                'conversation_id'        => $conversation_id,
                'media_type'             => $mediaType,
                'wa_message_id'          => $wa_message_id,
                'filename'               => $realFilename,
                'public_url'             => $publicUrl,
                'is_reply'               => !!$reply_to_message_id,
                'reply_to_message_id'    => $reply_to_message_id,
                'reply_to_wa_message_id' => $reply_to_wa_message_id,
            ]);

            return ['success' => true, 'wa_message_id' => $wa_message_id, 'media_id' => $media_id];
        }

        \WAS\Core\SystemLogger::logError('Falha ao enviar mensagem de mídia.', [
            'tenant_id' => $tenant_id,
            'media_id' => $media_id,
            'payload'  => $payload,
            'response' => $sendResponse
        ]);

        return $sendResponse;
    }
}

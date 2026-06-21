<?php
namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\Inbox\MediaRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class InboundMediaService {
    private $api_client;
    private $token_service;
    private $media_repo;

    public function __construct() {
        $this->api_client = new MetaApiClient();
        $this->token_service = new TokenService();
        $this->media_repo = new MediaRepository();
    }

    public function handle_inbound_media($tenant_id, $conversation_id, $message_id, $media_id, $media_type, $mime_type) {
        // 1. Registrar mídia localmente
        $local_media_id = $this->media_repo->create([
            'tenant_id'       => $tenant_id,
            'conversation_id' => $conversation_id,
            'message_id'      => $message_id,
            'meta_media_id'   => $media_id,
            'media_type'      => $media_type,
            'mime_type'       => $mime_type,
            'direction'       => 'inbound',
            'status'          => 'pending'
        ]);

        // 2. Buscar URL da mídia na Meta
        $token = $this->token_service->get_active_token($tenant_id);
        if (!$token) return false;

        $mediaInfo = $this->api_client->get('media.get', ['media_id' => $media_id], [], $token);
        if (!$mediaInfo['success'] || empty($mediaInfo['url'])) {
            $this->media_repo->update($local_media_id, ['status' => 'failed', 'error_message' => 'Falha ao buscar URL na Meta']);
            return false;
        }

        // 3. Baixar arquivo
        $url = $mediaInfo['url'];
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            $this->media_repo->update($local_media_id, ['status' => 'failed', 'error_message' => $response->get_error_message()]);
            return false;
        }

        $binary = wp_remote_retrieve_body($response);
        $filename = $media_id . $this->get_extension($mime_type);
        
        // 4. Salvar localmente (WordPress Uploads)
        $upload = wp_upload_bits($filename, null, $binary);
        if ($upload['error']) {
            $this->media_repo->update($local_media_id, ['status' => 'failed', 'error_message' => $upload['error']]);
            return false;
        }

        // 5. Atualizar registro
        $this->media_repo->update($local_media_id, [
            'storage_path' => $upload['file'],
            'public_url'   => $upload['url'],
            'file_size'    => filesize($upload['file']),
            'status'       => 'downloaded'
        ]);

        \WAS\Compliance\AuditLogger::log('media_received', 'media', $local_media_id, [
            'conversation_id' => $conversation_id,
            'media_type'      => $mediaType,
            'meta_id'         => $media_id
        ]);

        return $upload['url'];
    }

    private function get_extension($mime) {
        $map = [
            'image/jpeg' => '.jpg',
            'image/png'  => '.png',
            'audio/ogg'  => '.ogg',
            'audio/mpeg' => '.mp3',
            'audio/aac'  => '.aac',
            'audio/mp4'  => '.mp4',
            'video/mp4'  => '.mp4',
            'application/pdf' => '.pdf'
        ];
        return $map[$mime] ?? '';
    }
}

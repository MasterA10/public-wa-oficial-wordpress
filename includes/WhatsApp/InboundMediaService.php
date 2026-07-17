<?php
namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\Inbox\MediaRepository;
use WAS\Auth\TenantContext;
use WAS\WhatsApp\PhoneNumberService;

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

    public function handle_inbound_media($tenant_id, $conversation_id, $message_id, $media_id, $media_type, $mime_type, $direction = 'inbound', $phone_number_id = null) {
        // 1. Registrar mídia localmente
        $local_media_id = $this->media_repo->create([
            'tenant_id'       => $tenant_id,
            'conversation_id' => $conversation_id,
            'message_id'      => $message_id,
            'meta_media_id'   => $media_id,
            'media_type'      => $media_type,
            'mime_type'       => $mime_type,
            'direction'       => $direction,
            'status'          => 'pending'
        ]);

        // 2. Buscar a credencial da conta WABA do número que recebeu a mídia.
        // Um tenant pode possuir várias WABAs; o token global do tenant pode
        // não ter permissão para acessar este media_id.
        $phone = null;
        if ($phone_number_id) {
            $phone = (new PhoneNumberService())->get_by_phone_number_id($tenant_id, $phone_number_id);
            if (!$phone) {
                $this->media_repo->update($local_media_id, [
                    'status'        => 'failed',
                    'error_message' => 'Número WhatsApp do webhook não encontrado neste tenant: ' . $phone_number_id,
                ]);
                return false;
            }
        }

        $token = $phone && !empty($phone->whatsapp_account_id)
            ? $this->token_service->get_active_token($tenant_id, (int) $phone->whatsapp_account_id)
            : $this->token_service->get_active_token($tenant_id);
        if (!$token) {
            $this->media_repo->update($local_media_id, [
                'status'        => 'failed',
                'error_message' => $phone_number_id
                    ? 'Token ativo da conta WABA do número ' . $phone_number_id . ' não encontrado para baixar a mídia'
                    : 'Token ativo do WhatsApp não encontrado para baixar a mídia',
            ]);
            return false;
        }

        $mediaInfo = $this->api_client->get('media.get', ['media_id' => $media_id], [], $token);
        if (!$mediaInfo['success'] || empty($mediaInfo['url'])) {
            $this->media_repo->update($local_media_id, [
                'status'        => 'failed',
                'error_message' => $mediaInfo['error'] ?? 'Falha ao buscar URL da mídia na Meta',
            ]);
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

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $binary = wp_remote_retrieve_body($response);
        if ($http_code < 200 || $http_code >= 300 || '' === $binary) {
            $this->media_repo->update($local_media_id, [
                'status'        => 'failed',
                'error_message' => sprintf(
                    'Falha HTTP ao baixar mídia da Meta (HTTP %d)%s',
                    $http_code,
                    $binary === '' ? ': resposta vazia' : ''
                ),
            ]);
            return false;
        }

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

        \WAS\Compliance\AuditLogger::log('outbound' === $direction ? 'media_sent' : 'media_received', 'media', $local_media_id, [
            'conversation_id' => $conversation_id,
            'media_type'      => $media_type,
            'meta_id'         => $media_id
        ]);

        return $upload['url'];
    }

    private function get_extension($mime) {
        $mime = strtolower(trim(explode(';', (string) $mime, 2)[0]));
        $map = [
            'image/jpeg' => '.jpg',
            'image/png'  => '.png',
            'image/webp' => '.webp',
            'audio/ogg'  => '.ogg',
            'audio/mpeg' => '.mp3',
            'audio/aac'  => '.aac',
            'audio/mp4'  => '.m4a',
            'video/mp4'  => '.mp4',
            'application/pdf' => '.pdf'
        ];
        return $map[$mime] ?? '';
    }
}

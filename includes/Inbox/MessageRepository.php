<?php
namespace WAS\Inbox;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class MessageRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('messages');
    }

    /**
     * Cria uma mensagem de entrada (inbound).
     */
    public function create_inbound($data) {
        return $this->create(array_merge($data, ['direction' => 'inbound']));
    }

    /**
     * Cria uma mensagem de saída (outbound).
     */
    public function create_outbound($data) {
        return $this->create(array_merge($data, ['direction' => 'outbound']));
    }

    /**
     * Método base para criação de mensagens.
     */
    private function create($data) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        if (!$tenant_id) {
            return false;
        }

        $defaults = [
            'tenant_id'       => $tenant_id,
            'status'          => 'received',
            'created_at'      => current_time('mysql', 1),
        ];

        $payload = array_merge($defaults, $data);

        $result = $wpdb->insert(
            $this->table_name,
            $payload
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Busca mensagem pelo ID oficial do WhatsApp (evita duplicidade).
     */
    public function find_by_wa_message_id($wa_message_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        if (!$tenant_id) return null;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE wa_message_id = %s AND tenant_id = %d",
            $wa_message_id,
            $tenant_id
        ));
    }

    /**
     * Busca mensagem por ID interno e tenant.
     */
    public function find_by_id($id, $tenant_id = null) {
        global $wpdb;
        $tenant_id = $tenant_id ?: TenantContext::get_tenant_id();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND tenant_id = %d",
            $id,
            $tenant_id
        ));
    }

    /**
     * Lista mensagens de uma conversa com join de mídia e preview de resposta.
     */
    public function list_by_conversation($conversation_id, $limit = 50, $offset = 0, $tenant_id = null) {
        global $wpdb;
        $tenant_id = $tenant_id ?: TenantContext::get_tenant_id();
        $media_table = TableNameResolver::get_table_name('media');
        $referral_table = TableNameResolver::getMessageReferralsTable();

        // Note: For reply preview, we'll do a simple approach or a self-join.
        // A self-join is better for performance here.
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, 
                    med.public_url as media_url, med.filename as media_filename, med.file_size as media_size,
                    med.status as media_status, med.mime_type as media_mime_type, med.error_message as media_error,
                    r.text_body as reply_text, r.direction as reply_direction, r.message_type as reply_type,
                    ref.headline as referral_headline, ref.body as referral_body, ref.source_url as referral_url,
                    ref.media_type as referral_media_type, ref.image_url as referral_image, ref.video_url as referral_video
             FROM {$this->table_name} m
             LEFT JOIN {$media_table} med ON m.id = med.message_id
             LEFT JOIN {$this->table_name} r ON m.reply_to_message_id = r.id
             LEFT JOIN {$referral_table} ref ON m.referral_id = ref.id
             WHERE m.conversation_id = %d AND m.tenant_id = %d 
             ORDER BY m.created_at ASC 
             LIMIT %d OFFSET %d",
            $conversation_id,
            $tenant_id,
            $limit,
            $offset
        ));
    }

    /**
     * Lista mensagens novas de uma conversa (após um determinado ID).
     * Usado pelo sistema de polling em tempo real.
     */
    public function list_new_messages($conversation_id, $after_id, $tenant_id = null) {
        global $wpdb;
        $tenant_id = $tenant_id ?: TenantContext::get_tenant_id();
        $media_table = TableNameResolver::get_table_name('media');
        $referral_table = TableNameResolver::getMessageReferralsTable();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, 
                    med.public_url as media_url, med.filename as media_filename, med.file_size as media_size,
                    med.status as media_status, med.mime_type as media_mime_type, med.error_message as media_error,
                    r.text_body as reply_text, r.direction as reply_direction, r.message_type as reply_type,
                    ref.headline as referral_headline, ref.body as referral_body, ref.source_url as referral_url,
                    ref.media_type as referral_media_type, ref.image_url as referral_image, ref.video_url as referral_video
             FROM {$this->table_name} m
             LEFT JOIN {$media_table} med ON m.id = med.message_id
             LEFT JOIN {$this->table_name} r ON m.reply_to_message_id = r.id
             LEFT JOIN {$referral_table} ref ON m.referral_id = ref.id
             WHERE m.conversation_id = %d AND m.tenant_id = %d AND m.id > %d
             ORDER BY m.created_at ASC",
            $conversation_id,
            $tenant_id,
            $after_id
        ));
    }

    /**
     * Returns evidence from the external route for a webhook message.
     * This lets the inbox show whether a public media URL was delivered.
     */
    public function get_route_diagnostics($wa_message_id, $tenant_id = null) {
        global $wpdb;

        $tenant_id = $tenant_id ?: TenantContext::get_tenant_id();
        if (!$tenant_id || !$wa_message_id) {
            return [];
        }

        $events_table = TableNameResolver::getWebhookEventsTable();
        $deliveries_table = TableNameResolver::getOutboxDeliveriesTable();
        $routes_table = TableNameResolver::getRoutesTable();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id as event_id, e.routing_status, e.routing_note, e.normalized_payload,
                    d.id as delivery_id, d.status as delivery_status, d.attempts,
                    d.response_status, d.last_error, d.delivered_at,
                    r.id as route_id, r.name as route_name, r.target_url
             FROM {$events_table} e
             LEFT JOIN {$deliveries_table} d ON d.event_id = e.id
             LEFT JOIN {$routes_table} r ON r.id = d.route_id
             WHERE e.wa_message_id = %s AND e.tenant_id = %d
             ORDER BY d.id ASC",
            (string) $wa_message_id,
            (int) $tenant_id
        ));

        if (empty($rows)) {
            return [];
        }

        $diagnostics = [];
        foreach ($rows as $row) {
            $normalized = json_decode((string) ($row->normalized_payload ?? ''), true);
            $media = $this->find_route_media_payload(is_array($normalized) ? $normalized : []);
            $public_url = $this->route_public_url($media);
            $delivery_id = $row->delivery_id ?? null;
            $response_status = $row->response_status ?? null;
            $route_id = $row->route_id ?? null;

            $diagnostics[] = [
                'event_id'                => (int) ($row->event_id ?? 0),
                'routing_status'          => (string) ($row->routing_status ?? ''),
                'routing_note'            => $row->routing_note ?? null,
                'delivery_id'             => $delivery_id ? (int) $delivery_id : null,
                'delivery_status'         => $row->delivery_status ?? null,
                'attempts'                => isset($row->attempts) ? (int) $row->attempts : null,
                'response_status'         => $response_status ? (int) $response_status : null,
                'last_error'              => $row->last_error ?? null,
                'delivered_at'            => $row->delivered_at ?? null,
                'route_id'                => $route_id ? (int) $route_id : null,
                'route_name'              => $row->route_name ?? null,
                'target_url'              => $row->target_url ?? null,
                'public_url'              => $public_url,
                'public_url_sent'         => (bool) $public_url,
                'url_status'              => $media['url_status'] ?? null,
                'storage_status'          => $media['storage_status'] ?? null,
                'url_error'               => $media['url_error'] ?? ($media['storage_error'] ?? null),
                'requires_authorization' => !empty($media['url_requires_authorization']),
            ];
        }

        return $diagnostics;
    }

    private function find_route_media_payload(array $normalized) {
        $candidates = [
            $normalized['media'] ?? null,
            $normalized['message']['audio'] ?? null,
            $normalized['message']['document'] ?? null,
            $normalized['message']['image'] ?? null,
            $normalized['message']['sticker'] ?? null,
            $normalized['message']['video'] ?? null,
            $normalized['payload']['messages'][0]['audio'] ?? null,
            $normalized['payload']['messages'][0]['document'] ?? null,
            $normalized['payload']['messages'][0]['image'] ?? null,
            $normalized['payload']['messages'][0]['sticker'] ?? null,
            $normalized['payload']['messages'][0]['video'] ?? null,
            $normalized['payload']['message_echoes'][0]['audio'] ?? null,
            $normalized['payload']['message_echoes'][0]['document'] ?? null,
            $normalized['payload']['message_echoes'][0]['image'] ?? null,
            $normalized['payload']['message_echoes'][0]['sticker'] ?? null,
            $normalized['payload']['message_echoes'][0]['video'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && (!empty($candidate['id']) || !empty($candidate['public_url']) || !empty($candidate['url']))) {
                return $candidate;
            }
        }

        return [];
    }

    private function route_public_url(array $media) {
        if (!empty($media['public_url'])) {
            return (string) $media['public_url'];
        }

        if ('stored' === ($media['url_status'] ?? null) && !empty($media['url']) && empty($media['url_requires_authorization'])) {
            return (string) $media['url'];
        }

        return null;
    }

    /**
     * Atualiza o status de uma mensagem (sent, delivered, read, failed).
     */
    public function update_status($wa_message_id, $status) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            ['status' => $status],
            ['wa_message_id' => $wa_message_id, 'tenant_id' => $tenant_id],
            ['%s'],
            ['%s', '%d']
        );
    }
}

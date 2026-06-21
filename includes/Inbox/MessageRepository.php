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
    public function list_by_conversation($conversation_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        $media_table = TableNameResolver::get_table_name('media');
        $referral_table = TableNameResolver::getMessageReferralsTable();

        // Note: For reply preview, we'll do a simple approach or a self-join.
        // A self-join is better for performance here.
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, 
                    med.public_url as media_url, med.filename as media_filename, med.file_size as media_size,
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
    public function list_new_messages($conversation_id, $after_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        $media_table = TableNameResolver::get_table_name('media');
        $referral_table = TableNameResolver::getMessageReferralsTable();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, 
                    med.public_url as media_url, med.filename as media_filename, med.file_size as media_size,
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

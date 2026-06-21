<?php
namespace WAS\Inbox;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class ConversationRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('conversations');
    }

    /**
     * Atualiza uma conversa.
     */
    public function update(int $id, array $data) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        return $wpdb->update($this->table_name, $data, ['id' => $id, 'tenant_id' => $tenant_id]);
    }

    /**
     * Busca uma conversa vinculada ao tenant.
     */
    public function findForTenant(int $id, int $tenantId) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND tenant_id = %d",
            $id,
            $tenantId
        ));
    }

    /**
     * Busca uma conversa aberta para um contato ou cria se não existir.
     * 
     * @param int $contact_id ID do contato.
     * @param string $phone_number_id O ID do número de telefone (Meta).
     * @return object|false
     */
    public function find_or_create_open_conversation($contact_id, $phone_number_id = '') {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        if (!$tenant_id) {
            return false;
        }

        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE contact_id = %d AND tenant_id = %d AND status = 'open'",
            $contact_id,
            $tenant_id
        ));

        if ($conversation) {
            return $conversation;
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'tenant_id'       => $tenant_id,
                'contact_id'      => $contact_id,
                'phone_number_id' => $phone_number_id, // Campo obrigatório
                'status'          => 'open',
                'last_message_at' => current_time('mysql', 1),
                'created_at'      => current_time('mysql', 1),
                'updated_at'      => current_time('mysql', 1),
            ]
        );

        if ($result) {
            return $this->get_by_id($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Atualiza o timestamp da última mensagem na conversa.
     */
    public function update_last_message_at($conversation_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            [
                'last_message_at' => current_time('mysql', 1),
                'updated_at'      => current_time('mysql', 1)
            ],
            ['id' => $conversation_id, 'tenant_id' => $tenant_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
    }

    /**
     * Atualiza o ID da última mensagem recebida do cliente.
     */
    public function update_last_inbound_wa_message_id($conversation_id, $wa_message_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            [
                'last_inbound_wa_message_id' => $wa_message_id,
                'updated_at'                 => current_time('mysql', 1)
            ],
            ['id' => $conversation_id, 'tenant_id' => $tenant_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
    }

    /**
     * Marca o momento em que um indicador de digitação foi enviado.
     */
    public function mark_typing_sent($conversation_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            [
                'last_typing_sent_at' => current_time('mysql', 1),
                'updated_at'          => current_time('mysql', 1)
            ],
            ['id' => $conversation_id, 'tenant_id' => $tenant_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
    }

    /**
     * Marca o momento em que uma mensagem de saída foi enviada.
     */
    public function mark_outbound_sent($conversation_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            [
                'last_outbound_sent_at' => current_time('mysql', 1),
                'updated_at'            => current_time('mysql', 1)
            ],
            ['id' => $conversation_id, 'tenant_id' => $tenant_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
    }

    /**
     * Atribui uma conversa a um usuário (atendente).
     */
    public function assign($conversation_id, $user_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            [
                'assigned_user_id' => $user_id,
                'updated_at'       => current_time('mysql', 1)
            ],
            ['id' => $conversation_id, 'tenant_id' => $tenant_id],
            ['%d', '%s'],
            ['%d', '%d']
        );
    }

    /**
     * Busca uma conversa pelo ID.
     */
    public function get_by_id($id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND tenant_id = %d",
            $id,
            $tenant_id
        ));
    }

    /**
     * Lista conversas do tenant ordenadas pela última mensagem.
     */
    public function list_conversations($limit = 20, $offset = 0) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        $contact_table = TableNameResolver::get_table_name('contacts');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, ct.profile_name, ct.wa_id 
             FROM {$this->table_name} c
             JOIN {$contact_table} ct ON c.contact_id = ct.id
             WHERE c.tenant_id = %d 
             ORDER BY c.last_message_at DESC 
             LIMIT %d OFFSET %d",
            $tenant_id,
            $limit,
            $offset
        ));
    }
}

<?php
namespace WAS\Inbox;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class ContactRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('contacts');
    }

    /**
     * Cria um novo contato.
     */
    public function create(array $data) {
        global $wpdb;
        $result = $wpdb->insert($this->table_name, $data);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Atualiza um contato existente.
     */
    public function update(int $id, array $data) {
        global $wpdb;
        return $wpdb->update($this->table_name, $data, ['id' => $id]);
    }


    /**
     * Busca um contato pelo wa_id e tenant_id.
     */
    public function find_by_wa_id(int $tenantId, string $waId) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d AND wa_id = %s",
            $tenantId,
            $waId
        ));
    }

    /**
     * Busca um contato pelo telefone e tenant_id.
     */
    public function find_by_phone(int $tenantId, string $phone) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d AND (phone = %s OR normalized_phone = %s)",
            $tenantId,
            $phone,
            $phone
        ));
    }

    /**
     * Busca um contato pelo WhatsApp ID ou cria se não existir.
     * 
     * @param string $wa_id O ID do WhatsApp (geralmente o número de telefone).
     * @param string $profile_name O nome de perfil do contato.
     * @return object|false O objeto do contato ou false em caso de erro.
     */
    public function find_or_create_by_wa_id($wa_id, $profile_name = '', $phone = '') {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        if (!$tenant_id) {
            return false;
        }

        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE wa_id = %s AND tenant_id = %d",
            $wa_id,
            $tenant_id
        ));

        if ($contact) {
            $update_data = [];
            // Atualiza o nome se mudou ou se estava vazio
            if (!empty($profile_name) && $contact->profile_name !== $profile_name) {
                $update_data['profile_name'] = $profile_name;
            }

            // Se recebemos via inbound ou retorno da API, o status é confirmado
            if ($contact->phone_status !== 'confirmed_by_wa_id' && $contact->phone_status !== 'confirmed_by_inbound') {
                $update_data['phone_status'] = 'confirmed_by_wa_id';
            }

            if (!empty($update_data)) {
                $update_data['updated_at'] = current_time('mysql', 1);
                $wpdb->update(
                    $this->table_name,
                    $update_data,
                    ['id' => $contact->id]
                );
                foreach($update_data as $k => $v) $contact->$k = $v;
            }
            return $contact;
        }

        $digits = preg_replace('/\D/', '', $phone ?: $wa_id);
        $raw_phone = !empty($phone) ? $phone : $wa_id;

        // Tenta normalizar se for Brasil
        $normalizer = new \WAS\WhatsApp\PhoneNormalizerService();
        $norm_res = $normalizer->normalize($raw_phone);
        if ($norm_res['success']) {
            $digits = $norm_res['normalized'];
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'tenant_id'        => $tenant_id,
                'wa_id'            => $wa_id,
                'phone'            => $raw_phone,
                'raw_phone'        => $raw_phone,
                'normalized_phone' => $digits,
                'phone_status'     => 'confirmed_by_wa_id',
                'profile_name'     => $profile_name,
                'created_at'       => current_time('mysql', 1),
                'updated_at'       => current_time('mysql', 1),
            ]
        );

        if ($result) {
            return $this->get_by_id($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Confirma o wa_id de um contato após retorno da API da Meta ou Webhook.
     */
    public function confirm_wa_id($contact_id, $wa_id, $status = 'confirmed_by_wa_id') {
        global $wpdb;
        return $wpdb->update(
            $this->table_name,
            [
                'wa_id'        => $wa_id,
                'phone_status' => $status,
                'updated_at'   => current_time('mysql', 1)
            ],
            ['id' => $contact_id]
        );
    }

    /**
     * Adiciona uma tag ao contato.
     */
    public function add_tag($contact_id, $tag) {
        $contact = $this->get_by_id($contact_id);
        if (!$contact) return false;

        $tags = json_decode($contact->tags ?: '[]', true);
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            return $this->update_tags($contact_id, $tags);
        }
        return true;
    }

    /**
     * Remove uma tag do contato.
     */
    public function remove_tag($contact_id, $tag) {
        $contact = $this->get_by_id($contact_id);
        if (!$contact) return false;

        $tags = json_decode($contact->tags ?: '[]', true);
        $tags = array_values(array_diff($tags, [$tag]));
        return $this->update_tags($contact_id, $tags);
    }

    private function update_tags($contact_id, $tags) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            ['tags' => json_encode($tags), 'updated_at' => current_time('mysql', 1)],
            ['id' => $contact_id, 'tenant_id' => $tenant_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
    }

    public function find_by_normalized_phone($phone) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        $digits = preg_replace('/\D/', '', $phone);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE normalized_phone = %s AND tenant_id = %d LIMIT 1",
            $digits,
            $tenant_id
        ));
    }

    /**
     * Busca um contato pelo ID interno.
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
     * Lista contatos do tenant atual.
     */
    public function list_contacts($limit = 20, $offset = 0) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
            $tenant_id,
            $limit,
            $offset
        ));
    }
}

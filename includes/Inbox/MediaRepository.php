<?php
namespace WAS\Inbox;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class MediaRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('media');
    }

    public function create($data) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        if (!$tenant_id) return false;

        $defaults = [
            'tenant_id'  => $tenant_id,
            'status'     => 'created',
            'created_at' => current_time('mysql', 1),
            'updated_at' => current_time('mysql', 1),
        ];

        $payload = array_merge($defaults, $data);
        $result = $wpdb->insert($this->table_name, $payload);

        return $result ? $wpdb->insert_id : false;
    }

    public function get_by_id($id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND tenant_id = %d",
            $id, $tenant_id
        ));
    }

    public function update($id, $data) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();
        $data['updated_at'] = current_time('mysql', 1);
        return $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $id, 'tenant_id' => $tenant_id]
        );
    }

    public function mark_uploaded($id, $meta_media_id) {
        return $this->update($id, [
            'meta_media_id' => $meta_media_id,
            'status'        => 'uploaded_to_meta'
        ]);
    }

    public function attach_message($id, $message_id) {
        return $this->update($id, ['message_id' => $message_id]);
    }
}

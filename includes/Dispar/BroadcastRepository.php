<?php
namespace WAS\Dispar;

use WAS\Auth\TenantContext;
use WAS\Core\TableNameResolver;

if (!defined('ABSPATH')) exit;

class BroadcastRepository {
    private $broadcasts;
    private $items;

    public function __construct() {
        $this->broadcasts = TableNameResolver::getBroadcastsTable();
        $this->items = TableNameResolver::getBroadcastItemsTable();
    }

    public function create(array $data, array $rows) {
        global $wpdb;
        $tenant = TenantContext::get_tenant_id();
        $now = current_time('mysql', true);
        $wpdb->insert($this->broadcasts, [
            'tenant_id' => $tenant, 'template_id' => (int)$data['template_id'],
            'name' => sanitize_text_field($data['name'] ?? ''), 'category' => sanitize_key($data['category'] ?? 'utility'),
            'interval_seconds' => max(5, min(86400, (int)$data['interval_seconds'])),
            'cost_per_message' => max(0, (float)$data['cost_per_message']), 'status' => 'draft',
            'total_count' => count($rows), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int)$wpdb->insert_id;
        foreach ($rows as $row) {
            $wpdb->insert($this->items, [
                'broadcast_id' => $id, 'phone' => preg_replace('/\D+/', '', (string)$row['phone']),
                'variables_json' => wp_json_encode($row['variables']), 'status' => 'pending', 'updated_at' => $now,
            ]);
        }
        return $id;
    }

    public function find($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->broadcasts} WHERE id=%d AND tenant_id=%d", $id, TenantContext::get_tenant_id()));
    }

    public function list($limit = 20) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->broadcasts} WHERE tenant_id=%d ORDER BY id DESC LIMIT %d", TenantContext::get_tenant_id(), $limit));
    }

    public function update($id, array $data) { global $wpdb; $data['updated_at'] = current_time('mysql', true); return $wpdb->update($this->broadcasts, $data, ['id'=>(int)$id, 'tenant_id'=>TenantContext::get_tenant_id()]); }

    public function next_item($broadcast_id) {
        global $wpdb;
        // Recupera também um item que ficou "sending" por uma queda do navegador/API.
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->items} WHERE broadcast_id=%d AND (status='pending' OR (status='sending' AND updated_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE)) ORDER BY id ASC LIMIT 1", $broadcast_id));
        if (!$item) return null;
        $changed = $wpdb->query($wpdb->prepare("UPDATE {$this->items} SET status='sending', attempts=attempts+1, updated_at=%s WHERE id=%d AND (status='pending' OR (status='sending' AND updated_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE))", current_time('mysql', true), $item->id));
        return $changed ? $item : null;
    }

    public function update_item($id, array $data) { global $wpdb; $data['updated_at'] = current_time('mysql', true); return $wpdb->update($this->items, $data, ['id'=>(int)$id]); }

    public function items($broadcast_id, $limit = 100) { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT id,phone,status,wa_message_id,error_code,error_message,sent_at FROM {$this->items} WHERE broadcast_id=%d ORDER BY id ASC LIMIT %d", $broadcast_id, $limit)); }

    public function counts($broadcast_id) { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) total FROM {$this->items} WHERE broadcast_id=%d GROUP BY status", $broadcast_id), OBJECT_K); }
}

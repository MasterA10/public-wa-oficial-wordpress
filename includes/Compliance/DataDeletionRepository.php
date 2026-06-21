<?php
namespace WAS\Compliance;

use WAS\Core\TableNameResolver;

if (!defined('ABSPATH')) {
    exit;
}

class DataDeletionRepository {
    private static function get_table_name() {
        return TableNameResolver::get_table_name('data_deletion_requests');
    }

    public static function insert(array $data) {
        global $wpdb;
        return $wpdb->insert(self::get_table_name(), $data);
    }

    public static function find_by_uuid(string $uuid) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE request_uuid = %s",
            $uuid
        ));
    }
}

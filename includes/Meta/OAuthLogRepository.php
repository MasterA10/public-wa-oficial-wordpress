<?php
namespace WAS\Meta;

use WAS\Core\TableNameResolver;

if (!defined('ABSPATH')) {
    exit;
}

class OAuthLogRepository {
    private static function get_table_name() {
        return TableNameResolver::get_table_name('meta_oauth_logs');
    }

    public static function insert(array $data) {
        global $wpdb;
        return $wpdb->insert(self::get_table_name(), $data);
    }
}

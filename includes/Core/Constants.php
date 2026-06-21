<?php
/**
 * Plugin Constants
 *
 * @package WAS\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WAS_VERSION' ) ) {
	define( 'WAS_VERSION', '0.4.0' );
}

if ( ! defined( 'WAS_PLUGIN_FILE' ) ) {
	define( 'WAS_PLUGIN_FILE', dirname( dirname( __DIR__ ) ) . '/whatsapp-saas-core.php' );
}

if ( ! defined( 'WAS_PLUGIN_DIR' ) ) {
	define( 'WAS_PLUGIN_DIR', plugin_dir_path( WAS_PLUGIN_FILE ) );
}

if ( ! defined( 'WAS_PLUGIN_URL' ) ) {
	define( 'WAS_PLUGIN_URL', plugin_dir_url( WAS_PLUGIN_FILE ) );
}

if ( ! defined( 'WAS_META_GRAPH_BASE_URL' ) ) {
	define( 'WAS_META_GRAPH_BASE_URL', 'https://graph.facebook.com' );
}

if ( ! defined( 'WAS_META_GRAPH_DEFAULT_VERSION' ) ) {
	define( 'WAS_META_GRAPH_DEFAULT_VERSION', 'v25.0' );
}

if ( ! defined( 'WAS_REST_NAMESPACE' ) ) {
	define( 'WAS_REST_NAMESPACE', 'was/v1' );
}

if ( ! defined( 'WAS_DB_VERSION_OPTION' ) ) {
	define( 'WAS_DB_VERSION_OPTION', 'was_db_version' );
}

if ( ! defined( 'WAS_DB_VERSION' ) ) {
	define( 'WAS_DB_VERSION', '1.0.9' );
}

if ( ! defined( 'WAS_ENCRYPTION_KEY' ) ) {
    $was_key = get_option( 'was_encryption_key' );
    if ( empty( $was_key ) ) {
        $was_key = bin2hex( random_bytes( 16 ) );
        update_option( 'was_encryption_key', $was_key );
    }
    define( 'WAS_ENCRYPTION_KEY', $was_key );
}

<?php
/**
 * AssetService class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing plugin assets.
 */
class AssetService {

	/**
	 * Enqueue scripts and styles for both Admin and SaaS Shell.
	 *
	 * @param string $page The current page identifier.
	 */
	public static function enqueue_assets( $page ) {
		wp_enqueue_style( 'was-app-css', WAS_PLUGIN_URL . 'assets/css/app.css', [], WAS_VERSION );
		wp_enqueue_script( 'was-app-js', WAS_PLUGIN_URL . 'assets/js/app.js', [], WAS_VERSION, true );

		$meta_repo = new \WAS\Meta\MetaAppRepository();
		$active_app = $meta_repo->get_active_app();

		wp_localize_script( 'was-app-js', 'wasApp', [
			'restUrl' => esc_url_raw( rest_url( 'was/v1' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'page'    => $page,
            'isShell' => ! is_admin(),
            'baseUrl' => home_url( '/app' ),
            'adminUrl' => admin_url( 'admin.php?page=was-' ),
            'timezone' => wp_timezone_string(),
            'pollingInterval' => (int) get_option('was_master_polling_interval', 3000),
            'metaConfigId' => $active_app ? $active_app->config_id : '',
		] );

        // Enqueue dashicons if in shell
        if ( ! is_admin() ) {
            wp_enqueue_style( 'dashicons' );
        }
	}
}

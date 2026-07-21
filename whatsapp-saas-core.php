<?php
/**
 * Plugin Name: WhatsApp SaaS Core
 * Description: Transform WordPress into a multi-tenant WhatsApp SaaS using Meta Official API.
 * Version: 0.5.8
 * Author:            Equipe do Produto
 * Text Domain:       whatsapp-saas-core
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * 
 * @package WAS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define basic constants.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/Core/Constants.php';

/**
 * Register Autoloader.
 */
require_once WAS_PLUGIN_DIR . 'includes/Core/Autoloader.php';
\WAS\Core\Autoloader::register();

/**
 * Initialize the plugin.
 */
function was_plugin_init() {
	$plugin = \WAS\Core\Plugin::get_instance();
	$plugin->boot();
}

add_action( 'plugins_loaded', 'was_plugin_init' );

/**
 * Activation Hook.
 */
register_activation_hook( __FILE__, [ '\WAS\Core\Activator', 'activate' ] );

/**
 * Deactivation Hook.
 */
register_deactivation_hook( __FILE__, [ '\WAS\Core\Deactivator', 'deactivate' ] );

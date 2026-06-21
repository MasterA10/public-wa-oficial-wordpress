<?php
/**
 * Autoloader for the WAS namespace.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class.
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register() {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Autoload logic.
	 *
	 * @param string $class The class name.
	 */
	public static function autoload( $class ) {
		$prefix   = 'WAS\\';
		$base_dir = WAS_PLUGIN_DIR . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
}

<?php
/**
 * URLService class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing plugin URLs and links.
 */
class URLService {

	/**
	 * Get a URL for a plugin page, adapting to current environment (Admin or Shell).
	 *
	 * @param string $page The page identifier (e.g., 'dashboard', 'settings-meta').
	 * @return string
	 */
	public static function get_page_url( $page ) {
        // Normalize page name
        $page = str_replace( 'was-', '', $page );

		if ( is_admin() ) {
			return admin_url( 'admin.php?page=was-' . $page );
		}

        // Map shell routes
        $shell_page = str_replace( '-', '/', $page );
		return home_url( '/app/' . $shell_page );
	}

    /**
     * Helper for Meta Settings URL.
     */
    public static function get_meta_settings_url() {
        return self::get_page_url( 'settings-meta' );
    }

    /**
     * Helper for WhatsApp Settings URL.
     */
    public static function get_whatsapp_settings_url() {
        return self::get_page_url( 'settings-whatsapp' );
    }
}

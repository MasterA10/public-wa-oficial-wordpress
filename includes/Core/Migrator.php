<?php
/**
 * Migrator class for structural changes that dbDelta misses.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrator.
 */
class Migrator {

	/**
	 * Run migrations.
	 */
	public static function run() {
		self::migrate_contacts_table();
		self::migrate_conversations_table();
	}

	/**
	 * Migrate contacts table.
	 */
	private static function migrate_contacts_table() {
		global $wpdb;
		$table_name = TableNameResolver::get_table_name( 'contacts' );

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return;
		}

		// Add display_name if missing
		$row = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'display_name'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` 
				MODIFY COLUMN profile_name varchar(190) DEFAULT NULL,
				ADD COLUMN display_name varchar(190) DEFAULT NULL AFTER profile_name,
				ADD COLUMN name_source varchar(50) DEFAULT 'whatsapp_profile' AFTER display_name,
				ADD COLUMN name_locked tinyint(1) DEFAULT 0 AFTER name_source,
				ADD COLUMN last_profile_name_at datetime DEFAULT NULL AFTER name_locked" 
			);
		}
	}

	/**
	 * Migrate conversations table.
	 */
	private static function migrate_conversations_table() {
		global $wpdb;
		$table_name = TableNameResolver::get_table_name( 'conversations' );

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return;
		}

		// Add customer_service_window_status if missing
		$row = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE 'customer_service_window_status'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` 
				ADD COLUMN origin_type varchar(50) DEFAULT NULL AFTER status,
				ADD COLUMN origin_source varchar(50) DEFAULT NULL AFTER origin_type,
				ADD COLUMN ctwa_clid varchar(255) DEFAULT NULL AFTER origin_source,
				ADD COLUMN first_referral_id bigint(20) UNSIGNED DEFAULT NULL AFTER ctwa_clid,
				ADD COLUMN last_referral_id bigint(20) UNSIGNED DEFAULT NULL AFTER first_referral_id,
				ADD COLUMN last_inbound_wa_message_id varchar(190) DEFAULT NULL AFTER last_referral_id,
				ADD COLUMN last_customer_message_at datetime DEFAULT NULL AFTER last_inbound_wa_message_id,
				ADD COLUMN customer_service_window_expires_at datetime DEFAULT NULL AFTER last_customer_message_at,
				ADD COLUMN customer_service_window_status varchar(30) DEFAULT 'closed' AFTER customer_service_window_expires_at,
				ADD COLUMN last_typing_sent_at datetime DEFAULT NULL AFTER customer_service_window_status,
				ADD COLUMN last_outbound_sent_at datetime DEFAULT NULL AFTER last_typing_sent_at"
			);
		}
	}
}

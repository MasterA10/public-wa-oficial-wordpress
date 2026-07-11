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
		self::migrate_router_columns();
		self::migrate_onboarding_security_columns();
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

	/**
	 * Ensure Router columns exist for sites upgraded from earlier plugin versions.
	 */
	private static function migrate_router_columns() {
		self::add_missing_columns(
			TableNameResolver::get_table_name( 'whatsapp_accounts' ),
			[
				'token_expires_at'               => 'datetime DEFAULT NULL',
				'last_sync_at'                   => 'datetime DEFAULT NULL',
				'last_sync_error'                => 'text DEFAULT NULL',
				'webhook_subscribed'             => 'tinyint(1) DEFAULT 0',
				'webhook_override_detected'      => 'tinyint(1) DEFAULT 0',
				'webhook_override_callback_url'  => 'text DEFAULT NULL',
				'webhook_last_check_at'          => 'datetime DEFAULT NULL',
				'webhook_last_error'             => 'text DEFAULT NULL',
			]
		);

		self::add_missing_columns(
			TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ),
			[
				'meta_status'                    => 'varchar(80) DEFAULT NULL',
				'code_verification_status'       => 'varchar(80) DEFAULT NULL',
				'platform_type'                  => 'varchar(80) DEFAULT NULL',
				'throughput_json'                => 'longtext DEFAULT NULL',
				'last_status_check_at'           => 'datetime DEFAULT NULL',
				'last_status_changed_at'         => 'datetime DEFAULT NULL',
				'last_status_error'              => 'text DEFAULT NULL',
				'last_status_fingerprint'        => 'varchar(128) DEFAULT NULL',
				'last_status_response_json'      => 'longtext DEFAULT NULL',
				'default_route_id'               => 'bigint(20) UNSIGNED DEFAULT NULL',
				'webhook_override_detected'      => 'tinyint(1) DEFAULT 0',
				'webhook_override_callback_url'  => 'text DEFAULT NULL',
				'webhook_last_check_at'          => 'datetime DEFAULT NULL',
				'webhook_last_error'             => 'text DEFAULT NULL',
				'updated_at'                     => 'datetime DEFAULT NULL',
			]
		);

		self::add_missing_columns(
			TableNameResolver::getWebhookEventsTable(),
			[
				'whatsapp_account_id'            => 'bigint(20) UNSIGNED DEFAULT NULL',
				'whatsapp_phone_number_row_id'   => 'bigint(20) UNSIGNED DEFAULT NULL',
				'message_type'                   => 'varchar(100) DEFAULT NULL',
				'wa_message_id'                  => 'varchar(190) DEFAULT NULL',
				'wa_from'                        => 'varchar(80) DEFAULT NULL',
				'normalized_payload'             => 'longtext DEFAULT NULL',
				'idempotency_key'                => 'varchar(255) DEFAULT NULL',
				'routing_status'                 => "varchar(80) DEFAULT 'received'",
				'routing_note'                   => 'text DEFAULT NULL',
			]
		);

		self::add_missing_columns(
			TableNameResolver::getTemplatesTable(),
			[
				'router_phone_number_id'         => 'bigint(20) UNSIGNED DEFAULT NULL',
				'meta_response_json'             => 'longtext DEFAULT NULL',
				'requested_callback_url'         => 'text DEFAULT NULL',
				'last_status_check_at'           => 'datetime DEFAULT NULL',
				'last_status_error'              => 'text DEFAULT NULL',
				'approved_notified_at'           => 'datetime DEFAULT NULL',
			]
		);
	}

	/** Add one-time OAuth state and lifecycle fields to existing installations. */
	private static function migrate_onboarding_security_columns() {
		self::add_missing_columns(
			TableNameResolver::getOnboardingRegistrationsTable(),
			[
				'state_hash'           => 'varchar(128) DEFAULT NULL',
				'expires_at'           => 'datetime DEFAULT NULL',
				'callback_received_at' => 'datetime DEFAULT NULL',
				'completed_at'         => 'datetime DEFAULT NULL',
			]
		);
	}

	private static function add_missing_columns( $table_name, array $columns ) {
		global $wpdb;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return;
		}

		foreach ( $columns as $column => $definition ) {
			$exists = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name` LIKE '" . esc_sql( $column ) . "'" );
			if ( ! empty( $exists ) ) {
				continue;
			}

			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `$column` $definition" );
		}
	}
}

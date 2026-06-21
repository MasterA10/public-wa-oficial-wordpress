<?php
/**
 * Installer class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installer.
 */
class Installer {

	/**
	 * Run installation logic.
	 */
	public static function install() {
		self::create_tables();
		\WAS\Core\Migrator::run();
		self::validate_installation();
		self::create_capabilities();
		\WAS\Compliance\LegalPagesGenerator::generateAll();
		self::seed_data();
        flush_rewrite_rules(false);
	}

	/**
	 * Validate that all tables were created.
	 */
	private static function validate_installation() {
		global $wpdb;
		$tables = TableNameResolver::get_all_tables();
		$missing = [];

		foreach ( $tables as $table ) {
			$full_name = TableNameResolver::get_table_name( $table );
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full_name ) ) !== $full_name ) {
				$missing[] = $full_name;
			}
		}

		if ( ! empty( $missing ) ) {
			error_log( 'WAS Error: Missing tables after installation: ' . implode( ', ', $missing ) );
			add_action( 'admin_notices', function() use ( $missing ) {
				echo '<div class="error"><p>WhatsApp SaaS Core: Missing tables: ' . esc_html( implode( ', ', $missing ) ) . '</p></div>';
			} );
		}
	}

	/**
	 * Create database tables.
	 */
	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// WAS-011: Tenants table.
		$table_tenants = TableNameResolver::get_table_name( 'tenants' );
		$sql_tenants = "CREATE TABLE $table_tenants (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(100) NOT NULL,
			status varchar(50) DEFAULT 'active',
			plan varchar(50) DEFAULT 'free',
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";
		dbDelta( $sql_tenants );

		// WAS-012: Tenant Users table.
		$table_tenant_users = TableNameResolver::get_table_name( 'tenant_users' );
		$sql_tenant_users = "CREATE TABLE $table_tenant_users (
			tenant_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			role varchar(50) NOT NULL,
			status varchar(50) DEFAULT 'active',
			PRIMARY KEY  (tenant_id, user_id),
			KEY tenant_id (tenant_id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql_tenant_users );

		// WAS-013: Meta Apps table.
		$table_meta_apps = TableNameResolver::get_table_name( 'meta_apps' );
		$sql_meta_apps = "CREATE TABLE $table_meta_apps (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT 'Meta App',
			app_id varchar(100) NOT NULL,
			app_secret text NOT NULL,
			app_secret_encrypted text DEFAULT NULL,
			config_id varchar(100) NULL,
			graph_version varchar(20) DEFAULT 'v25.0',
			embedded_signup_config_id varchar(190) DEFAULT NULL,
			webhook_callback_url text DEFAULT NULL,
			webhook_verify_token varchar(190) DEFAULT NULL,
			verify_token varchar(255) NULL,
			privacy_policy_url text DEFAULT NULL,
			terms_url text DEFAULT NULL,
			data_deletion_url text DEFAULT NULL,
			environment varchar(50) DEFAULT 'production',
			app_mode varchar(50) DEFAULT 'development',
			app_review_status varchar(50) DEFAULT 'not_submitted',
			business_verification_status varchar(50) DEFAULT 'unknown',
			access_verification_status varchar(50) DEFAULT 'unknown',
			is_default tinyint(1) DEFAULT 0,
			status varchar(50) DEFAULT 'active',
			last_healthcheck_at datetime DEFAULT NULL,
			last_error text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY app_id (app_id),
			KEY environment (environment),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_meta_apps );

		// WAS-014: WhatsApp Accounts table.
		$table_wa_accounts = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$sql_wa_accounts = "CREATE TABLE $table_wa_accounts (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			meta_app_id bigint(20) UNSIGNED DEFAULT NULL,
			meta_business_id varchar(100) NULL,
			waba_id varchar(100) NOT NULL,
			name varchar(255) NULL,
			currency varchar(20) DEFAULT NULL,
			timezone varchar(80) DEFAULT NULL,
			status varchar(50) DEFAULT 'connected',
			webhook_subscription_status varchar(50) DEFAULT 'unknown',
			last_template_sync_at datetime DEFAULT NULL,
			last_webhook_event_at datetime DEFAULT NULL,
			connected_at datetime DEFAULT NULL,
			disconnected_at datetime DEFAULT NULL,
			last_error text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_waba (tenant_id, waba_id),
			KEY waba_id (waba_id),
			KEY tenant_id (tenant_id),
			KEY meta_app_id (meta_app_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_wa_accounts );

		// WAS-015: WhatsApp Phone Numbers table.
		$table_wa_phones = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$sql_wa_phones = "CREATE TABLE $table_wa_phones (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			whatsapp_account_id bigint(20) UNSIGNED NOT NULL,
			phone_number_id varchar(100) NOT NULL,
			display_phone_number varchar(50) NULL,
			verified_name varchar(255) NULL,
			quality_rating varchar(50) DEFAULT NULL,
			messaging_limit_tier varchar(50) DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			name_status varchar(80) DEFAULT NULL,
			account_mode varchar(80) DEFAULT NULL,
			health_status_json longtext DEFAULT NULL,
			last_diagnostic_at datetime DEFAULT NULL,
			last_send_test_at datetime DEFAULT NULL,
			last_webhook_at datetime DEFAULT NULL,
			last_error text DEFAULT NULL,
			is_default tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone_number_id (phone_number_id),
			KEY tenant_id (tenant_id),
			KEY wa_account_id (whatsapp_account_id)
		) $charset_collate;";
		dbDelta( $sql_wa_phones );

		// WAS-016: Meta Tokens table.
		$table_meta_tokens = TableNameResolver::get_table_name( 'meta_tokens' );
		$sql_meta_tokens = "CREATE TABLE $table_meta_tokens (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			whatsapp_account_id bigint(20) UNSIGNED NULL,
			access_token_encrypted text NOT NULL,
			token_prefix varchar(20) DEFAULT NULL,
			token_length int(10) DEFAULT NULL,
			scopes text NULL,
			expires_at datetime NULL,
			last_used_at datetime NULL,
			last_error text DEFAULT NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY wa_account_id (whatsapp_account_id)
		) $charset_collate;";
		dbDelta( $sql_meta_tokens );

		// WAS-017: Contacts table.
		$table_contacts = TableNameResolver::get_table_name( 'contacts' );
		$sql_contacts = "CREATE TABLE $table_contacts (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			wa_id varchar(100) NOT NULL,
			phone varchar(50) NOT NULL,
			raw_phone varchar(80) DEFAULT NULL,
			normalized_phone varchar(50) DEFAULT NULL,
			phone_country varchar(10) DEFAULT 'BR',
			phone_status varchar(50) DEFAULT 'unverified',
			phone_validation_notes text DEFAULT NULL,
			last_phone_check_at datetime DEFAULT NULL,
			profile_name varchar(190) DEFAULT NULL,
			display_name varchar(190) DEFAULT NULL,
			name_source varchar(50) DEFAULT 'whatsapp_profile',
			name_locked tinyint(1) DEFAULT 0,
			last_profile_name_at datetime DEFAULT NULL,
			tags text NULL,
			opt_in_status varchar(50) DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_wa_id (tenant_id, wa_id),
			KEY phone (phone),
			KEY normalized_phone (normalized_phone),
			KEY tenant_id (tenant_id)
		) $charset_collate;";
		dbDelta( $sql_contacts );

		// WAS-018: Opt-ins table.
		$table_optins = TableNameResolver::get_table_name( 'contact_optins' );
		$sql_optins = "CREATE TABLE $table_optins (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			contact_id bigint(20) UNSIGNED NOT NULL,
			source varchar(100) NULL,
			consent_text text NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY tenant_id (tenant_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_optins );

		// WAS-019: Conversations table.
		$table_conversations = TableNameResolver::get_table_name( 'conversations' );
		$sql_conversations = "CREATE TABLE $table_conversations (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			contact_id bigint(20) UNSIGNED NOT NULL,
			phone_number_id varchar(100) NOT NULL,
			assigned_user_id bigint(20) UNSIGNED NULL,
			status varchar(50) DEFAULT 'open',
			origin_type varchar(50) DEFAULT NULL,
			origin_source varchar(50) DEFAULT NULL,
			ctwa_clid varchar(255) DEFAULT NULL,
			first_referral_id bigint(20) UNSIGNED DEFAULT NULL,
			last_referral_id bigint(20) UNSIGNED DEFAULT NULL,
			last_inbound_wa_message_id varchar(190) DEFAULT NULL,
			last_customer_message_at datetime DEFAULT NULL,
			customer_service_window_expires_at datetime DEFAULT NULL,
			customer_service_window_status varchar(30) DEFAULT 'closed',
			last_typing_sent_at datetime DEFAULT NULL,
			last_outbound_sent_at datetime DEFAULT NULL,
			last_message_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY contact_id (contact_id),
			KEY status (status),
			KEY last_message_at (last_message_at)
		) $charset_collate;";
		dbDelta( $sql_conversations );

		// WAS-020: Messages table.
		$table_messages = TableNameResolver::get_table_name( 'messages' );
		$sql_messages = "CREATE TABLE $table_messages (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			conversation_id bigint(20) UNSIGNED NOT NULL,
			direction varchar(20) NOT NULL,
			message_type varchar(50) NOT NULL,
			wa_message_id varchar(255) NULL,
			text_body text NULL,
			status varchar(50) DEFAULT 'sent',
			reply_to_message_id bigint(20) UNSIGNED DEFAULT NULL,
			reply_to_wa_message_id varchar(255) DEFAULT NULL,
			context_from varchar(80) DEFAULT NULL,
			context_payload longtext DEFAULT NULL,
			button_text varchar(255) DEFAULT NULL,
			button_payload text DEFAULT NULL,
			interactive_type varchar(80) DEFAULT NULL,
			interactive_id varchar(190) DEFAULT NULL,
			interactive_title text DEFAULT NULL,
			interactive_description text DEFAULT NULL,
			latitude decimal(10, 7) DEFAULT NULL,
			longitude decimal(10, 7) DEFAULT NULL,
			location_name text DEFAULT NULL,
			location_address text DEFAULT NULL,
			contacts_json longtext DEFAULT NULL,
			order_json longtext DEFAULT NULL,
			referral_id bigint(20) UNSIGNED DEFAULT NULL,
			raw_payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY wa_message_id (wa_message_id),
			KEY conversation_id (conversation_id),
			KEY tenant_id (tenant_id),
			KEY reply_to_message_id (reply_to_message_id),
			KEY reply_to_wa_message_id (reply_to_wa_message_id),
			KEY referral_id (referral_id),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_messages );

		// WAS-031: Message Referrals table.
		$table_referrals = TableNameResolver::get_table_name( 'message_referrals' );
		$sql_referrals = "CREATE TABLE $table_referrals (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			conversation_id bigint(20) UNSIGNED DEFAULT NULL,
			message_id bigint(20) UNSIGNED DEFAULT NULL,
			source_type varchar(50) DEFAULT NULL,
			source_id varchar(190) DEFAULT NULL,
			source_url text DEFAULT NULL,
			headline text DEFAULT NULL,
			body text DEFAULT NULL,
			media_type varchar(50) DEFAULT NULL,
			image_url text DEFAULT NULL,
			video_url text DEFAULT NULL,
			thumbnail_url text DEFAULT NULL,
			ctwa_clid varchar(255) DEFAULT NULL,
			raw_referral longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY conversation_id (conversation_id),
			KEY message_id (message_id),
			KEY source_type (source_type),
			KEY source_id (source_id),
			KEY ctwa_clid (ctwa_clid)
		) $charset_collate;";
		dbDelta( $sql_referrals );

		// WAS-021: Message Statuses table.
		$table_msg_statuses = TableNameResolver::get_table_name( 'message_statuses' );
		$sql_msg_statuses = "CREATE TABLE $table_msg_statuses (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			wa_message_id varchar(255) NOT NULL,
			status varchar(50) NOT NULL,
			raw_payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY wa_message_id (wa_message_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_msg_statuses );

		// WAS-022: Message Templates table.
		$table_templates = TableNameResolver::get_table_name( 'message_templates' );
		$sql_templates = "CREATE TABLE $table_templates (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			whatsapp_account_id bigint(20) UNSIGNED NOT NULL,
			meta_template_id varchar(190) NULL,
			waba_id varchar(100) NOT NULL,
			name varchar(190) NOT NULL,
			category varchar(80) NOT NULL,
			language varchar(20) NOT NULL DEFAULT 'pt_BR',
			status varchar(50) DEFAULT 'draft',
			friendly_payload longtext NULL,
			meta_payload longtext NULL,
			variable_map longtext NULL,
			header_type varchar(50) NULL,
			body_text longtext NOT NULL,
			footer_text text NULL,
			components_json longtext NULL,
			buttons_json longtext NULL,
			rejection_reason text NULL,
			last_meta_error text NULL,
			submitted_at datetime NULL,
			approved_at datetime NULL,
			rejected_at datetime NULL,
			paused_at datetime NULL,
			deleted_at datetime NULL,
			synced_at datetime NULL,
			template_family varchar(50) DEFAULT NULL,
			authentication_type varchar(50) DEFAULT NULL,
			code_expiration_minutes int(11) DEFAULT NULL,
			add_security_recommendation tinyint(1) DEFAULT 1,
			otp_type varchar(50) DEFAULT NULL,
			package_name varchar(190) DEFAULT NULL,
			signature_hash varchar(190) DEFAULT NULL,
			autofill_text varchar(190) DEFAULT NULL,
			zero_tap_terms_accepted tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_waba_template_lang (tenant_id, waba_id, name, language),
			KEY tenant_id (tenant_id),
			KEY waba_id (waba_id),
			KEY status (status),
			KEY category (category)
		) $charset_collate;";
		dbDelta( $sql_templates );

		// WAS-023: Media table.
		$table_media = TableNameResolver::get_table_name( 'media' );
		$sql_media = "CREATE TABLE $table_media (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			conversation_id bigint(20) UNSIGNED DEFAULT NULL,
			message_id bigint(20) UNSIGNED DEFAULT NULL,
			meta_media_id varchar(190) NULL,
			wp_attachment_id bigint(20) UNSIGNED NULL,
			media_type varchar(50) NOT NULL,
			mime_type varchar(100) DEFAULT NULL,
			filename varchar(190) DEFAULT NULL,
			original_filename varchar(190) DEFAULT NULL,
			file_size bigint(20) UNSIGNED DEFAULT NULL,
			sha256 varchar(190) DEFAULT NULL,
			storage_provider varchar(50) DEFAULT 'wordpress',
			storage_path text DEFAULT NULL,
			public_url text DEFAULT NULL,
			direction varchar(20) DEFAULT NULL,
			status varchar(50) DEFAULT 'created',
			meta_payload longtext DEFAULT NULL,
			error_message text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY conversation_id (conversation_id),
			KEY message_id (message_id),
			KEY meta_media_id (meta_media_id),
			KEY media_type (media_type),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_media );

		// WAS-024: Webhook Events table.
		$table_webhooks = TableNameResolver::get_table_name( 'webhook_events' );
		$sql_webhooks = "CREATE TABLE $table_webhooks (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED DEFAULT NULL,
			waba_id varchar(100) DEFAULT NULL,
			phone_number_id varchar(100) DEFAULT NULL,
			event_type varchar(100) DEFAULT 'unknown',
			payload longtext NOT NULL,
			processing_status varchar(50) DEFAULT 'pending',
			signature_valid tinyint(1) DEFAULT 0,
			error_message text DEFAULT NULL,
			received_at datetime NOT NULL,
			processed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY waba_id (waba_id),
			KEY processing_status (processing_status),
			KEY received_at (received_at)
		) $charset_collate;";
		dbDelta( $sql_webhooks );

		// WAS-025: Audit Logs table.
		$table_audit = TableNameResolver::get_table_name( 'audit_logs' );
		$sql_audit = "CREATE TABLE $table_audit (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NULL,
			user_id bigint(20) UNSIGNED NULL,
			action varchar(100) NOT NULL,
			entity_type varchar(100) NULL,
			entity_id varchar(100) NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action (action),
			KEY created_at (created_at),
			KEY tenant_id (tenant_id)
		) $charset_collate;";
		dbDelta( $sql_audit );

		// WAS-026: Settings table.
		$table_settings = TableNameResolver::get_table_name( 'settings' );
		$sql_settings = "CREATE TABLE $table_settings (
			tenant_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			setting_key varchar(100) NOT NULL,
			setting_value longtext NULL,
			autoload varchar(20) DEFAULT 'yes',
			PRIMARY KEY  (tenant_id, setting_key),
			KEY setting_key (setting_key)
		) $charset_collate;";
		dbDelta( $sql_settings );

		// WAS-027: Onboarding Sessions table.
		$table_onboarding = TableNameResolver::get_table_name( 'onboarding_sessions' );
		$sql_onboarding = "CREATE TABLE $table_onboarding (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			session_uuid varchar(100) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'started',
			meta_code text DEFAULT NULL,
			waba_id varchar(100) DEFAULT NULL,
			phone_number_id varchar(100) DEFAULT NULL,
			business_id varchar(100) DEFAULT NULL,
			error_message text DEFAULT NULL,
			raw_session_payload longtext DEFAULT NULL,
			started_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			failed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_uuid (session_uuid),
			KEY tenant_id (tenant_id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_onboarding );

		// WAS-028: Health Checks table.
		$table_health = TableNameResolver::get_table_name( 'health_checks' );
		$sql_health = "CREATE TABLE $table_health (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			check_type varchar(100) NOT NULL,
			entity_type varchar(100) DEFAULT NULL,
			entity_id bigint(20) UNSIGNED DEFAULT NULL,
			tenant_id bigint(20) UNSIGNED DEFAULT NULL,
			status varchar(50) NOT NULL,
			message text DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			checked_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY check_type (check_type),
			KEY status (status),
			KEY checked_at (checked_at)
		) $charset_collate;";
		dbDelta( $sql_health );

		// WAS-029: Admin Audit Logs table.
		$table_admin_audit = TableNameResolver::get_table_name( 'admin_audit_logs' );
		$sql_admin_audit = "CREATE TABLE $table_admin_audit (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			tenant_id bigint(20) UNSIGNED DEFAULT NULL,
			action varchar(190) NOT NULL,
			entity_type varchar(100) DEFAULT NULL,
			entity_id varchar(100) DEFAULT NULL,
			ip_address varchar(100) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY tenant_id (tenant_id),
			KEY action (action),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_admin_audit );

		// WAS-030: App Review Checklist table.
		$table_review = TableNameResolver::get_table_name( 'app_review_checklist' );
		$sql_review = "CREATE TABLE $table_review (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			meta_app_id bigint(20) UNSIGNED NOT NULL,
			item_key varchar(190) NOT NULL,
			label varchar(190) NOT NULL,
			status varchar(50) DEFAULT 'pending',
			evidence_url text DEFAULT NULL,
			notes text DEFAULT NULL,
			updated_by bigint(20) UNSIGNED DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY app_item (meta_app_id, item_key)
		) $charset_collate;";
		dbDelta( $sql_review );

		// Meta API Logs table.
		$table_meta_logs = TableNameResolver::get_table_name( 'meta_api_logs' );
		$sql_meta_logs = "CREATE TABLE $table_meta_logs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NULL,
			operation varchar(100) NOT NULL,
			method varchar(10) NOT NULL,
			path text NOT NULL,
			request_payload longtext NULL,
			response_body longtext NULL,
			status_code int(5) NOT NULL,
			success tinyint(1) NOT NULL,
			error_code varchar(50) NULL,
			error_subcode varchar(50) NULL,
			error_message text NULL,
			duration_ms int(10) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY operation (operation),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_meta_logs );

		// WAS-032: Auth Code Attempts table.
		$table_auth_attempts = TableNameResolver::get_table_name( 'auth_code_attempts' );
		$sql_auth_attempts = "CREATE TABLE $table_auth_attempts (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			contact_phone varchar(50) NOT NULL,
			template_id bigint(20) UNSIGNED NOT NULL,
			code_hash varchar(255) DEFAULT NULL,
			purpose varchar(50) DEFAULT NULL,
			wa_message_id varchar(190) DEFAULT NULL,
			status varchar(50) DEFAULT 'sent_to_meta',
			expires_at datetime DEFAULT NULL,
			sent_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY contact_phone (contact_phone),
			KEY wa_message_id (wa_message_id)
		) $charset_collate;";
		dbDelta( $sql_auth_attempts );

		// WAS-033: Meta OAuth Logs table.
		$table_oauth_logs = TableNameResolver::get_table_name( 'meta_oauth_logs' );
		$sql_oauth_logs = "CREATE TABLE $table_oauth_logs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED DEFAULT NULL,
			session_uuid varchar(100) DEFAULT NULL,
			state varchar(190) DEFAULT NULL,
			code_preview varchar(40) DEFAULT NULL,
			error_code varchar(100) DEFAULT NULL,
			error_message text DEFAULT NULL,
			raw_payload longtext DEFAULT NULL,
			ip_address varchar(100) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY session_uuid (session_uuid),
			KEY state (state),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_oauth_logs );

		// WAS-034: Meta Deauthorize Logs table.
		$table_deauthorize_logs = TableNameResolver::get_table_name( 'meta_deauthorize_logs' );
		$sql_deauthorize_logs = "CREATE TABLE $table_deauthorize_logs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			signed_request text DEFAULT NULL,
			raw_payload longtext DEFAULT NULL,
			ip_address varchar(100) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			processed_status varchar(50) DEFAULT 'received',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY processed_status (processed_status),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_deauthorize_logs );

		// WAS-035: Data Deletion Requests table.
		$table_deletion_requests = TableNameResolver::get_table_name( 'data_deletion_requests' );
		$sql_deletion_requests = "CREATE TABLE $table_deletion_requests (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			request_uuid varchar(100) NOT NULL,
			tenant_id bigint(20) UNSIGNED DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			meta_user_id varchar(190) DEFAULT NULL,
			signed_request text DEFAULT NULL,
			raw_payload longtext DEFAULT NULL,
			status varchar(50) DEFAULT 'pending',
			confirmation_code varchar(100) DEFAULT NULL,
			status_url text DEFAULT NULL,
			processed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY request_uuid (request_uuid),
			KEY tenant_id (tenant_id),
			KEY status (status),
			KEY meta_user_id (meta_user_id)
		) $charset_collate;";
		dbDelta( $sql_deletion_requests );
	}

	/**
	 * Create custom capabilities and roles.
	 */
	private static function create_capabilities() {
		Capabilities::register();
	}

	/**
	 * Seed initial data.
	 */
	private static function seed_data() {
		global $wpdb;

		$table_tenants = TableNameResolver::get_table_name( 'tenants' );
		$table_users   = TableNameResolver::get_table_name( 'tenant_users' );

		// Check if we already have tenants.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_tenants" );
		if ( $count > 0 ) {
			return;
		}

		// Create default tenant for the first administrator found.
		$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
		if ( empty( $admins ) ) {
			return;
		}

		$admin = $admins[0];

		$wpdb->insert( $table_tenants, [
			'name'       => 'Minha Empresa Demo',
			'slug'       => 'demo',
			'status'     => 'active',
			'plan'       => 'free',
			'created_at' => current_time( 'mysql', true ),
		] );

		$tenant_id = $wpdb->insert_id;

		$wpdb->insert( $table_users, [
			'tenant_id' => $tenant_id,
			'user_id'   => $admin->ID,
			'role'      => 'platform_owner',
			'status'    => 'active',
		] );

		// Set as current tenant for this user.
		update_user_meta( $admin->ID, '_was_current_tenant_id', $tenant_id );
	}
}

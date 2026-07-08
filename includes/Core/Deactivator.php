<?php
/**
 * Deactivator class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivator.
 */
class Deactivator {

	/**
	 * Run deactivation logic.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'was_router_process_outbox' );
		wp_clear_scheduled_hook( 'was_router_sync_template_statuses' );
		wp_clear_scheduled_hook( 'was_router_process_onboarding_reconciliation' );
	}
}

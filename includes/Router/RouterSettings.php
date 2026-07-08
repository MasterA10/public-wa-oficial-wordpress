<?php

namespace WAS\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RouterSettings {

	public static function get_service_secret() {
		if ( defined( 'WAS_ROUTER_SERVICE_SECRET' ) && WAS_ROUTER_SERVICE_SECRET ) {
			return (string) WAS_ROUTER_SERVICE_SECRET;
		}

		return (string) get_option( 'was_router_service_secret', '' );
	}

	public static function get_route_secret() {
		if ( defined( 'WAS_ROUTER_ROUTE_SECRET' ) && WAS_ROUTER_ROUTE_SECRET ) {
			return (string) WAS_ROUTER_ROUTE_SECRET;
		}

		$secret = (string) get_option( 'was_router_route_secret', '' );
		if ( $secret ) {
			return $secret;
		}

		return self::get_service_secret();
	}

	public static function get_default_route_target_url() {
		if ( defined( 'WAS_ROUTER_ROUTE_TARGET_URL' ) && WAS_ROUTER_ROUTE_TARGET_URL ) {
			return (string) WAS_ROUTER_ROUTE_TARGET_URL;
		}

		return (string) get_option( 'was_router_route_target_url', '' );
	}

	public static function get_onboarding_callback_timeout() {
		return (int) get_option( 'was_router_onboarding_callback_timeout', 15 );
	}

	public static function get_onboarding_processing_stale_seconds() {
		return max( 1, (int) get_option( 'was_router_onboarding_processing_stale_seconds', 120 ) );
	}

	public static function should_auto_subscribe_webhooks() {
		return (bool) get_option( 'was_router_auto_subscribe_webhooks', true );
	}
}

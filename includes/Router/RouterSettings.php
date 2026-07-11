<?php

namespace WAS\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RouterSettings {

	public static function get_meta_oauth_redirect_uri() {
		if ( defined( 'WAS_META_ONBOARDING_REDIRECT_URI' ) && WAS_META_ONBOARDING_REDIRECT_URI ) {
			return (string) WAS_META_ONBOARDING_REDIRECT_URI;
		}
		if ( getenv( 'META_ONBOARDING_REDIRECT_URI' ) ) {
			return (string) getenv( 'META_ONBOARDING_REDIRECT_URI' );
		}
		$value = (string) get_option( 'was_meta_onboarding_redirect_uri', '' );
		if ( $value ) {
			return $value;
		}
		if ( function_exists( 'rest_url' ) ) {
			return rest_url( 'was/v1/meta/oauth/callback' );
		}
		return '';
	}

	public static function get_meta_embedded_signup_base_url( $version = null ) {
		if ( defined( 'WAS_META_EMBEDDED_SIGNUP_BASE_URL' ) && WAS_META_EMBEDDED_SIGNUP_BASE_URL ) {
			return (string) WAS_META_EMBEDDED_SIGNUP_BASE_URL;
		}
		if ( getenv( 'META_EMBEDDED_SIGNUP_BASE_URL' ) ) {
			return (string) getenv( 'META_EMBEDDED_SIGNUP_BASE_URL' );
		}
		$version = $version ?: ( defined( 'WAS_META_GRAPH_DEFAULT_VERSION' ) ? WAS_META_GRAPH_DEFAULT_VERSION : 'v25.0' );
		return 'https://www.facebook.com/' . rawurlencode( $version ) . '/dialog/oauth';
	}

	public static function get_onboarding_ttl_seconds() {
		return max( 60, (int) get_option( 'was_router_onboarding_ttl_seconds', 900 ) );
	}

	public static function get_service_secret() {
		if ( defined( 'WAS_ROUTER_SERVICE_SECRET' ) && WAS_ROUTER_SERVICE_SECRET ) {
			return (string) WAS_ROUTER_SERVICE_SECRET;
		}
		if ( getenv( 'WAS_ROUTER_SERVICE_SECRET' ) ) {
			return (string) getenv( 'WAS_ROUTER_SERVICE_SECRET' );
		}

		return (string) get_option( 'was_router_service_secret', '' );
	}

	public static function get_route_secret() {
		if ( defined( 'WAS_ROUTER_ROUTE_SECRET' ) && WAS_ROUTER_ROUTE_SECRET ) {
			return (string) WAS_ROUTER_ROUTE_SECRET;
		}
		if ( getenv( 'WAS_ROUTER_ROUTE_SECRET' ) ) {
			return (string) getenv( 'WAS_ROUTER_ROUTE_SECRET' );
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
		if ( getenv( 'WAS_ROUTER_ROUTE_TARGET_URL' ) ) {
			return (string) getenv( 'WAS_ROUTER_ROUTE_TARGET_URL' );
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

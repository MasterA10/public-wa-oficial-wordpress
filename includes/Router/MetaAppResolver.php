<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaAppRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MetaAppResolver {

	public function resolve_for_webhook_payload( array $payload, $decrypt_secret = true ) {
		$phone_number_id = $this->phone_number_id_from_payload( $payload );
		if ( $phone_number_id ) {
			$app = $this->find_app_by_phone_number_id( $phone_number_id, $decrypt_secret );
			if ( $app ) {
				return $app;
			}
		}
		$meta_waba_id = $payload['entry'][0]['id'] ?? null;
		if ( $meta_waba_id ) {
			$app = $this->find_app_by_waba_id( (string) $meta_waba_id, $decrypt_secret );
			if ( $app ) {
				return $app;
			}
		}

		return ( new MetaAppRepository() )->get_active_app( $decrypt_secret );
	}

	/** Resolve from raw bytes without deserializing the webhook body. */
	public function resolve_for_webhook_raw_body( $raw_body, $decrypt_secret = true ) {
		if ( preg_match( '/"phone_number_id"\s*:\s*"?([A-Za-z0-9_-]+)"?/i', (string) $raw_body, $match ) ) {
			$app = $this->find_app_by_phone_number_id( $match[1], $decrypt_secret );
			if ( $app ) {
				return $app;
			}
		}
		if ( preg_match( '/"entry"\s*:\s*\[\s*\{[^}]*"id"\s*:\s*"?([A-Za-z0-9_-]+)"?/is', (string) $raw_body, $match ) ) {
			$app = $this->find_app_by_waba_id( $match[1], $decrypt_secret );
			if ( $app ) {
				return $app;
			}
		}
		return ( new MetaAppRepository() )->get_active_app( $decrypt_secret );
	}

	private function find_app_by_phone_number_id( $phone_number_id, $decrypt_secret ) {
		global $wpdb;
		$phones = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$accounts = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$apps = TableNameResolver::get_table_name( 'meta_apps' );
		$app = $wpdb->get_row( $wpdb->prepare( "SELECT ma.* FROM $apps ma INNER JOIN $accounts wa ON wa.meta_app_id = ma.id INNER JOIN $phones wp ON wp.whatsapp_account_id = wa.id WHERE wp.phone_number_id = %s LIMIT 1", (string) $phone_number_id ) );
		if ( $app && $decrypt_secret && ! empty( $app->app_secret ) ) {
			$app->app_secret = SecretVault::decrypt( $app->app_secret );
		}
		return $app;
	}

	private function phone_number_id_from_payload( array $payload ) {
		foreach ( $payload['entry'] ?? [] as $entry ) {
			foreach ( $entry['changes'] ?? [] as $change ) {
				$value = $change['value'] ?? [];
				if ( ! empty( $value['metadata']['phone_number_id'] ) ) {
					return (string) $value['metadata']['phone_number_id'];
				}
				if ( ! empty( $value['phone_number_id'] ) ) {
					return (string) $value['phone_number_id'];
				}
			}
		}
		return null;
	}

	private function find_app_by_waba_id( $meta_waba_id, $decrypt_secret ) {
		global $wpdb;

		$accounts = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$apps = TableNameResolver::get_table_name( 'meta_apps' );
		$app = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ma.* FROM $apps ma INNER JOIN $accounts wa ON wa.meta_app_id = ma.id WHERE wa.waba_id = %s LIMIT 1",
				$meta_waba_id
			)
		);

		if ( $app && $decrypt_secret && ! empty( $app->app_secret ) ) {
			$app->app_secret = SecretVault::decrypt( $app->app_secret );
		}

		return $app;
	}
}

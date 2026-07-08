<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\Meta\MetaAppRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MetaAppResolver {

	public function resolve_for_webhook_payload( array $payload, $decrypt_secret = true ) {
		$meta_waba_id = $payload['entry'][0]['id'] ?? null;
		if ( $meta_waba_id ) {
			$app = $this->find_app_by_waba_id( (string) $meta_waba_id, $decrypt_secret );
			if ( $app ) {
				return $app;
			}
		}

		return ( new MetaAppRepository() )->get_active_app( $decrypt_secret );
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

<?php

use WAS\Core\TableNameResolver;
use WAS\Router\OnboardingService;

class OnboardingBundleTest extends WAS_Router_TestCase {

	public function test_creates_full_onboarding_bundle_with_internal_ids_and_default_route() {
		$result = ( new OnboardingService() )->create_bundle(
			[
				'tenant'       => [
					'name'   => 'Agenda Master',
					'slug'   => 'agenda-master',
					'status' => 'active',
				],
				'meta_app'     => [
					'name'         => 'Meta Oficial',
					'app_id'       => 'meta-app-id',
					'app_secret'   => 'meta-app-secret',
					'verify_token' => 'verify-token',
				],
				'waba'         => [
					'waba_id'      => 'meta-waba-1',
					'name'         => 'Studio WABA',
					'business_id'  => 'business-1',
					'access_token' => 'waba-access-token',
					'status'       => 'active',
				],
				'phone_number' => [
					'phone_number_id'      => 'meta-phone-1',
					'display_phone_number' => '+55 11 99999-9999',
					'verified_name'        => 'Studio',
					'status'               => 'active',
				],
				'route'        => [
					'name'       => 'Agenda webhook',
					'target_url' => 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/',
					'secret'     => 'route-secret',
				],
			]
		);

		$this->assert_false( is_wp_error( $result ) );
		$this->assert_same( 1, $result['tenant_id'] );
		$this->assert_same( 1, $result['meta_app_id'] );
		$this->assert_same( 1, $result['waba_id'] );
		$this->assert_same( 1, $result['phone_number_id'] );
		$this->assert_same( 1, $result['route_id'] );
		$this->assert_same( 1, $result['router_waba_id'] );
		$this->assert_same( 1, $result['router_phone_number_id'] );
		$this->assert_same( 1, $result['router_route_id'] );
		$this->assert_same( 'meta-waba-1', $result['meta_waba_id'] );
		$this->assert_same( 'meta-phone-1', $result['meta_phone_number_id'] );
		$this->assert_same( 'meta-phone-1', $result['whatsapp_phone_number_id'] );
		$this->assert_same( '+55 11 99999-9999', $result['display_phone_number'] );

		$phone = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::get_table_name( 'whatsapp_phone_numbers' ) . ' WHERE id = 1 LIMIT 1' );
		$route = $GLOBALS['wpdb']->get_row( 'SELECT * FROM ' . TableNameResolver::getRoutesTable() . ' WHERE id = 1 LIMIT 1' );
		$tokens = $GLOBALS['wpdb']->tables[ TableNameResolver::get_table_name( 'meta_tokens' ) ] ?? [];

		$this->assert_same( 1, (int) $phone->default_route_id );
		$this->assert_same( 'https://agenda.dominai.cloud/whatsapp/webhooks/waba-router/', $route->target_url );
		$this->assert_count( 1, $tokens );
		$this->assert_same( 'active', $tokens[0]['status'] );
	}
}

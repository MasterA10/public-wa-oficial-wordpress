<?php
/**
 * PhoneNumberDiagnosticsService class.
 *
 * @package WAS\WhatsApp
 */

namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for performing detailed phone number diagnostics with Meta API.
 */
class PhoneNumberDiagnosticsService {

	private $api_client;
	private $phone_repository;

	public function __construct() {
		$this->api_client       = new MetaApiClient();
		$this->phone_repository = new PhoneNumberRepository();
	}

	/**
	 * Run diagnostics for all numbers in a WABA.
	 *
	 * @param int    $tenant_id Tenant ID.
	 * @param string $waba_id   WABA ID.
	 * @param string $token     Access Token.
	 * @return array
	 */
	public function run( $tenant_id, $waba_id, $token ) {
		$listResponse = $this->api_client->get(
			'waba.phone_numbers',
			[ 'waba_id' => $waba_id ],
			[
				'fields' => 'id,display_phone_number,verified_name,status,quality_rating,messaging_limit_tier,account_mode',
			],
			$token
		);

		$numbers = [];

		foreach ( ( $listResponse['data'] ?? [] ) as $number ) {
			$phoneNumberId = $number['id'];

			$details = $this->safeGetPhoneDetails( $phoneNumberId, $token );
			$profile = $this->safeGetBusinessProfile( $phoneNumberId, $token );

			$merged = array_merge( $number, [
				'details'          => $details,
				'business_profile' => $profile,
			] );

			$this->phone_repository->upsertFromDiagnostics( $tenant_id, $waba_id, $merged );

			$numbers[] = [
				'phone_number_id'      => $phoneNumberId,
				'display_phone_number' => $number['display_phone_number'] ?? null,
				'verified_name'        => $number['verified_name'] ?? null,
				'status'               => $details['status'] ?? $number['status'] ?? null,
				'name_status'          => $details['name_status'] ?? null,
				'quality_rating'       => $details['quality_rating'] ?? $number['quality_rating'] ?? null,
				'messaging_limit_tier' => $details['messaging_limit_tier'] ?? $number['messaging_limit_tier'] ?? null,
				'account_mode'         => $number['account_mode'] ?? null,
				'health_status'        => $details['health_status'] ?? null,
				'business_profile'     => $profile,
			];
		}

		return [
			'status'  => count( $numbers ) > 0 ? 'success' : 'warning',
			'message' => count( $numbers ) . ' número(s) vinculado(s) à WABA.',
			'numbers' => $numbers,
		];
	}

	private function safeGetPhoneDetails( $phoneNumberId, $token ) {
		try {
			return $this->api_client->get(
				'phone.get',
				[ 'phone_number_id' => $phoneNumberId ],
				[
					'fields' => 'id,display_phone_number,verified_name,quality_rating,messaging_limit_tier,status,name_status,health_status',
				],
				$token
			);
		} catch ( Throwable $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}

	private function safeGetBusinessProfile( $phoneNumberId, $token ) {
		try {
			$res = $this->api_client->get(
				'phone.business_profile',
				[ 'phone_number_id' => $phoneNumberId ],
				[
					'fields' => 'messaging_product,about,address,description,email,profile_picture_url,websites,vertical',
				],
				$token
			);
            if (!$res['success']) {
                return [
                    'status' => 'error',
                    'message' => $res['error'] ?? 'Acesso negado ou erro na Meta.'
                ];
            }
            return $res;
		} catch ( Throwable $e ) {
			return [
				'status'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}
}

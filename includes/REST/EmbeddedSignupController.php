<?php
/**
 * EmbeddedSignupController class.
 *
 * @package WAS\REST
 */

namespace WAS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WAS\Auth\OnboardingSessionService;
use WAS\Auth\TenantContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for Embedded Signup REST endpoints.
 */
class EmbeddedSignupController {

	/**
	 * Onboarding Session Service.
	 *
	 * @var OnboardingSessionService
	 */
	private $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new OnboardingSessionService();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( 'was/v1', '/onboarding/whatsapp/start', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'start' ],
			'permission_callback' => [ Routes::class, 'check_auth' ],
		] );

		register_rest_route( 'was/v1', '/onboarding/whatsapp/complete', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'complete' ],
			'permission_callback' => [ Routes::class, 'check_auth' ],
		] );

		register_rest_route( 'was/v1', '/onboarding/whatsapp/cancel', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'cancel' ],
			'permission_callback' => [ Routes::class, 'check_auth' ],
		] );
	}

	/**
	 * Start onboarding session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function start( $request ) {
		$tenant_id = TenantContext::getTenantId();
		$user_id   = get_current_user_id();

		try {
			$session_uuid = $this->service->start( $tenant_id, $user_id );
			return new WP_REST_Response( [
				'success'      => true,
				'session_uuid' => $session_uuid,
			], 200 );
		} catch ( \Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'error'   => $e->getMessage(),
			], 500 );
		}
	}

	/**
	 * Complete onboarding.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function complete( $request ) {
		$session_uuid    = sanitize_text_field( $request->get_param( 'session_uuid' ) );
		$code            = sanitize_text_field( $request->get_param( 'code' ) );
		$waba_id         = sanitize_text_field( $request->get_param( 'waba_id' ) );
		$phone_number_id = sanitize_text_field( $request->get_param( 'phone_number_id' ) );
		$business_id     = sanitize_text_field( $request->get_param( 'business_id' ) );

		if ( ! $session_uuid || ! $code || ! $waba_id || ! $phone_number_id ) {
			return new WP_REST_Response( [
				'success' => false,
				'error'   => 'Missing required parameters.',
			], 400 );
		}

		try {
			$result = $this->service->complete( $session_uuid, $code, $waba_id, $phone_number_id, $business_id );
			return new WP_REST_Response( $result, 200 );
		} catch ( \Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'error'   => $e->getMessage(),
			], 500 );
		}
	}

	/**
	 * Cancel onboarding.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function cancel( $request ) {
		$session_uuid = sanitize_text_field( $request->get_param( 'session_uuid' ) );
		$reason       = sanitize_text_field( $request->get_param( 'reason' ) );

		if ( ! $session_uuid ) {
			return new WP_REST_Response( [
				'success' => false,
				'error'   => 'Missing session UUID.',
			], 400 );
		}

		$this->service->cancel( $session_uuid, $reason );

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}
}

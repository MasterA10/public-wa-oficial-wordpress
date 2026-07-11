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
use WAS\Router\OnboardingService;

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
		register_rest_route( 'was/v1', '/whatsapp/onboarding/start', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'start_embedded_signup' ],
			'permission_callback' => [ Routes::class, 'check_auth' ],
		] );

		register_rest_route( 'was/v1', '/whatsapp/onboarding/attempts/(?P<attempt_id>[A-Za-z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'status' ],
			'permission_callback' => [ Routes::class, 'check_auth' ],
		] );

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

	public function start_embedded_signup( $request ) {
		$tenant_id = TenantContext::getTenantId();
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || ! $params ) {
			$params = $request->get_params();
		}
		$params['tenant_id'] = (int) $tenant_id;
		$result = ( new OnboardingService() )->start_embedded_signup( $params );
		return is_wp_error( $result )
			? new WP_REST_Response( [ 'success' => false, 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ], (int) ( $result->get_error_data()['status'] ?? 500 ) )
			: new WP_REST_Response( $result, 200 );
	}

	public function status( $request ) {
		$result = ( new OnboardingService() )->get_attempt_status( TenantContext::getTenantId(), $request->get_param( 'attempt_id' ) );
		return is_wp_error( $result )
			? new WP_REST_Response( [ 'success' => false, 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ], (int) ( $result->get_error_data()['status'] ?? 500 ) )
			: new WP_REST_Response( $result, 200 );
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

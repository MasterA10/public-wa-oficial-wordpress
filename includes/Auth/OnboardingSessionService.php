<?php
/**
 * OnboardingSessionService class.
 *
 * @package WAS\Auth
 */

namespace WAS\Auth;

use WAS\Meta\MetaOAuthService;
use WAS\Meta\TokenService;
use WAS\WhatsApp\WhatsAppAccountRepository;
use WAS\WhatsApp\PhoneNumberRepository;
use WAS\WhatsApp\WebhookSubscriptionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for coordinating the Embedded Signup onboarding process.
 */
class OnboardingSessionService {

	private $session_repository;
	private $oauth_service;
	private $token_service;
	private $account_repository;
	private $phone_repository;
	private $webhook_service;

	public function __construct() {
		$this->session_repository = new OnboardingSessionRepository();
		$this->oauth_service     = new MetaOAuthService();
		$this->token_service     = new TokenService();
		$this->account_repository = new WhatsAppAccountRepository();
		$this->phone_repository   = new PhoneNumberRepository();
		$this->webhook_service   = new WebhookSubscriptionService();
	}

	/**
	 * Start a new onboarding session.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @param int $user_id   User ID.
	 * @return string Session UUID.
	 * @throws \RuntimeException If Meta App is not configured.
	 */
	public function start( $tenant_id, $user_id ) {
		$app_repo = new \WAS\Meta\MetaAppRepository();
		$app = $app_repo->get_active_app();

		if ( ! $app || empty( $app->app_id ) || empty( $app->app_secret ) ) {
			throw new \RuntimeException( 'Meta App (App ID/Secret) não configurado. Configure as credenciais antes de iniciar o cadastro.' );
		}

		$session_uuid = 'ob_' . bin2hex( random_bytes( 8 ) );
		$this->session_repository->create( $tenant_id, $user_id, $session_uuid );
		return $session_uuid;
	}

	/**
	 * Complete the onboarding process.
	 *
	 * @param string $session_uuid    Session UUID.
	 * @param string $code            OAuth code from Meta.
	 * @param string $waba_id         WABA ID from Meta.
	 * @param string $phone_number_id Phone Number ID from Meta.
	 * @param string $business_id     Business ID from Meta (optional).
	 * @return array Result of the operation.
	 */
	public function complete( $session_uuid, $code, $waba_id, $phone_number_id, $business_id = null ) {
		$session = $this->session_repository->find_by_uuid( $session_uuid );

		if ( ! $session ) {
			throw new \RuntimeException( 'Onboarding session not found.' );
		}

		// 1. Exchange code for token
		$token_data = $this->oauth_service->exchangeCodeForToken( $code );
		$access_token = $token_data['access_token'];

		// 2. Save WhatsApp Account
		$account_id = $this->account_repository->createOrUpdate( [
			'tenant_id'         => $session->tenant_id,
			'waba_id'           => $waba_id,
			'meta_business_id'  => $business_id,
			'status'            => 'active',
		] );

		// 3. Save Phone Number
		$this->phone_repository->createOrUpdate( [
			'tenant_id'           => $session->tenant_id,
			'whatsapp_account_id' => $account_id,
			'phone_number_id'     => $phone_number_id,
			'status'              => 'active',
			'is_default'          => 1,
		] );

		// 4. Store Token
		$this->token_service->store_encrypted_token( $session->tenant_id, $account_id, $access_token );

		// 5. Subscribe WABA to Webhooks
		$this->webhook_service->subscribeWaba( $waba_id, $access_token );

		// 6. Update Session
		$this->session_repository->update( $session_uuid, [
			'status'          => 'connected',
			'meta_code'       => $code,
			'waba_id'         => $waba_id,
			'phone_number_id' => $phone_number_id,
			'business_id'     => $business_id,
			'completed_at'    => current_time( 'mysql', true ),
		] );

		return [
			'success'         => true,
			'waba_id'         => $waba_id,
			'phone_number_id' => $phone_number_id,
		];
	}

	/**
	 * Cancel the onboarding process.
	 *
	 * @param string $session_uuid Session UUID.
	 * @param string $reason       Reason for cancellation.
	 */
	public function cancel( $session_uuid, $reason = 'user_cancelled' ) {
		$this->session_repository->update( $session_uuid, [
			'status'        => 'cancelled',
			'error_message' => $reason,
			'failed_at'     => current_time( 'mysql', true ),
		] );
	}
}

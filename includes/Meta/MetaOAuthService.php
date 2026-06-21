<?php
/**
 * MetaOAuthService class.
 *
 * @package WAS\Meta
 */

namespace WAS\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for handling Meta OAuth operations.
 */
class MetaOAuthService {

	/**
	 * Meta App Repository.
	 *
	 * @var MetaAppRepository
	 */
	private $app_repository;

	/**
	 * Meta API Client.
	 *
	 * @var MetaApiClient
	 */
	private $api_client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->app_repository = new MetaAppRepository();
		$this->api_client     = new MetaApiClient();
	}

	/**
	 * Exchange a code for an access token.
	 *
	 * @param string $code The code from Embedded Signup.
	 * @return array
	 * @throws \RuntimeException If the exchange fails.
	 */
	public function exchangeCodeForToken( $code ) {
		$app = $this->app_repository->get_active_app();

		if ( ! $app || ! $app->app_id || ! $app->app_secret ) {
			throw new \RuntimeException( 'Meta App credentials not configured.' );
		}

		$query = [
			'client_id'     => $app->app_id,
			'client_secret' => $app->app_secret,
			'code'          => $code,
		];

		// We use the raw GET call because this doesn't use a Bearer token.
		$result = $this->api_client->get( 'oauth.access_token', [], $query, '' );

		if ( ! $result['success'] ) {
			throw new \RuntimeException( $result['error'] ?? 'Failed to exchange code for token.' );
		}

		if ( empty( $result['access_token'] ) ) {
			throw new \RuntimeException( 'Meta did not return an access_token.' );
		}

		return $result;
	}
}

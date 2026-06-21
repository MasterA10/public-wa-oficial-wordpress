<?php
/**
 * TokenDebugService class.
 *
 * @package WAS\Meta
 */

namespace WAS\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for debugging Meta Access Tokens.
 */
class TokenDebugService {

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
		$this->api_client = new MetaApiClient();
	}

	/**
	 * Debug a token using the Meta debug_token endpoint.
	 *
	 * @param string $input_token The token to debug.
	 * @return array
	 */
	public function debugToken( $input_token ) {
		$app_repo = new MetaAppRepository();
		$app = $app_repo->get_active_app();

		if ( ! $app || empty( $app->app_id ) || empty( $app->app_secret ) ) {
			return [ 'success' => false, 'error' => 'Meta App not configured.' ];
		}

        $app_secret = TokenVault::decrypt($app->app_secret);
		$app_access_token = $app->app_id . '|' . $app_secret;

		$query = [
			'input_token'  => $this->normalizeToken($input_token),
			'access_token' => $app_access_token,
		];

		// debug_token is a special endpoint
		$url = "https://graph.facebook.com/debug_token";
        $url = add_query_arg($query, $url);

        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['success' => false, 'error' => $body['error']['message'] ?? 'Meta API Error'];
        }

        return [
            'success' => true,
            'data'    => $body['data'] ?? []
        ];
	}

    private function normalizeToken($token) {
        $token = trim($token);
        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }
        return $token;
    }
}

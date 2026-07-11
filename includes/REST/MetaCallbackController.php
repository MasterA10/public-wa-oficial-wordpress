<?php
namespace WAS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WAS\Meta\OAuthLogRepository;
use WAS\Meta\DeauthorizeLogRepository;
use WAS\Compliance\DataDeletionRepository;
use WAS\Router\OnboardingService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for Meta callback endpoints.
 */
class MetaCallbackController {

    /**
     * Handle OAuth callback from Meta.
     */
    public function oauth_callback(WP_REST_Request $request) {
        $params = $request->get_params();

        $code = isset($params['code']) ? sanitize_text_field($params['code']) : null;
        $state = isset($params['state']) ? sanitize_text_field($params['state']) : null;
        $error = isset($params['error']) ? sanitize_text_field($params['error']) : null;
        $errorDescription = isset($params['error_description']) ? sanitize_text_field($params['error_description']) : null;

        OAuthLogRepository::insert([
            'state' => $state ? hash('sha256', $state) : null,
            'code_preview' => $code ? 'received' : null,
            'error_code' => $error,
            'error_message' => $errorDescription,
            // Nunca persistir code/state em claro nem devolver o payload OAuth.
            'raw_payload' => wp_json_encode([
                'has_code' => (bool) $code,
                'has_state' => (bool) $state,
                'error' => $error,
            ]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => current_time('mysql'),
        ]);

        $result = ( new OnboardingService() )->handle_oauth_callback([
            'code' => $code,
            'state' => $state,
            'error' => $error,
            'error_description' => $errorDescription,
        ]);
        if ( is_wp_error( $result ) ) {
            $status = (int) ( $result->get_error_data()['status'] ?? 400 );
            return new WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], $status);
        }

        return new WP_REST_Response( array_merge( [ 'success' => true ], is_array( $result ) ? $result : [] ), 200 );
    }

    /**
     * Handle Deauthorize callback from Meta.
     */
    public function deauthorize_callback(WP_REST_Request $request) {
        $params = $request->get_params();

        $signedRequest = isset($params['signed_request'])
            ? sanitize_text_field($params['signed_request'])
            : null;

        DeauthorizeLogRepository::insert([
            'signed_request' => $signedRequest,
            'raw_payload' => wp_json_encode($params),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'processed_status' => 'received',
            'created_at' => current_time('mysql'),
        ]);

        // Phase 1: Only logging. 
        // TODO: Validate signed_request and identify tenant to revoke tokens.

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Deauthorization received.',
        ], 200);
    }

    /**
     * Handle Data Deletion callback from Meta.
     */
    public function data_deletion_callback(WP_REST_Request $request) {
        $params = $request->get_params();

        $uuid = wp_generate_uuid4();
        $confirmationCode = 'DEL-' . strtoupper(substr(str_replace('-', '', $uuid), 0, 12));
        $statusUrl = home_url('/data-deletion-status?request=' . rawurlencode($uuid));

        DataDeletionRepository::insert([
            'request_uuid' => $uuid,
            'signed_request' => isset($params['signed_request']) ? sanitize_text_field($params['signed_request']) : null,
            'raw_payload' => wp_json_encode($params),
            'status' => 'pending',
            'confirmation_code' => $confirmationCode,
            'status_url' => $statusUrl,
            'created_at' => current_time('mysql'),
        ]);

        return new WP_REST_Response([
            'url' => $statusUrl,
            'confirmation_code' => $confirmationCode,
        ], 200);
    }
}

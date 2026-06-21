<?php
namespace WAS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WAS\Meta\OAuthLogRepository;
use WAS\Meta\DeauthorizeLogRepository;
use WAS\Compliance\DataDeletionRepository;

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
            'state' => $state,
            'code_preview' => $code ? substr($code, 0, 12) . '...' : null,
            'error_code' => $error,
            'error_message' => $errorDescription,
            'raw_payload' => wp_json_encode($params),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => current_time('mysql'),
        ]);

        if ($error) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $error,
                'message' => $errorDescription ?: 'Erro no fluxo OAuth.',
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'service' => 'Meta OAuth Callback',
            'message' => $code ? 'Code recebido.' : 'Callback ativo.',
        ], 200);
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

<?php
namespace WAS\Meta;

use WAS\Meta\MetaAppRepository;

if (!defined('ABSPATH')) {
    exit;
}

class MetaApiClient {
    private $version;
    private $baseUrl = 'https://graph.facebook.com';

    public function __construct() {
        $repository = new MetaAppRepository();
        $app = $repository->get_active_app();
        $this->version = $app->graph_version ?? 'v25.0';
    }

    public function postJson(string $operation, array $pathParams, array $body, string $token) {
        $url = $this->buildUrl($operation, $pathParams);
        $start_time = microtime(true);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        $result = $this->parse($response);
        $duration = (int)((microtime(true) - $start_time) * 1000);

        MetaApiRequestLogger::log(
            $operation,
            'POST',
            $url,
            $result['code'] ?? wp_remote_retrieve_response_code($response),
            $result['success'],
            is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response),
            $duration,
            !$result['success'] ? ['message' => $result['error'] ?? ''] : [],
            $body
        );

        return $result;
    }

    public function get(string $operation, array $pathParams, array $query, string $token) {
        $url = add_query_arg($query, $this->buildUrl($operation, $pathParams));
        $start_time = microtime(true);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 30,
        ]);

        $result = $this->parse($response);
        $duration = (int)((microtime(true) - $start_time) * 1000);

        MetaApiRequestLogger::log(
            $operation,
            'GET',
            $url,
            $result['code'] ?? wp_remote_retrieve_response_code($response),
            $result['success'],
            is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response),
            $duration,
            !$result['success'] ? ['message' => $result['error'] ?? ''] : [],
            $query
        );

        return $result;
    }

    public function delete(string $operation, array $pathParams, array $query, string $token) {
        $url = add_query_arg($query, $this->buildUrl($operation, $pathParams));
        $start_time = microtime(true);

        $response = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 30,
        ]);

        $result = $this->parse($response);
        $duration = (int)((microtime(true) - $start_time) * 1000);

        MetaApiRequestLogger::log(
            $operation,
            'DELETE',
            $url,
            $result['code'] ?? wp_remote_retrieve_response_code($response),
            $result['success'],
            is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response),
            $duration,
            !$result['success'] ? ['message' => $result['error'] ?? ''] : [],
            $query
        );

        return $result;
    }

    public function uploadMedia(string $phoneNumberId, string $filePath, string $mimeType, string $accessToken) {
        $url = $this->baseUrl . '/' . $this->version . '/' . rawurlencode($phoneNumberId) . '/media';
        
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'Arquivo não encontrado para upload.'];
        }

        $ch = curl_init();
        $postFields = [
            'messaging_product' => 'whatsapp',
            'type' => $mimeType,
            'file' => curl_file_create($filePath, $mimeType, basename($filePath)),
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->normalizeAccessToken($accessToken),
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'error' => 'Erro cURL: ' . $error];
        }

        $body = json_decode($raw, true);
        $success = ($status < 400 && !isset($body['error']));
        $errorMessage = '';
        if (!$success) {
            $errorMessage = $body['error']['message'] ?? ($body['error'] ?? 'Erro desconhecido na Meta');
        }

        $result = array_merge([
            'success' => $success,
            'code'    => $status,
            'error'   => $errorMessage
        ], $body ?: []);

        MetaApiRequestLogger::log(
            'media.upload',
            'POST',
            $url,
            $status,
            $result['success'],
            $raw,
            0, // Duration could be tracked if needed
            !$result['success'] ? ['message' => $result['error'] ?? ''] : [],
            ['filename' => basename($filePath), 'mime' => $mimeType]
        );

        return $result;
    }

    private function normalizeAccessToken(string $token): string {
        $token = trim($token);
        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }
        return $token;
    }

    private function buildUrl(string $operation, array $pathParams): string {
        $path = MetaEndpointRegistry::resolve($operation, $pathParams);
        return sprintf('%s/%s%s', $this->baseUrl, $this->version, $path);
    }

    private function parse($response) {
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400 || isset($body['error'])) {
            return [
                'success' => false,
                'error' => $body['error']['message'] ?? 'Erro na Meta API',
                'code' => $body['error']['code'] ?? $code
            ];
        }

        return array_merge(['success' => true], $body ?: []);
    }
}

<?php

namespace WAS\Meta;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger de Requisições Meta API
 */
class MetaApiRequestLogger {

    /**
     * Registra uma chamada para a Meta Graph API com payload completo sanitizado.
     */
    public static function log(string $operation, string $method, string $path, int $status_code, bool $success, $response_body, int $duration_ms, array $error = [], $request_payload = null) {
        global $wpdb;

        $table_name = TableNameResolver::getMetaApiLogsTable();
        $tenant_id = TenantContext::getTenantId();

        // Sanitização de path (remover tokens se existirem na URL)
        $path = preg_replace('/access_token=[^&]+/', 'access_token=TOKEN_MASKED', $path);

        // Sanitização de payloads (remover access_token do body)
        $sanitized_request = self::sanitizePayload($request_payload);
        $sanitized_response = self::sanitizePayload($response_body);

        $wpdb->insert($table_name, [
            'tenant_id'       => $tenant_id,
            'operation'       => $operation,
            'method'          => $method,
            'path'            => $path,
            'request_payload' => is_array($sanitized_request) || is_object($sanitized_request) ? json_encode($sanitized_request) : $sanitized_request,
            'response_body'   => is_array($sanitized_response) || is_object($sanitized_response) ? json_encode($sanitized_response) : $sanitized_response,
            'status_code'     => $status_code,
            'success'         => $success ? 1 : 0,
            'error_code'      => $error['code'] ?? null,
            'error_subcode'   => $error['subcode'] ?? null,
            'error_message'   => $error['message'] ?? null,
            'duration_ms'     => $duration_ms,
            'created_at'      => current_time('mysql', true),
        ]);
    }

    /**
     * Remove tokens e dados sensíveis de payloads antes de salvar no banco
     */
    private static function sanitizePayload($payload) {
        if (empty($payload)) {
            return $payload;
        }

        $isArray = is_array($payload);
        $isObject = is_object($payload);
        
        if (is_string($payload)) {
            // Tenta decodificar caso seja JSON
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
                $isArray = true;
            } elseif (strpos($payload, 'access_token=') !== false) {
                return preg_replace('/access_token=[^&]+/', 'access_token=TOKEN_MASKED', $payload);
            }
        }

        if ($isArray || $isObject) {
            $data = self::sanitizeSensitiveData((array)$payload);
            return $isArray ? $data : (object)$data;
        }

        return $payload;
    }

    private static function sanitizeSensitiveData(array $data): array {
        foreach ($data as $key => $value) {
            if (in_array($key, ['access_token', 'verify_token'], true)) {
                $data[$key] = 'TOKEN_MASKED';
                continue;
            }

            if (is_array($value) || is_object($value)) {
                $data[$key] = self::sanitizeSensitiveData((array)$value);
                continue;
            }

            if (is_string($value) && strpos($value, 'access_token=') !== false) {
                $data[$key] = preg_replace('/access_token=[^&]+/', 'access_token=TOKEN_MASKED', $value);
            }
        }

        return $data;
    }
}

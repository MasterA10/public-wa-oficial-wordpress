<?php
namespace WAS\WhatsApp;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookSignatureValidator {
    /**
     * Valida a assinatura X-Hub-Signature-256 enviada pela Meta.
     */
    public static function is_valid($raw_body, $signature_header, $app_secret) {
        if (!$signature_header || !str_starts_with($signature_header, 'sha256=')) {
            return false;
        }

        $signature = str_replace('sha256=', '', $signature_header);
        $expected_signature = hash_hmac('sha256', $raw_body, $app_secret);

        return hash_equals($expected_signature, $signature);
    }
}

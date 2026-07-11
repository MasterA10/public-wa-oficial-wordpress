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
        if ( ! is_string( $signature_header ) || ! preg_match( '/^sha256=[a-f0-9]{64}$/i', $signature_header ) ) {
            return false;
        }

        $signature = substr( $signature_header, 7 );
        $expected_signature = hash_hmac('sha256', $raw_body, $app_secret);

        return hash_equals($expected_signature, $signature);
    }
}

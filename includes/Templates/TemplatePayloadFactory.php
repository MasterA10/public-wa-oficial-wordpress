<?php

namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fábrica de Payloads para Templates
 */
class TemplatePayloadFactory {
    /**
     * Converte dados internos para payload oficial da Meta
     */
    public static function createMetaPayload(array $data) {
        return [
            'name'       => $data['name'],
            'language'   => $data['language'],
            'category'   => $data['category'],
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $data['body_text']
                ]
            ]
        ];
    }
}

<?php
namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Construtor especializado para payloads de ENVIO de templates de autenticação (OTP).
 */
final class AuthenticationTemplateSendPayloadBuilder {

    /**
     * Constrói o payload para envio de um template de autenticação COPY_CODE.
     */
    public function build_copy_code_payload(string $to, string $template_name, string $language_code, string $code): array {
        $code = trim($code);

        if (empty($code)) {
            throw new \InvalidArgumentException('O código de autenticação não pode estar vazio.');
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $template_name,
                'language' => [
                    'code' => $language_code,
                ],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $code,
                            ],
                        ],
                    ],
                    [
                        'type'       => 'button',
                        'sub_type'   => 'url',
                        'index'      => '0',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $code,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

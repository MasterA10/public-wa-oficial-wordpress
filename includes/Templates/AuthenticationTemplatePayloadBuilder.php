<?php
namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Construtor de payloads específico para templates de AUTHENTICATION.
 * Segue as restrições da Meta para modelos de autenticação/OTP.
 */
final class AuthenticationTemplatePayloadBuilder {

    /**
     * Constrói o payload da Graph API a partir de um array amigável.
     *
     * @param array $friendly
     * @return array
     * @throws \RuntimeException
     */
    public function build(array $friendly): array {
        $auth = $friendly['authentication'] ?? [];
        $otpType = strtoupper($auth['type'] ?? 'COPY_CODE');

        if (!in_array($otpType, ['COPY_CODE', 'ONE_TAP', 'ZERO_TAP'], true)) {
            throw new \RuntimeException('Tipo de autenticação inválido.');
        }

        $components = [];

        // Componente BODY (Obrigatório, mas sem texto livre)
        $components[] = [
            'type' => 'BODY',
            'add_security_recommendation' => (bool) ($auth['add_security_recommendation'] ?? true),
        ];

        // Componente FOOTER (Opcional)
        if (!empty($auth['code_expiration_minutes'])) {
            $components[] = [
                'type' => 'FOOTER',
                'code_expiration_minutes' => (int) $auth['code_expiration_minutes'],
            ];
        }

        // Componente BUTTONS (Obrigatório e restrito a OTP)
        $button = [
            'type' => 'OTP',
            'otp_type' => $otpType,
            'text' => $auth['button_text'] ?? 'Copiar código',
        ];

        // Configurações extras para One-tap e Zero-tap
        if ($otpType === 'ONE_TAP' || $otpType === 'ZERO_TAP') {
            $button['autofill_text'] = $auth['autofill_text'] ?? 'Preencher automaticamente';
            $button['package_name'] = $auth['package_name'] ?? null;
            $button['signature_hash'] = $auth['signature_hash'] ?? null;

            if (empty($button['package_name']) || empty($button['signature_hash'])) {
                throw new \RuntimeException('One-tap/Zero-tap exigem package_name e signature_hash.');
            }
        }

        if ($otpType === 'ZERO_TAP') {
            $button['zero_tap_terms_accepted'] = (bool) ($auth['zero_tap_terms_accepted'] ?? false);

            if (!$button['zero_tap_terms_accepted']) {
                throw new \RuntimeException('Zero-tap exige aceite dos termos.');
            }
        }

        $components[] = [
            'type' => 'BUTTONS',
            'buttons' => [
                array_filter($button, fn($value) => $value !== null),
            ],
        ];

        return [
            'name' => $this->normalize_name($friendly['name'] ?? ''),
            'category' => 'AUTHENTICATION',
            'language' => $friendly['language'] ?? 'pt_BR',
            'components' => $components,
        ];
    }

    /**
     * Normaliza o nome do template conforme regras da Meta.
     */
    private function normalize_name(string $name): string {
        $name = strtolower(remove_accents($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $name = trim($name, '_');

        if ($name === '') {
            throw new \RuntimeException('Nome do template inválido.');
        }

        return $name;
    }
}

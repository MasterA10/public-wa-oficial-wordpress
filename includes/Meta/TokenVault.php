<?php

namespace WAS\Meta;

/**
 * TokenVault
 * 
 * Responsável exclusivo por criptografar e descriptografar segredos e tokens.
 * A chave de criptografia deve vir de constante segura (WAS_ENCRYPTION_KEY).
 */
class TokenVault {

    private const METHOD = 'AES-256-CBC';

    /**
     * Busca um token válido para o tenant/waba.
     */
    public function get_valid_token($tenant_id, $waba_internal_id = null) {
        global $wpdb;
        $table = \WAS\Core\TableNameResolver::get_table_name('meta_tokens');

        $encrypted = null;

        if ($waba_internal_id) {
            // Prefer the credential belonging to the exact WABA. A generic
            // tenant token must never win over a number-specific token.
            $encrypted = $wpdb->get_var($wpdb->prepare(
                "SELECT access_token_encrypted FROM $table
                 WHERE tenant_id = %d AND status = 'active' AND whatsapp_account_id = %d
                 ORDER BY id DESC LIMIT 1",
                $tenant_id,
                $waba_internal_id
            ));

            if (!$encrypted) {
                $encrypted = $wpdb->get_var($wpdb->prepare(
                    "SELECT access_token_encrypted FROM $table
                     WHERE tenant_id = %d AND status = 'active' AND whatsapp_account_id IS NULL
                     ORDER BY id DESC LIMIT 1",
                    $tenant_id
                ));
            }
        } else {
            $encrypted = $wpdb->get_var($wpdb->prepare(
                "SELECT access_token_encrypted FROM $table
                 WHERE tenant_id = %d AND status = 'active'
                 ORDER BY whatsapp_account_id DESC, id DESC LIMIT 1",
                $tenant_id
            ));
        }

        if (!$encrypted) {
            return null;
        }

        // Tokens nunca podem ser usados ou devolvidos em claro.
        if (!defined('WAS_ENCRYPTION_KEY')) {
            \WAS\Core\SystemLogger::logError("WAS_ENCRYPTION_KEY not defined. Token access blocked.", [
                'tenant_id' => $tenant_id,
                'context'   => 'TokenVault::get_valid_token'
            ]);
            return null;
        }

        try {
            return self::decrypt($encrypted);
        } catch (\Exception $e) {
            \WAS\Core\SystemLogger::logException($e, [
                'context'   => 'TokenVault::get_valid_token',
                'tenant_id' => $tenant_id,
            ]);
            return null;
        }
    }

    /**
     * Criptografa um valor.
     * 
     * @param string $value Valor puro.
     * @return string Valor criptografado em base64 com IV.
     * @throws \Exception Se a chave não estiver definida.
     */
    public static function encrypt(string $value): string {
        if (!defined('WAS_ENCRYPTION_KEY') || empty(WAS_ENCRYPTION_KEY)) {
            throw new \Exception('Chave de criptografia WAS_ENCRYPTION_KEY não definida.');
        }

        $iv_length = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt($value, self::METHOD, WAS_ENCRYPTION_KEY, 0, $iv);
        
        // Retorna IV + Valor criptografado em base64
        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografa um valor.
     * 
     * @param string $encrypted_value Valor criptografado em base64.
     * @return string Valor puro.
     * @throws \Exception Se a chave não estiver definida ou falha na descriptografia.
     */
    public static function decrypt(string $encrypted_value): string {
        if (!defined('WAS_ENCRYPTION_KEY') || empty(WAS_ENCRYPTION_KEY)) {
            throw new \Exception('Chave de criptografia WAS_ENCRYPTION_KEY não definida.');
        }

        $decoded = base64_decode($encrypted_value, true);
        if ($decoded === false) {
            return '';
        }

        $iv_length = openssl_cipher_iv_length(self::METHOD);
        if (strlen($decoded) <= $iv_length) {
            throw new \Exception('Valor criptografado invalido ou incompleto.');
        }

        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);

        $decrypted = openssl_decrypt($encrypted, self::METHOD, WAS_ENCRYPTION_KEY, 0, $iv);
        
        if ($decrypted === false) {
            throw new \Exception('Falha ao descriptografar valor. A chave de criptografia pode ter mudado.');
        }

        return $decrypted;
    }

    /**
     * Mascara um token para exibição segura em logs ou telas.
     * 
     * @param string $token
     * @param int $visible_length Quantidade de caracteres visíveis no início e fim.
     * @return string Token mascarado.
     */
    public static function mask(string $token, int $visible_length = 4): string {
        if (strlen($token) <= ($visible_length * 2)) {
            return '********';
        }

        return substr($token, 0, $visible_length) . '...' . substr($token, -$visible_length);
    }
}

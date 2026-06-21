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

        $query = "SELECT access_token_encrypted FROM $table WHERE tenant_id = %d AND status = 'active'";
        $params = [$tenant_id];

        if ($waba_internal_id) {
            $query .= " AND (whatsapp_account_id = %d OR whatsapp_account_id IS NULL)";
            $params[] = $waba_internal_id;
        }

        $query .= " ORDER BY whatsapp_account_id DESC LIMIT 1";

        $prepared = $wpdb->prepare($query, ...$params);
        $encrypted = $wpdb->get_var($prepared);

        if (!$encrypted) {
            return null;
        }

        // Se a chave não estiver definida, retorna o valor "puro" (fallback para modo dev/sem criptografia configurada)
        if (!defined('WAS_ENCRYPTION_KEY')) {
            \WAS\Core\SystemLogger::logError("WAS_ENCRYPTION_KEY not defined. Returning raw token.", [
                'tenant_id' => $tenant_id,
                'context'   => 'TokenVault::get_valid_token'
            ]);
            return $encrypted;
        }

        try {
            return self::decrypt($encrypted);
        } catch (\Exception $e) {
            \WAS\Core\SystemLogger::logException($e, [
                'context'   => 'TokenVault::get_valid_token',
                'tenant_id' => $tenant_id,
            ]);
            return $encrypted; // Fallback
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

        $decoded = base64_decode($encrypted_value);
        if ($decoded === false) {
            return '';
        }

        $iv_length = openssl_cipher_iv_length(self::METHOD);
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

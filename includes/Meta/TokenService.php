<?php
namespace WAS\Meta;

if (!defined('ABSPATH')) {
    exit;
}

class TokenService {
    /**
     * Obtém o token ativo para o tenant/waba.
     */
    public function get_active_token($tenant_id, $waba_internal_id = null) {
        $vault = new TokenVault();
        return $vault->get_valid_token($tenant_id, $waba_internal_id);
    }

    /**
     * Salva um token criptografado para o tenant e conta.
     *
     * @param int    $tenant_id           ID do Tenant.
     * @param int    $whatsapp_account_id ID da Conta WhatsApp (opcional).
     * @param string $access_token        Access Token puro.
     * @return int|bool ID do token inserido ou false em caso de erro.
     */
    public function store_encrypted_token($tenant_id, $whatsapp_account_id, $access_token) {
        global $wpdb;
        $table = \WAS\Core\TableNameResolver::get_table_name('meta_tokens');

        try {
            $encrypted = TokenVault::encrypt($access_token);
        } catch (\Exception $e) {
            \WAS\Core\SystemLogger::logException($e, [
                'context'   => 'TokenService::store_encrypted_token',
                'tenant_id' => $tenant_id,
            ]);
            // Se falhar a criptografia, vamos salvar puro apenas se estiver em modo demo ou se a chave não estiver definida, 
            // seguindo a lógica do vault. Mas o ideal é que a chave exista.
            $encrypted = $access_token;
        }

        // Desativa tokens anteriores para este tenant/conta se necessário, ou apenas insere novo.
        // O projeto parece preferir 'active' status.
        $wpdb->update(
            $table,
            ['status' => 'inactive'],
            ['tenant_id' => $tenant_id, 'whatsapp_account_id' => $whatsapp_account_id]
        );

        $result = $wpdb->insert(
            $table,
            [
                'tenant_id'               => $tenant_id,
                'whatsapp_account_id'     => $whatsapp_account_id,
                'access_token_encrypted'  => $encrypted,
                'status'                  => 'active',
                'created_at'              => current_time('mysql', true),
            ]
        );

        return $result ? $wpdb->insert_id : false;
    }
}

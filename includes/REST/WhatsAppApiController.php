<?php

namespace WAS\REST;

use WAS\WhatsApp\WhatsAppAccountRepository;
use WAS\WhatsApp\PhoneNumberRepository;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller REST para WhatsApp (Accounts, Numbers)
 */
class WhatsAppApiController {

    private $accountRepository;
    private $numberRepository;

    public function __construct() {
        $this->accountRepository = new WhatsAppAccountRepository();
        $this->numberRepository = new PhoneNumberRepository();
    }

    public function register_routes() {
        register_rest_route( 'was/v1', '/whatsapp/check-connection', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'check_connection' ],
            'permission_callback' => [ $this, 'permissions_check' ],
        ] );

        register_rest_route( 'was/v1', '/whatsapp/accounts', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_accounts' ],
            'permission_callback' => [ $this, 'permissions_check' ],
        ] );

        register_rest_route( 'was/v1', '/whatsapp/connections/(?P<connection_id>\d+)/messages', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'send_connection_message' ],
            'permission_callback' => [ $this, 'permissions_check' ],
        ] );
    }

    /**
     * Realiza verificação completa de conexão.
     */
    public function check_connection(WP_REST_Request $request) {
        $tenant_id = \WAS\Auth\TenantContext::getTenantId();
        $service = new \WAS\WhatsApp\IntegrationConnectionCheckService();
        $results = $service->checkConnection($tenant_id);
        
        return new WP_REST_Response([
            'success' => true,
            'results' => $results
        ], 200);
    }

    /**
     * Lista contas WABA do tenant atual.
     */
    public function get_accounts(WP_REST_Request $request) {
        $accounts = $this->accountRepository->getByTenant();
        
        // Anexar o número principal para cada conta
        foreach ($accounts as &$account) {
            $primary_phone = $this->numberRepository->getPrimaryForAccount($account->id);
            if (!$primary_phone) {
                $primary_phone = $this->numberRepository->getDefaultByTenant($account->tenant_id);
            }
            $account->phone_number_id = $primary_phone ? $primary_phone->phone_number_id : null;
        }
        
        return new WP_REST_Response($accounts, 200);
    }


    /**
     * Lista números de telefone do tenant atual.
     */
    public function get_phone_numbers(WP_REST_Request $request) {
        // Implementar se necessário separadamente
        return new WP_REST_Response([], 200);
    }

    public function send_connection_message( WP_REST_Request $request ) {
        global $wpdb;
        $tenant_id = (int) \WAS\Auth\TenantContext::getTenantId();
        $connection_id = (int) $request->get_param( 'connection_id' );
        $table = \WAS\Core\TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
        $phone = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d AND tenant_id = %d AND status = 'active' LIMIT 1", $connection_id, $tenant_id ) );
        if ( ! $phone ) {
            return new \WP_Error( 'connection_not_found', 'Conexao WhatsApp nao encontrada para este tenant.', [ 'status' => 404 ] );
        }

        $params = $request->get_json_params();
        if ( ! is_array( $params ) || ! $params ) {
            $params = $request->get_params();
        }
        $params['phone_number_id'] = $connection_id;
        $result = ( new \WAS\Router\WhatsAppService() )->send_message( $params, (object) [ 'type' => 'wordpress_user', 'user_id' => get_current_user_id() ] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return new WP_REST_Response( $result, ( ! empty( $result['success'] ) ? 200 : 502 ) );
    }

    /**
     * Verifica permissão.
     */
    public function permissions_check() {
        return Routes::check_auth();
    }
}

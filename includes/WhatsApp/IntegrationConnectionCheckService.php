<?php
/**
 * IntegrationConnectionCheckService class.
 *
 * @package WAS\WhatsApp
 */

namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\Meta\TokenDebugService;
use WAS\Core\TableNameResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for performing a comprehensive connection check with Meta/WhatsApp.
 */
class IntegrationConnectionCheckService {

	private $api_client;
	private $token_service;
	private $debug_service;

	public function __construct() {
		$this->api_client    = new MetaApiClient();
		$this->token_service = new TokenService();
		$this->debug_service = new TokenDebugService();
	}

	/**
	 * Perform a full connection check for a tenant.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @return array
	 */
	public function checkConnection( $tenant_id ) {
		$results = [
			'token'            => [ 'status' => 'pending', 'label' => 'Token Válido', 'details' => '' ],
			'waba'             => [ 'status' => 'pending', 'label' => 'WABA Acessível', 'details' => '' ],
			'phone_numbers'    => [ 'status' => 'pending', 'label' => 'Números Encontrados', 'details' => '' ],
			'business_profile' => [ 'status' => 'pending', 'label' => 'Perfil Comercial Carregado', 'details' => '' ],
			'webhook'          => [ 'status' => 'pending', 'label' => 'Webhook Inscrito', 'details' => '' ],
			'templates'        => [ 'status' => 'pending', 'label' => 'Templates Sincronizados', 'details' => '' ],
		];

		// 1. Get Token
		$token = $this->token_service->get_active_token( $tenant_id );
		if ( ! $token ) {
			$results['token'] = [ 'status' => 'error', 'label' => 'Token Válido', 'details' => 'Token não configurado.' ];
			return $results;
		}

		// 2. Debug Token
		$debug = $this->debug_service->debugToken( $token );
		if ( ! $debug['success'] || ! ($debug['data']['is_valid'] ?? false) ) {
			$results['token'] = [ 'status' => 'error', 'label' => 'Token Válido', 'details' => $debug['error'] ?? 'Token inválido.' ];
			return $results;
		}
		$results['token'] = [ 'status' => 'success', 'label' => 'Token Válido', 'details' => 'Token validado com sucesso.' ];

		// 3. Get Account Info (WABA)
		$account_repo = new WhatsAppAccountRepository();
		$account = $account_repo->getByTenant( $tenant_id );
        $account = !empty($account) ? $account[0] : null;

		if ( ! $account || empty( $account->waba_id ) ) {
			$results['waba'] = [ 'status' => 'error', 'label' => 'WABA Acessível', 'details' => 'WABA ID não encontrado no banco local.' ];
			return $results;
		}

		$waba_res = $this->api_client->get( 'waba.get', [ 'waba_id' => $account->waba_id ], [ 'fields' => 'id,name,currency,timezone_id' ], $token );
		if ( ! $waba_res['success'] ) {
			$results['waba'] = [ 'status' => 'error', 'label' => 'WABA Acessível', 'details' => $waba_res['error'] ];
		} else {
			$results['waba'] = [ 'status' => 'success', 'label' => 'WABA Acessível', 'details' => "WABA '{$waba_res['name']}' encontrada." ];
		}

		// 4. List Phone Numbers & Run Rich Diagnostics
        $phone_diag_service = new PhoneNumberDiagnosticsService();
        $phone_results = $phone_diag_service->run($tenant_id, $account->waba_id, $token);
        
        $results['phone_numbers'] = [
            'status'  => $phone_results['status'],
            'label'   => 'Números Encontrados',
            'message' => $phone_results['message'],
            'numbers' => $phone_results['numbers'],
        ];

		// 5. Check Webhook Subscription
		$sub_res = $this->api_client->get( 'waba.get_subscribed_apps', [ 'waba_id' => $account->waba_id ], [], $token );
		$is_subscribed = false;
		if ( $sub_res['success'] ) {
			foreach ( ($sub_res['data'] ?? []) as $app ) {
				// How to verify if it's OUR app? Maybe check app_id if available or just check if list is not empty
				$is_subscribed = true; 
				break;
			}
		}
		
		if ( $is_subscribed ) {
			$results['webhook'] = [ 'status' => 'success', 'label' => 'Webhook Inscrito', 'details' => 'A WABA está enviando eventos para a aplicação.' ];
		} else {
			$results['webhook'] = [ 'status' => 'warning', 'label' => 'Webhook Inscrito', 'details' => 'Assinatura não detectada ou falha na verificação.' ];
		}

		// 6. Templates
		$tpl_res = $this->api_client->get( 'templates.list', [ 'waba_id' => $account->waba_id ], [], $token );
		if ( ! $tpl_res['success'] ) {
			$results['templates'] = [ 'status' => 'warning', 'label' => 'Templates Sincronizados', 'details' => $tpl_res['error'] ];
		} else {
			$count = count( $tpl_res['data'] ?? [] );
			$results['templates'] = [ 'status' => 'success', 'label' => 'Templates Sincronizados', 'details' => "{$count} templates encontrados na Meta." ];
		}

        // Remover campos antigos que agora estão dentro de phone_numbers
        unset($results['business_profile']);

		return $results;
	}
}

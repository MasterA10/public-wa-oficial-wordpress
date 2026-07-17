<?php
/**
 * AdminMasterApiController class.
 *
 * @package WAS\REST
 */

namespace WAS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WAS\Core\TableNameResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for Master Admin REST endpoints.
 */
class AdminMasterApiController {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( 'was/v1', '/admin/overview', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_overview' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/tenants', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_tenants' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_tenant' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
		] );

        register_rest_route( 'was/v1', '/admin/tenants/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update_tenant_status' ],
            'permission_callback' => [ $this, 'permissions_check' ],
        ] );

		register_rest_route( 'was/v1', '/admin/wabas', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_wabas' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

        register_rest_route( 'was/v1', '/admin/wabas/(?P<id>\d+)/(?P<action>[a-z-]+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_waba_action' ],
            'permission_callback' => [ $this, 'permissions_check' ],
        ] );

		register_rest_route( 'was/v1', '/admin/phone-numbers', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_phones' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/phone-numbers/onboarding/start', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'start_phone_onboarding' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/phone-numbers/onboarding/attempts/(?P<attempt_id>[A-Za-z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'phone_onboarding_status' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/phone-numbers/(?P<id>\d+)/test-message', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'test_phone_message' ],
            'permission_callback' => [ $this, 'permissions_check' ],
        ] );

		register_rest_route( 'was/v1', '/admin/onboardings', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_onboardings' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/phone-numbers/(?P<id>\d+)/details', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_phone_details' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/phone-numbers/(?P<id>\d+)/routes', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_phone_route' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/phone-numbers/(?P<id>\d+)/templates/sync', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'sync_phone_templates' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/routes/(?P<id>\d+)', [
			[
				'methods'             => [ 'PATCH', 'PUT' ],
				'callback'            => [ $this, 'update_route' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_route' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( 'was/v1', '/admin/templates', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_templates' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/webhooks', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_webhooks' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/tokens', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_tokens' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/app-review/checklist', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_review_checklist' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_review_item' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
		] );

		register_rest_route( 'was/v1', '/admin/audit-logs', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_audit_logs' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		] );

		register_rest_route( 'was/v1', '/admin/tokens/(?P<id>\d+)/test', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'test_token' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/templates/(?P<id>\d+)/payload', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_template_payload' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

        register_rest_route( 'was/v1', '/admin/meta-apps', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_meta_apps' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_meta_app' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        ] );

        register_rest_route( 'was/v1', '/admin/meta-apps/(?P<id>\d+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_meta_app' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'test_meta_app' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        ] );
	}

	/**
	 * Get overview stats for the master dashboard.
	 */
	public function get_overview( $request ) {
		global $wpdb;

		$table_tenants     = TableNameResolver::get_table_name( 'tenants' );
		$table_wabas       = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$table_phones      = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$table_templates   = TableNameResolver::get_table_name( 'message_templates' );
		$table_webhooks    = TableNameResolver::get_table_name( 'webhook_events' );
		$table_onboarding  = TableNameResolver::get_table_name( 'onboarding_sessions' );

		$tenants_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_tenants WHERE status = 'active'" );
		$wabas_count   = $wpdb->get_var( "SELECT COUNT(*) FROM $table_wabas WHERE status = 'connected'" );
		$phones_count  = $wpdb->get_var( "SELECT COUNT(*) FROM $table_phones WHERE status = 'active'" );
		$templates_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_templates WHERE status = 'APPROVED'" );
		$webhooks_today = $wpdb->get_var( "SELECT COUNT(*) FROM $table_webhooks WHERE DATE(received_at) = CURDATE()" );
		$onboarding_fails = $wpdb->get_var( "SELECT COUNT(*) FROM $table_onboarding WHERE status = 'failed' OR status = 'cancelled'" );

		return new WP_REST_Response( [
			'tenants'             => (int) $tenants_count,
			'wabas'               => (int) $wabas_count,
			'phones'              => (int) $phones_count,
			'templates'           => (int) $templates_count,
			'webhooks_today'      => (int) $webhooks_today,
			'onboarding_failures' => (int) $onboarding_fails,
		], 200 );
	}

    /**
     * Get list of tenants.
     */
    public function get_tenants( $request ) {
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'tenants' );
        $tenants = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
        return new WP_REST_Response( $tenants, 200 );
    }

    /**
     * Get list of WABAs.
     */
    public function get_wabas( $request ) {
        global $wpdb;
        $table_wabas   = TableNameResolver::get_table_name( 'whatsapp_accounts' );
        $table_tenants = TableNameResolver::get_table_name( 'tenants' );

        $wabas = $wpdb->get_results( "
            SELECT w.*, t.name as tenant_name 
            FROM $table_wabas w
            LEFT JOIN $table_tenants t ON w.tenant_id = t.id
            ORDER BY w.created_at DESC
        " );

        return new WP_REST_Response( $wabas, 200 );
    }

    /**
     * Get list of Phone Numbers.
     */
	public function get_phones( $request ) {
        global $wpdb;
        $table_phones  = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
        $table_wabas   = TableNameResolver::get_table_name( 'whatsapp_accounts' );
        $table_tenants = TableNameResolver::get_table_name( 'tenants' );

        $phones = $wpdb->get_results( "
            SELECT p.*, a.waba_id, a.name as waba_name, a.status as waba_status, t.name as tenant_name
            FROM $table_phones p
            LEFT JOIN $table_wabas a ON a.id = p.whatsapp_account_id
            LEFT JOIN $table_tenants t ON p.tenant_id = t.id
            ORDER BY p.created_at DESC
        " );

		return new WP_REST_Response( $phones, 200 );
	}

	public function start_phone_onboarding( $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : [];
		$params['tenant_id'] = (int) ( $params['tenant_id'] ?? 0 );

		$result = ( new \WAS\Router\OnboardingService() )->start_embedded_signup( $params );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function phone_onboarding_status( $request ) {
		$tenant_id = (int) $request->get_param( 'tenant_id' );
		$attempt_id = $request->get_param( 'attempt_id' );
		if ( ! $tenant_id ) {
			return new \WP_Error( 'tenant_required', 'Tenant obrigatorio.', [ 'status' => 400 ] );
		}

		$service = new \WAS\Router\OnboardingService();
		$result = $request->get_param( 'refresh' )
			? $service->refresh_attempt_status( $tenant_id, $attempt_id )
			: $service->get_attempt_status( $tenant_id, $attempt_id );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function get_phone_details( $request ) {
		$phone = $this->find_master_phone( (int) $request->get_param( 'id' ) );
		if ( ! $phone ) {
			return new \WP_Error( 'phone_number_not_found', 'Numero nao encontrado.', [ 'status' => 404 ] );
		}

		$routes = ( new \WAS\Router\RouteRepository() )->list( [
			'tenant_id'       => (int) $phone->tenant_id,
			'phone_number_id' => (int) $phone->id,
		] );
		$templates = ( new \WAS\Router\AdminRouterService() )->list_phone_number_templates( (int) $phone->id );
		if ( is_wp_error( $templates ) ) {
			$templates = [];
		}

		return new WP_REST_Response( [
			'phone'     => $phone,
			'routes'    => array_map( [ $this, 'public_route' ], $routes ),
			'templates' => $templates,
		], 200 );
	}

	public function create_phone_route( $request ) {
		$phone = $this->find_master_phone( (int) $request->get_param( 'id' ) );
		if ( ! $phone ) {
			return new \WP_Error( 'phone_number_not_found', 'Numero nao encontrado.', [ 'status' => 404 ] );
		}
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : [];
		$params['tenant_id'] = (int) $phone->tenant_id;
		$params['phone_number_id'] = (int) $phone->id;
		$route = ( new \WAS\Router\AdminRouterService() )->create_route( $params );
		return is_wp_error( $route ) ? $route : new WP_REST_Response( $this->public_route( $route ), 201 );
	}

	public function sync_phone_templates( $request ) {
		$phone = $this->find_master_phone( (int) $request->get_param( 'id' ) );
		if ( ! $phone ) {
			return new \WP_Error( 'phone_number_not_found', 'Numero nao encontrado.', [ 'status' => 404 ] );
		}
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : [];
		$result = ( new \WAS\Router\AdminRouterService() )->sync_phone_number_templates( (int) $phone->id, $params );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result, 200 );
	}

	public function update_route( $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : [];
		$route = ( new \WAS\Router\RouteRepository() )->update( (int) $request->get_param( 'id' ), $params );
		return is_wp_error( $route ) ? $route : new WP_REST_Response( $this->public_route( $route ), 200 );
	}

	public function delete_route( $request ) {
		$route = ( new \WAS\Router\RouteRepository() )->archive( (int) $request->get_param( 'id' ) );
		return is_wp_error( $route ) ? $route : new WP_REST_Response( $this->public_route( $route ), 200 );
	}

	private function find_master_phone( $id ) {
		global $wpdb;
		$phones = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$accounts = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$tenants = TableNameResolver::get_table_name( 'tenants' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT p.*, a.waba_id, a.name AS waba_name, a.status AS waba_status, t.name AS tenant_name FROM $phones p LEFT JOIN $accounts a ON a.id = p.whatsapp_account_id LEFT JOIN $tenants t ON t.id = p.tenant_id WHERE p.id = %d LIMIT 1",
			(int) $id
		) );
	}

	private function public_route( $route ) {
		return [
			'id'              => (int) $route->id,
			'name'            => $route->name,
			'target_url'      => $route->target_url,
			'is_active'       => (bool) $route->is_active,
			'status'          => $route->status,
			'event_filters'   => json_decode( $route->event_filters_json ?: '{}', true ),
			'timeout_ms'      => (int) $route->timeout_ms,
			'max_retries'     => (int) $route->max_retries,
			'priority'        => (int) $route->priority,
		];
	}

    /**
     * Get list of Onboarding Sessions.
     */
    public function get_onboardings( $request ) {
        global $wpdb;
        $table_onboarding = TableNameResolver::get_table_name( 'onboarding_sessions' );
        $table_tenants    = TableNameResolver::get_table_name( 'tenants' );
        $table_users      = $wpdb->users;

        $sessions = $wpdb->get_results( "
            SELECT s.*, t.name as tenant_name, u.user_login 
            FROM $table_onboarding s
            LEFT JOIN $table_tenants t ON s.tenant_id = t.id
            LEFT JOIN $table_users u ON s.user_id = u.ID
            ORDER BY s.created_at DESC
        " );

        return new WP_REST_Response( $sessions, 200 );
    }

    /**
     * Get list of Templates.
     */
    public function get_templates( $request ) {
        global $wpdb;
        $table_templates = TableNameResolver::get_table_name( 'message_templates' );
        $table_tenants   = TableNameResolver::get_table_name( 'tenants' );

        $templates = $wpdb->get_results( "
            SELECT m.*, t.name as tenant_name 
            FROM $table_templates m
            LEFT JOIN $table_tenants t ON m.tenant_id = t.id
            ORDER BY m.created_at DESC
        " );

        return new WP_REST_Response( $templates, 200 );
    }

    /**
     * Get list of Webhooks.
     */
    public function get_webhooks( $request ) {
        global $wpdb;
        $table_webhooks = TableNameResolver::get_table_name( 'webhook_events' );
        $table_tenants  = TableNameResolver::get_table_name( 'tenants' );

        $events = $wpdb->get_results( "
            SELECT w.*, t.name as tenant_name 
            FROM $table_webhooks w
            LEFT JOIN $table_tenants t ON w.tenant_id = t.id
            ORDER BY w.received_at DESC LIMIT 100
        " );

        return new WP_REST_Response( $events, 200 );
    }

    /**
     * Get list of Tokens.
     */
    public function get_tokens( $request ) {
        global $wpdb;
        $table_tokens  = TableNameResolver::get_table_name( 'meta_tokens' );
        $table_tenants = TableNameResolver::get_table_name( 'tenants' );

        $tokens = $wpdb->get_results( "
            SELECT k.*, t.name as tenant_name 
            FROM $table_tokens k
            LEFT JOIN $table_tenants t ON k.tenant_id = t.id
            ORDER BY k.created_at DESC
        " );

        // Sanitize for security
        foreach($tokens as $token) {
            unset($token->access_token_encrypted);
            $token->prefix = $token->token_prefix ?: '...';
            $token->length = $token->token_length ?: 0;
        }

        return new WP_REST_Response( $tokens, 200 );
    }

    /**
     * Get App Review Checklist.
     */
    public function get_review_checklist( $request ) {
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'app_review_checklist' );
        $items = $wpdb->get_results( "SELECT * FROM $table" );

        if ( empty($items) ) {
            // Default items based on the spec
            return new WP_REST_Response([
                ['item_key' => 'business_portfolio_created', 'label' => 'Portfolio de Negócios Criado', 'status' => 'pending'],
                ['item_key' => 'meta_app_created', 'label' => 'Meta App Criado', 'status' => 'pending'],
                ['item_key' => 'embedded_signup_configured', 'label' => 'Embedded Signup Configurado', 'status' => 'pending'],
                ['item_key' => 'privacy_policy_url_added', 'label' => 'URL de Privacidade Adicionada', 'status' => 'pending'],
                ['item_key' => 'template_creation_video_recorded', 'label' => 'Vídeo: Criação de Template', 'status' => 'pending'],
                ['item_key' => 'message_sending_video_recorded', 'label' => 'Vídeo: Envio de Mensagem', 'status' => 'pending'],
            ], 200);
        }

        return new WP_REST_Response( $items, 200 );
    }

    /**
     * Get list of Meta Apps.
     */
    public function get_meta_apps( $request ) {
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'meta_apps' );
        $apps = $wpdb->get_results( "SELECT * FROM $table" );
        
        // Sanitize for security
        foreach($apps as $app) {
            unset($app->app_secret);
            if (!empty($app->app_secret_encrypted)) {
                $app->app_secret_masked = '...'; // Mocked mask for now
            }
        }

        return new WP_REST_Response( $apps, 200 );
    }

    /**
     * Get Master Audit Logs.
     */
    public function get_audit_logs( $request ) {
        global $wpdb;
        $table_audit   = TableNameResolver::get_table_name( 'audit_logs' );
        $table_tenants = TableNameResolver::get_table_name( 'tenants' );
        $table_users   = $wpdb->users;

        $logs = $wpdb->get_results( "
            SELECT l.*, u.user_login, t.name as tenant_name 
            FROM $table_audit l 
            LEFT JOIN $table_users u ON l.user_id = u.ID
            LEFT JOIN $table_tenants t ON l.tenant_id = t.id
            ORDER BY l.created_at DESC LIMIT 200
        " );

        return new WP_REST_Response( $logs, 200 );
    }

    /**
     * Get Global Settings.
     */
    public function get_settings( $request ) {
        // Return global settings from wp_options or was_settings where tenant_id = 0
		return new WP_REST_Response([
			'master_graph_version' => get_option('was_master_graph_version', 'v25.0'),
			'master_msg_rate_limit' => get_option('was_master_msg_rate_limit', 60),
			'master_log_retention' => get_option('was_master_log_retention', 90),
			'master_polling_interval' => get_option('was_master_polling_interval', 3000),
			'external_send_webhook_secret_configured' => (bool) \WAS\Router\RouterSettings::get_external_send_webhook_secret(),
		], 200);
    }

    /**
     * Save Global Settings.
     */
    public function save_settings( $request ) {
        $params = $request->get_json_params();
        
        if (isset($params['master_graph_version'])) {
            update_option('was_master_graph_version', sanitize_text_field($params['master_graph_version']));
        }
        if (isset($params['master_msg_rate_limit'])) {
            update_option('was_master_msg_rate_limit', intval($params['master_msg_rate_limit']));
        }
        if (isset($params['master_log_retention'])) {
            update_option('was_master_log_retention', intval($params['master_log_retention']));
        }
		if (isset($params['master_polling_interval'])) {
			update_option('was_master_polling_interval', intval($params['master_polling_interval']));
		}
		if (isset($params['external_send_webhook_secret'])) {
			update_option('was_external_send_webhook_secret', sanitize_text_field($params['external_send_webhook_secret']));
		}

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Save/Create Meta App.
     */
    public function save_meta_app( $request ) {
        $params = $request->get_json_params();
        $repo = new \WAS\Meta\MetaAppRepository();
        
        // Use existing repository logic if possible or implement direct DB
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'meta_apps' );

        $data = [
            'name'           => sanitize_text_field($params['name'] ?? 'Meta App'),
            'app_id'         => sanitize_text_field($params['app_id']),
            'graph_version'  => sanitize_text_field($params['graph_version'] ?? 'v25.0'),
            'config_id'      => sanitize_text_field($params['config_id'] ?? ''),
            'environment'    => sanitize_text_field($params['environment'] ?? 'production'),
            'status'         => sanitize_text_field($params['status'] ?? 'active'),
            'updated_at'     => current_time('mysql', true),
        ];

        if (!empty($params['app_secret'])) {
            $data['app_secret'] = \WAS\Meta\TokenVault::encrypt($params['app_secret']);
        }

        if (!empty($params['id'])) {
            $wpdb->update($table, $data, ['id' => intval($params['id'])]);
            $id = intval($params['id']);
        } else {
            $data['created_at'] = current_time('mysql', true);
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }

        return new WP_REST_Response(['success' => true, 'id' => $id], 200);
    }

    /**
     * Delete Meta App.
     */
    public function delete_meta_app( $request ) {
        $id = $request->get_param('id');
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'meta_apps' );
        $wpdb->delete($table, ['id' => $id]);
        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Test Meta App credentials.
     */
    public function test_meta_app( $request ) {
        // Placeholder for real Meta API validation
        return new WP_REST_Response(['success' => true, 'message' => 'Credenciais válidas (Simulado)'], 200);
    }

    /**
     * Save/Create Tenant.
     */
    public function save_tenant( $request ) {
        $params = $request->get_json_params();
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'tenants' );

        $data = [
            'name'   => sanitize_text_field($params['name']),
            'slug'   => sanitize_title($params['slug']),
            'plan'   => sanitize_text_field($params['plan'] ?? 'free'),
            'status' => sanitize_text_field($params['status'] ?? 'active'),
            'updated_at' => current_time('mysql', true),
        ];

        if (!empty($params['id'])) {
            $wpdb->update($table, $data, ['id' => intval($params['id'])]);
            $id = intval($params['id']);
        } else {
            $data['created_at'] = current_time('mysql', true);
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
        }

        return new WP_REST_Response(['success' => true, 'id' => $id], 200);
    }

    /**
     * Update Tenant status.
     */
    public function update_tenant_status( $request ) {
        $id = $request->get_param('id');
        $params = $request->get_json_params();
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'tenants' );
        $wpdb->update($table, ['status' => sanitize_text_field($params['status'])], ['id' => $id]);
        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Handle WABA actions.
     */
    public function handle_waba_action( $request ) {
        $id = $request->get_param('id');
        $action = $request->get_param('action');
        
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'whatsapp_accounts' );
        $waba = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$waba) {
            return new WP_REST_Response(['success' => false, 'message' => 'WABA não encontrada.'], 404);
        }

        if ($action === 'sync-templates') {
            $sync_service = new \WAS\Templates\TemplateSyncService();
            $result = $sync_service->syncWaba((int)$waba->tenant_id, $waba->waba_id);
            if ($result['success']) {
                $summary = $result['summary'];
                $msg = "Sincronização concluída.\nTemplates Criados: {$summary['created_local']}\nAtualizados: {$summary['updated_local']}";
                return new WP_REST_Response(['success' => true, 'message' => $msg], 200);
            }
            return new WP_REST_Response(['success' => false, 'message' => 'Erro: ' . ($result['error'] ?? 'Unknown')], 500);
        }

        if ($action === 'subscribe-webhooks') {
            $token_service = new \WAS\Meta\TokenService();
            $token = $token_service->get_active_token($waba->tenant_id);
            if (!$token) return new WP_REST_Response(['success' => false, 'message' => 'Token não encontrado.'], 400);

            $sub_service = new \WAS\WhatsApp\WebhookSubscriptionService();
            $res = $sub_service->subscribeWaba($waba->waba_id, $token);

            if ($res['success']) {
                $wpdb->update($table, ['webhook_subscription_status' => 'subscribed'], ['id' => $id]);
                return new WP_REST_Response(['success' => true, 'message' => 'Inscrito com sucesso!'], 200);
            }
            return new WP_REST_Response(['success' => false, 'message' => 'Erro Meta: ' . ($res['error'] ?? 'Erro desconhecido')], 500);
        }
        
        return new WP_REST_Response(['success' => false, 'message' => "Ação '$action' não suportada."], 400);
    }

    /**
     * Test Phone message.
     */
    public function test_phone_message( $request ) {
        $id = $request->get_param('id');
        $params = $request->get_json_params();
        $to = sanitize_text_field($params['to'] ?? '');

        if (empty($to)) return new WP_REST_Response(['success' => false, 'message' => 'Número de destino é obrigatório.'], 400);

        global $wpdb;
        $table = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
        $phone = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$phone) return new WP_REST_Response(['success' => false, 'message' => 'Número não encontrado.'], 404);

        $token_service = new \WAS\Meta\TokenService();
        $token = $token_service->get_active_token($phone->tenant_id);
        if (!$token) return new WP_REST_Response(['success' => false, 'message' => 'Token não encontrado.'], 400);

        $api = new \WAS\Meta\MetaApiClient();
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => 'Mensagem de teste do Painel Master Admin.']
        ];

        $res = $api->postJson('messages.send', ['phone_number_id' => $phone->phone_number_id], $payload, $token);

        if ($res['success']) {
            return new WP_REST_Response(['success' => true, 'message' => 'Mensagem enviada com sucesso! ID: ' . ($res['messages'][0]['id'] ?? '')], 200);
        }
        return new WP_REST_Response(['success' => false, 'message' => 'Erro Meta: ' . ($res['error'] ?? 'Desconhecido')], 500);
    }

    /**
     * Get Template Payload.
     */
    public function get_template_payload( $request ) {
        $id = $request->get_param('id');
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'message_templates' );
        $tpl = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$tpl) return new WP_REST_Response(['success' => false, 'message' => 'Template não encontrado.'], 404);

        return new WP_REST_Response([
            'success' => true,
            'template_id' => $tpl->id,
            'name' => $tpl->name,
            'friendly_payload' => json_decode($tpl->friendly_payload ?: '{}'),
            'meta_payload' => json_decode($tpl->meta_payload ?: '{}'),
            'components_json' => json_decode($tpl->components_json ?: '[]'),
        ], 200);
    }

    /**
     * Test Token.
     */
    public function test_token( $request ) {
        $id = $request->get_param('id');
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'meta_tokens' );
        $token_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$token_row) return new WP_REST_Response(['success' => false, 'message' => 'Token não encontrado.'], 404);

        $vault = new \WAS\Meta\TokenVault();
        try {
            $raw_token = $vault->decrypt($token_row->access_token_encrypted);
        } catch (\Exception $e) {
            return new WP_REST_Response(['success' => false, 'message' => 'Falha ao descriptografar: ' . $e->getMessage()], 500);
        }

        $debug_service = new \WAS\Meta\TokenDebugService();
        $res = $debug_service->debugToken($raw_token);

        if ($res['success']) {
            $data = $res['data'];
            $msg = "Token Válido: " . ($data['is_valid'] ? 'SIM' : 'NÃO') . "\n";
            $msg .= "Expira em: " . (isset($data['expires_at']) ? date('Y-m-d H:i', $data['expires_at']) : 'Nunca') . "\n";
            $msg .= "Escopos: " . implode(', ', $data['scopes'] ?? []);
            
            // Atualizar banco com infos
            $wpdb->update($table, [
                'expires_at' => isset($data['expires_at']) ? date('Y-m-d H:i:s', $data['expires_at']) : null,
                'scopes' => implode(',', $data['scopes'] ?? []),
                'last_error' => $data['is_valid'] ? '' : 'Token inválido via debug'
            ], ['id' => $id]);

            return new WP_REST_Response(['success' => true, 'message' => $msg], 200);
        }

        return new WP_REST_Response(['success' => false, 'message' => 'Erro: ' . ($res['error'] ?? 'Desconhecido')], 500);
    }

    /**
     * Update App Review Item.
     */
    public function update_review_item( $request ) {
        $params = $request->get_json_params();
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'app_review_checklist' );

        $wpdb->replace($table, [
            'meta_app_id' => intval($params['meta_app_id'] ?? 1),
            'item_key'    => sanitize_text_field($params['item_key']),
            'label'       => sanitize_text_field($params['label']),
            'status'      => sanitize_text_field($params['status']),
            'updated_at'  => current_time('mysql', true),
            'created_at'  => current_time('mysql', true),
        ]);

        return new WP_REST_Response(['success' => true], 200);
    }

	/**
	 * Check permissions for master admin.
	 */
	public function permissions_check() {
		return current_user_can( 'was_view_master_dashboard' );
	}
}

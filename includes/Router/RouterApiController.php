<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WAS\WhatsApp\WebhookProcessor;
use WAS\WhatsApp\WebhookSignatureValidator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RouterApiController {

	private $auth_service;

	public function __construct() {
		$this->auth_service = new ApiTokenService();
	}

	public function register_routes() {
		$namespace = 'was-router';

		foreach ( [ '/health', '/healthz', '/healthcheck', '/internal/health' ] as $route ) {
			register_rest_route( $namespace, $route, [
				'methods'             => 'GET',
				'callback'            => [ $this, 'health' ],
				'permission_callback' => '__return_true',
			] );
		}

		register_rest_route( $namespace, '/auth/login', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'login' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/auth/me', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'me' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/dashboard', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'dashboard' ],
			'permission_callback' => '__return_true',
		] );

		foreach ( [ 'tenants', 'meta-apps', 'wabas', 'phone-numbers' ] as $resource ) {
			register_rest_route( $namespace, '/admin-api/' . $resource, [
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'admin_list_resource' ],
					'permission_callback' => '__return_true',
					'args'                => [ 'router_resource' => [ 'default' => $resource ] ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'admin_create_resource' ],
					'permission_callback' => '__return_true',
					'args'                => [ 'router_resource' => [ 'default' => $resource ] ],
				],
			] );
		}

		foreach ( [ 'tenants', 'meta-apps', 'wabas', 'phone-numbers' ] as $resource ) {
			register_rest_route( $namespace, '/admin-api/' . $resource . '/(?P<resource_id>\d+)', [
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'admin_delete_resource' ],
				'permission_callback' => '__return_true',
				'args'                => [ 'router_resource' => [ 'default' => $resource ] ],
			] );
		}

		register_rest_route( $namespace, '/admin-api/tenants/(?P<tenant_id>\d+)/status', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'tenant_status' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/meta-apps/(?P<meta_app_id>\d+)/rotate-secret', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rotate_meta_app_secret' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/wabas/(?P<waba_id>\d+)/rotate-token', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rotate_waba_token' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/onboarding/meta/registrations', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_onboarding_registration' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/onboarding/meta/start', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'start_onboarding' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/onboarding/meta/attempts/(?P<attempt_id>[A-Za-z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'onboarding_status' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/onboarding/meta/embedded-signup', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'complete_embedded_signup' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/onboarding/bundle', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_onboarding_bundle' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/whatsapp/send', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'send_message' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/webhooks/send', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'receive_external_send_webhook' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/whatsapp/connections/(?P<connection_id>\d+)/messages', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'send_connection_message' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/whatsapp/phone-numbers/(?P<phone_number_id>\d+)/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_phone_status' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/v1/whatsapp/templates', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_templates' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'submit_template' ],
				'permission_callback' => '__return_true',
			],
		] );

		register_rest_route( $namespace, '/admin-api/routes', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_routes' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_route' ],
				'permission_callback' => '__return_true',
			],
		] );

		register_rest_route( $namespace, '/admin-api/wabas/(?P<waba_id>\d+)/sync', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'sync_waba' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/deliveries', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_deliveries' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/deliveries/(?P<delivery_id>\d+)/retry', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'retry_delivery' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/deliveries/(?P<delivery_id>\d+)/cancel', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'cancel_delivery' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/events', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_events' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/events/(?P<event_id>\d+)/replay', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'replay_event' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/audit', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_audit_logs' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/outbound-messages', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_outbound_messages' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/templates', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'admin_list_templates' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'submit_template' ],
				'permission_callback' => '__return_true',
			],
		] );

		register_rest_route( $namespace, '/admin-api/templates/sync-all', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'admin_sync_all_templates' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/wabas/(?P<waba_id>\d+)/templates/sync', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'admin_sync_waba_templates' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/templates/(?P<template_id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'admin_get_template' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => [ 'PATCH', 'PUT' ],
				'callback'            => [ $this, 'admin_update_template' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'admin_delete_template' ],
				'permission_callback' => '__return_true',
			],
		] );

		register_rest_route( $namespace, '/admin-api/phone-numbers/(?P<phone_number_id>\d+)/templates', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'admin_phone_number_templates' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/phone-numbers/(?P<phone_number_id>\d+)/templates/sync', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'admin_sync_phone_number_templates' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/admin-api/routes/(?P<route_id>\d+)', [
			[
				'methods'             => [ 'PATCH', 'PUT' ],
				'callback'            => [ $this, 'update_route' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete_route' ],
				'permission_callback' => '__return_true',
			],
		] );

		foreach ( [ 'test', 'activate', 'deactivate', 'duplicate' ] as $action ) {
			register_rest_route( $namespace, '/admin-api/routes/(?P<route_id>\d+)/' . $action, [
				'methods'             => 'POST',
				'callback'            => [ $this, 'route_action' ],
				'permission_callback' => '__return_true',
				'args'                => [ 'route_action' => [ 'default' => $action ] ],
			] );
		}

		foreach ( [ 'pause', 'resume', 'sync-status' ] as $action ) {
			register_rest_route( $namespace, '/admin-api/phone-numbers/(?P<phone_number_id>\d+)/' . $action, [
				'methods'             => 'POST',
				'callback'            => [ $this, 'phone_number_action' ],
				'permission_callback' => '__return_true',
				'args'                => [ 'phone_number_action' => [ 'default' => $action ] ],
			] );
		}

		register_rest_route( $namespace, '/webhooks/meta', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'verify_meta_webhook' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'receive_meta_webhook' ],
				'permission_callback' => '__return_true',
			],
		] );
	}

	public function health( WP_REST_Request $request ) {
		return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
	}

	public function login( WP_REST_Request $request ) {
		$params = $this->params( $request );
		$result = $this->auth_service->issue_for_credentials(
			$params['username'] ?? $params['user_login'] ?? '',
			$params['password'] ?? '',
			$params['name'] ?? 'Router API'
		);

		return $this->respond( $result );
	}

	public function me( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond(
			[
				'type'         => $actor->type ?? 'api_token',
				'user_id'      => isset( $actor->user_id ) ? (int) $actor->user_id : null,
				'name'         => $actor->name ?? null,
				'capabilities' => $actor->capabilities ?? [],
			]
		);
	}

	public function dashboard( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->dashboard() );
	}

	public function admin_list_resource( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$service = new AdminRouterService();
		$params = $this->params( $request );
		$resource = $request->get_param( 'router_resource' );
		if ( ! $resource ) {
			$resource = $params['router_resource'] ?? '';
		}

		switch ( $resource ) {
			case 'tenants':
				return $this->respond( $service->list_tenants() );
			case 'meta-apps':
				return $this->respond( $service->list_meta_apps() );
			case 'wabas':
				return $this->respond( $service->list_wabas( $params ) );
			case 'phone-numbers':
				return $this->respond( $service->list_phone_numbers( $params ) );
		}

		return $this->respond( new WP_Error( 'unknown_admin_resource', 'Recurso admin desconhecido.', [ 'status' => 404 ] ) );
	}

	public function admin_create_resource( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$service = new AdminRouterService();
		$params = $this->params( $request );
		$resource = $request->get_param( 'router_resource' );
		if ( ! $resource ) {
			$resource = $params['router_resource'] ?? '';
		}

		switch ( $resource ) {
			case 'tenants':
				return $this->respond( $service->create_tenant( $params ) );
			case 'meta-apps':
				return $this->respond( $service->create_meta_app( $params ) );
			case 'wabas':
				return $this->respond( $service->create_waba( $params ) );
			case 'phone-numbers':
				return $this->respond( $service->create_phone_number( $params ) );
		}

		return $this->respond( new WP_Error( 'unknown_admin_resource', 'Recurso admin desconhecido.', [ 'status' => 404 ] ) );
	}

	public function admin_delete_resource( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$resource = $request->get_param( 'router_resource' );
		$params = $this->params( $request );
		if ( ! $resource ) {
			$resource = $params['router_resource'] ?? '';
		}

		return $this->respond( ( new AdminRouterService() )->delete_resource( $resource, (int) $request->get_param( 'resource_id' ) ) );
	}

	public function tenant_status( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->set_tenant_status( (int) $request->get_param( 'tenant_id' ), $this->params( $request ) ) );
	}

	public function rotate_meta_app_secret( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->rotate_meta_app_secret( (int) $request->get_param( 'meta_app_id' ), $this->params( $request ) ) );
	}

	public function rotate_waba_token( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->rotate_waba_token( (int) $request->get_param( 'waba_id' ), $this->params( $request ) ) );
	}

	public function create_onboarding_registration( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new OnboardingService() )->create_registration( $this->params( $request ) ) );
	}

	public function complete_embedded_signup( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new OnboardingService() )->complete_embedded_signup( $this->params( $request ) ) );
	}

	public function start_onboarding( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}
		return $this->respond( ( new OnboardingService() )->start_embedded_signup( $this->params( $request ) ) );
	}

	public function onboarding_status( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}
		return $this->respond( ( new OnboardingService() )->get_attempt_status( (int) ( $this->params( $request )['tenant_id'] ?? 0 ), $request->get_param( 'attempt_id' ) ) );
	}

	public function create_onboarding_bundle( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new OnboardingService() )->create_bundle( $this->params( $request ) ) );
	}

	public function send_message( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new WhatsAppService() )->send_message( $this->params( $request ), $actor ) );
	}

	public function receive_external_send_webhook( WP_REST_Request $request ) {
		$expected = RouterSettings::get_external_send_webhook_secret();
		$provided = (string) ( $request->get_header( 'x-was-webhook-secret' ) ?: $request->get_header( 'x-webhook-secret' ) );

		if ( ! $expected ) {
			return $this->respond( new WP_Error( 'external_send_webhook_not_configured', 'O segredo do webhook de envio nao foi configurado.', [ 'status' => 503 ] ) );
		}
		if ( ! $provided || ! hash_equals( $expected, $provided ) ) {
			return $this->respond( new WP_Error( 'external_send_webhook_unauthorized', 'Segredo do webhook invalido.', [ 'status' => 401 ] ) );
		}

		return $this->respond( ( new ExternalSendWebhookService() )->send( $this->params( $request ) ) );
	}

	public function send_connection_message( WP_REST_Request $request ) {
		$request->set_param( 'phone_number_id', (int) $request->get_param( 'connection_id' ) );
		return $this->send_message( $request );
	}

	public function get_phone_status( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$sync = $request->get_param( 'sync' );
		return $this->respond(
			( new WhatsAppService() )->get_phone_status(
				(int) $request->get_param( 'phone_number_id' ),
				null === $sync ? true : filter_var( $sync, FILTER_VALIDATE_BOOLEAN )
			)
		);
	}

	public function list_templates( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new TemplateRouterService() )->list_templates( $this->params( $request ) ) );
	}

	public function submit_template( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new TemplateRouterService() )->submit_template( $this->params( $request ) ) );
	}

	public function create_route( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$params = $this->params( $request );
		$route = ( new AdminRouterService() )->create_route( $params );
		return $this->respond( is_wp_error( $route ) ? $route : $this->public_route( $route ) );
	}

	public function update_route( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$route = ( new AdminRouterService() )->update_route( (int) $request->get_param( 'route_id' ), $this->params( $request ) );
		return $this->respond( is_wp_error( $route ) ? $route : $this->public_route( $route ) );
	}

	public function delete_route( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$route = ( new AdminRouterService() )->delete_route( (int) $request->get_param( 'route_id' ) );
		return $this->respond( is_wp_error( $route ) ? $route : $this->public_route( $route ) );
	}

	public function list_routes( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$params = $this->params( $request );
		$routes = ( new RouteRepository() )->list(
			[
				'tenant_id'       => $params['tenant_id'] ?? null,
				'phone_number_id' => $params['phone_number_id'] ?? null,
				'is_active'       => array_key_exists( 'is_active', $params ) ? $params['is_active'] : null,
			]
		);

		return $this->respond( array_map( [ $this, 'public_route' ], $routes ) );
	}

	public function sync_waba( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new WhatsAppService() )->sync_waba_phone_numbers( (int) $request->get_param( 'waba_id' ) ) );
	}

	public function list_deliveries( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->list_deliveries( $this->params( $request ) ) );
	}

	public function retry_delivery( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->retry_delivery( (int) $request->get_param( 'delivery_id' ) ) );
	}

	public function cancel_delivery( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->cancel_delivery( (int) $request->get_param( 'delivery_id' ) ) );
	}

	public function list_events( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->list_events( $this->params( $request ) ) );
	}

	public function replay_event( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->replay_event( (int) $request->get_param( 'event_id' ) ) );
	}

	public function list_audit_logs( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->list_audit_logs( $this->params( $request ) ) );
	}

	public function list_outbound_messages( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->list_outbound_messages( $this->params( $request ) ) );
	}

	public function admin_list_templates( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->list_templates( $this->params( $request ) ) );
	}

	public function admin_get_template( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->get_template( (int) $request->get_param( 'template_id' ) ) );
	}

	public function admin_phone_number_templates( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->list_phone_number_templates( (int) $request->get_param( 'phone_number_id' ) ) );
	}

	public function admin_sync_all_templates( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->sync_all_templates( $this->params( $request ) ) );
	}

	public function admin_sync_waba_templates( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->sync_waba_templates( (int) $request->get_param( 'waba_id' ), $this->params( $request ) ) );
	}

	public function admin_sync_phone_number_templates( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->sync_phone_number_templates( (int) $request->get_param( 'phone_number_id' ), $this->params( $request ) ) );
	}

	public function admin_update_template( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->update_template( (int) $request->get_param( 'template_id' ), $this->params( $request ) ) );
	}

	public function admin_delete_template( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		return $this->respond( ( new AdminRouterService() )->delete_template( (int) $request->get_param( 'template_id' ), $this->params( $request ) ) );
	}

	public function route_action( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$service = new AdminRouterService();
		$route_id = (int) $request->get_param( 'route_id' );
		$action = $request->get_param( 'route_action' );
		if ( ! $action && preg_match( '#/(test|activate|deactivate|duplicate)$#', $request->get_route(), $matches ) ) {
			$action = $matches[1];
		}

		switch ( $action ) {
			case 'test':
				return $this->respond( $service->test_route( $route_id ) );
			case 'activate':
				return $this->respond( $service->activate_route( $route_id ) );
			case 'deactivate':
				return $this->respond( $service->deactivate_route( $route_id ) );
			case 'duplicate':
				return $this->respond( $service->duplicate_route( $route_id ) );
		}

		return $this->respond( new WP_Error( 'unknown_route_action', 'Acao de rota desconhecida.', [ 'status' => 404 ] ) );
	}

	public function phone_number_action( WP_REST_Request $request ) {
		$actor = $this->require_actor( $request );
		if ( is_wp_error( $actor ) ) {
			return $this->respond( $actor );
		}

		$service = new AdminRouterService();
		$phone_number_id = (int) $request->get_param( 'phone_number_id' );
		$action = $request->get_param( 'phone_number_action' );
		if ( ! $action && preg_match( '#/(pause|resume|sync-status)$#', $request->get_route(), $matches ) ) {
			$action = $matches[1];
		}

		switch ( $action ) {
			case 'pause':
				return $this->respond( $service->pause_phone_number( $phone_number_id ) );
			case 'resume':
				return $this->respond( $service->resume_phone_number( $phone_number_id ) );
			case 'sync-status':
				return $this->respond( $service->sync_phone_number_status( $phone_number_id ) );
		}

		return $this->respond( new WP_Error( 'unknown_phone_number_action', 'Acao de numero desconhecida.', [ 'status' => 404 ] ) );
	}

	public function verify_meta_webhook( WP_REST_Request $request ) {
		$mode = $request->get_param( 'hub_mode' ) ?: $request->get_param( 'hub.mode' );
		$token = $request->get_param( 'hub_verify_token' ) ?: $request->get_param( 'hub.verify_token' );
		$challenge = $request->get_param( 'hub_challenge' ) ?: $request->get_param( 'hub.challenge' );

		$app = ( new \WAS\Meta\MetaAppRepository() )->get_active_app_for_verify_token( $token );
		if ( 'subscribe' === $mode && $app ) {
			return new WP_REST_Response( (string) $challenge, 200 );
		}

		return new WP_REST_Response( [ 'message' => 'Forbidden' ], 403 );
	}

	public function receive_meta_webhook( WP_REST_Request $request ) {
		$raw_body = $request->get_body();
		if ( ! $raw_body ) {
			$raw_body = file_get_contents( 'php://input' );
		}

		$app = ( new MetaAppResolver() )->resolve_for_webhook_raw_body( $raw_body, true );
		if ( ! $app ) {
			return new WP_REST_Response( [ 'message' => 'App not configured' ], 500 );
		}

		$signature = $request->get_header( 'x-hub-signature-256' );
		if ( ! $signature ) {
			$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
		}

		if ( ! WebhookSignatureValidator::is_valid( $raw_body, $signature, $app->app_secret ) ) {
			return new WP_REST_Response( [ 'message' => 'Invalid signature' ], 403 );
		}

		$payload = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) ) {
			return new WP_REST_Response( [ 'message' => 'Invalid payload' ], 400 );
		}

		try {
			( new WebhookProcessor() )->process( $payload );
		} catch ( \Throwable $e ) {
			\WAS\Core\SystemLogger::logException( $e, [ 'context' => 'RouterApiController::receive_meta_webhook.local_processor' ] );
		}

		$result = ( new WebhookRouterService() )->process_meta_payload( $payload, $raw_body, true );

		return new WP_REST_Response( $result, 200 );
	}

	private function require_actor( WP_REST_Request $request ) {
		return $this->auth_service->authenticate_request( $request );
	}

	private function params( WP_REST_Request $request ) {
		$json = $request->get_json_params();
		$params = is_array( $json ) && $json ? $json : $request->get_params();
		return is_array( $params ) ? $params : [];
	}

	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 500 ) : 500;
			$data = [
				'success' => false,
				'error'   => $result->get_error_code(),
				'message' => $result->get_error_message(),
			];
			if ( is_array( $error_data ) ) {
				unset( $error_data['status'] );
				$data = array_merge( $data, $error_data );
			}
			$data['detail'] = [
				'error'   => $result->get_error_code(),
				'message' => $result->get_error_message(),
			];
			if ( 'meta_template_submission_rejected' === $result->get_error_code() ) {
				$detail_meta_response = $data['meta_response'] ?? [];
				if ( is_array( $detail_meta_response ) && isset( $detail_meta_response['meta_error'] ) ) {
					$detail_meta_response['error'] = $detail_meta_response['meta_error'];
				}
				$data['detail']['meta_response'] = $detail_meta_response;
			}
			return new WP_REST_Response( $data, $status ?: 500 );
		}

		return new WP_REST_Response( $result, 200 );
	}

	private function public_route( $route ) {
		return [
			'id'              => (int) $route->id,
			'route_id'        => (int) $route->id,
			'tenant_id'       => (int) $route->tenant_id,
			'phone_number_id' => (int) $route->phone_number_id,
			'name'            => $route->name,
			'target_url'      => $route->target_url,
			'event_filters_json' => json_decode( $route->event_filters_json ?: '{}', true ),
			'event_filters'   => json_decode( $route->event_filters_json ?: '{}', true ),
			'is_active'       => (bool) $route->is_active,
			'status'          => $route->status,
			'timeout_ms'      => (int) $route->timeout_ms,
			'max_retries'     => (int) $route->max_retries,
			'priority'        => (int) $route->priority,
		];
	}
}

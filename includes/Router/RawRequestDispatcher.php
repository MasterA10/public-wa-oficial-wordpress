<?php

namespace WAS\Router;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RawRequestDispatcher {

	public function maybe_dispatch() {
		$path = trim( (string) get_query_var( 'was_router_path' ), '/' );
		if ( ! $path ) {
			return;
		}

		$request = $this->build_request( $path );
		$response = $this->dispatch( $path, $request );
		$this->emit( $response, $path );
		exit;
	}

	private function dispatch( $path, WP_REST_Request $request ) {
		$controller = new RouterApiController();
		$method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );

		if ( in_array( $path, [ 'health', 'healthz', 'healthcheck', 'internal/health' ], true ) && 'GET' === $method ) {
			return $controller->health( $request );
		}

		if ( 'auth/login' === $path && 'POST' === $method ) {
			return $controller->login( $request );
		}

		if ( 'auth/me' === $path && 'GET' === $method ) {
			return $controller->me( $request );
		}

		if ( 'admin-api/dashboard' === $path && 'GET' === $method ) {
			return $controller->dashboard( $request );
		}

		if ( preg_match( '#^admin-api/(tenants|meta-apps|wabas|phone-numbers)$#', $path, $matches ) ) {
			$request->set_param( 'router_resource', $matches[1] );
			if ( 'GET' === $method ) {
				return $controller->admin_list_resource( $request );
			}
			if ( 'POST' === $method ) {
				return $controller->admin_create_resource( $request );
			}
		}

		if ( preg_match( '#^admin-api/(tenants|meta-apps|wabas|phone-numbers)/(\d+)$#', $path, $matches ) && 'DELETE' === $method ) {
			$request->set_param( 'router_resource', $matches[1] );
			$request->set_param( 'resource_id', (int) $matches[2] );
			return $controller->admin_delete_resource( $request );
		}

		if ( preg_match( '#^admin-api/tenants/(\d+)/status$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'tenant_id', (int) $matches[1] );
			return $controller->tenant_status( $request );
		}

		if ( preg_match( '#^admin-api/meta-apps/(\d+)/rotate-secret$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'meta_app_id', (int) $matches[1] );
			return $controller->rotate_meta_app_secret( $request );
		}

		if ( preg_match( '#^admin-api/wabas/(\d+)/rotate-token$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'waba_id', (int) $matches[1] );
			return $controller->rotate_waba_token( $request );
		}

		if ( 'v1/onboarding/meta/registrations' === $path && 'POST' === $method ) {
			return $controller->create_onboarding_registration( $request );
		}

		if ( 'v1/onboarding/meta/start' === $path && 'POST' === $method ) {
			return $controller->start_onboarding( $request );
		}

		if ( preg_match( '#^v1/onboarding/meta/attempts/([A-Za-z0-9-]+)$#', $path, $matches ) && 'GET' === $method ) {
			$request->set_param( 'attempt_id', $matches[1] );
			return $controller->onboarding_status( $request );
		}

		if ( 'v1/onboarding/meta/embedded-signup' === $path && 'POST' === $method ) {
			return $controller->complete_embedded_signup( $request );
		}

		if ( 'v1/onboarding/bundle' === $path && 'POST' === $method ) {
			return $controller->create_onboarding_bundle( $request );
		}

		if ( 'v1/whatsapp/send' === $path && 'POST' === $method ) {
			return $controller->send_message( $request );
		}

		if ( 'v1/webhooks/send' === $path && 'POST' === $method ) {
			return $controller->receive_external_send_webhook( $request );
		}

		if ( preg_match( '#^v1/whatsapp/connections/(\d+)/messages$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'connection_id', (int) $matches[1] );
			return $controller->send_connection_message( $request );
		}

		if ( preg_match( '#^v1/whatsapp/phone-numbers/(\d+)/status$#', $path, $matches ) && 'GET' === $method ) {
			$request->set_param( 'phone_number_id', (int) $matches[1] );
			return $controller->get_phone_status( $request );
		}

		if ( 'v1/whatsapp/templates' === $path && 'GET' === $method ) {
			return $controller->list_templates( $request );
		}

		if ( 'v1/whatsapp/templates' === $path && 'POST' === $method ) {
			return $controller->submit_template( $request );
		}

		if ( 'admin-api/routes' === $path && 'GET' === $method ) {
			return $controller->list_routes( $request );
		}

		if ( 'admin-api/routes' === $path && 'POST' === $method ) {
			return $controller->create_route( $request );
		}

		if ( preg_match( '#^admin-api/routes/(\d+)$#', $path, $matches ) ) {
			$request->set_param( 'route_id', (int) $matches[1] );
			if ( in_array( $method, [ 'PATCH', 'PUT' ], true ) ) {
				return $controller->update_route( $request );
			}
			if ( 'DELETE' === $method ) {
				return $controller->delete_route( $request );
			}
		}

		if ( preg_match( '#^admin-api/wabas/(\d+)/sync$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'waba_id', (int) $matches[1] );
			return $controller->sync_waba( $request );
		}

		if ( preg_match( '#^admin-api/wabas/(\d+)/templates/sync$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'waba_id', (int) $matches[1] );
			return $controller->admin_sync_waba_templates( $request );
		}

		if ( 'admin-api/deliveries' === $path && 'GET' === $method ) {
			return $controller->list_deliveries( $request );
		}

		if ( preg_match( '#^admin-api/deliveries/(\d+)/retry$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'delivery_id', (int) $matches[1] );
			return $controller->retry_delivery( $request );
		}

		if ( preg_match( '#^admin-api/deliveries/(\d+)/cancel$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'delivery_id', (int) $matches[1] );
			return $controller->cancel_delivery( $request );
		}

		if ( 'admin-api/events' === $path && 'GET' === $method ) {
			return $controller->list_events( $request );
		}

		if ( preg_match( '#^admin-api/events/(\d+)/replay$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'event_id', (int) $matches[1] );
			return $controller->replay_event( $request );
		}

		if ( 'admin-api/audit' === $path && 'GET' === $method ) {
			return $controller->list_audit_logs( $request );
		}

		if ( 'admin-api/outbound-messages' === $path && 'GET' === $method ) {
			return $controller->list_outbound_messages( $request );
		}

		if ( 'admin-api/templates' === $path && 'GET' === $method ) {
			return $controller->admin_list_templates( $request );
		}

		if ( 'admin-api/templates' === $path && 'POST' === $method ) {
			return $controller->submit_template( $request );
		}

		if ( 'admin-api/templates/sync-all' === $path && 'POST' === $method ) {
			return $controller->admin_sync_all_templates( $request );
		}

		if ( preg_match( '#^admin-api/templates/(\d+)$#', $path, $matches ) ) {
			$request->set_param( 'template_id', (int) $matches[1] );
			if ( 'GET' === $method ) {
				return $controller->admin_get_template( $request );
			}
			if ( in_array( $method, [ 'PATCH', 'PUT' ], true ) ) {
				return $controller->admin_update_template( $request );
			}
			if ( 'DELETE' === $method ) {
				return $controller->admin_delete_template( $request );
			}
		}

		if ( preg_match( '#^admin-api/phone-numbers/(\d+)/templates$#', $path, $matches ) && 'GET' === $method ) {
			$request->set_param( 'phone_number_id', (int) $matches[1] );
			return $controller->admin_phone_number_templates( $request );
		}

		if ( preg_match( '#^admin-api/phone-numbers/(\d+)/templates/sync$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'phone_number_id', (int) $matches[1] );
			return $controller->admin_sync_phone_number_templates( $request );
		}

		if ( preg_match( '#^admin-api/routes/(\d+)/(test|activate|deactivate|duplicate)$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'route_id', (int) $matches[1] );
			$request->set_param( 'route_action', $matches[2] );
			return $controller->route_action( $request );
		}

		if ( preg_match( '#^admin-api/phone-numbers/(\d+)/(pause|resume|sync-status)$#', $path, $matches ) && 'POST' === $method ) {
			$request->set_param( 'phone_number_id', (int) $matches[1] );
			$request->set_param( 'phone_number_action', $matches[2] );
			return $controller->phone_number_action( $request );
		}

		if ( in_array( $path, [ 'webhooks/meta', 'webhooks/meta/verify' ], true ) && 'GET' === $method ) {
			return $controller->verify_meta_webhook( $request );
		}

		if ( 'webhooks/meta' === $path && 'POST' === $method ) {
			return $controller->receive_meta_webhook( $request );
		}

		return new WP_REST_Response( [ 'message' => 'Not Found' ], 404 );
	}

	private function build_request( $path ) {
		$method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
		$request = new WP_REST_Request( $method, '/' . $path );
		$raw_body = file_get_contents( 'php://input' );
		$request->set_body( $raw_body ?: '' );

		foreach ( $this->headers() as $name => $value ) {
			$request->set_header( $name, $value );
		}

		$query = $_GET;
		unset( $query['was_router_path'] );
		$request->set_query_params( $query );

		if ( 'POST' === $method || 'PUT' === $method || 'PATCH' === $method || 'DELETE' === $method ) {
			$json = json_decode( $raw_body, true );
			if ( is_array( $json ) ) {
				$request->set_body_params( $json );
			} else {
				$request->set_body_params( $_POST );
			}
		}

		return $request;
	}

	private function headers() {
		if ( function_exists( 'getallheaders' ) ) {
			return getallheaders();
		}

		$headers = [];
		foreach ( $_SERVER as $name => $value ) {
			if ( 0 === strpos( $name, 'HTTP_' ) ) {
				$key = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) );
				$headers[ $key ] = $value;
			}
		}
		return $headers;
	}

	private function emit( $response, $path ) {
		if ( $response instanceof WP_Error ) {
			$error_data = $response->get_error_data();
			$status = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 500 ) : 500;
			status_header( $status );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode(
				[
					'success' => false,
					'error'   => $response->get_error_code(),
					'message' => $response->get_error_message(),
				]
			);
			return;
		}

		if ( $response instanceof WP_REST_Response ) {
			status_header( $response->get_status() );
			$data = $response->get_data();
			if ( is_string( $data ) && in_array( $path, [ 'webhooks/meta', 'webhooks/meta/verify' ], true ) ) {
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo $data;
				return;
			}

			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( $data );
			return;
		}

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $response );
	}
}

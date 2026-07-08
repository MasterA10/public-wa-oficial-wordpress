<?php

namespace WAS\Router;

use WAS\Core\TableNameResolver;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ApiTokenService {

	public function issue_for_credentials( $username, $password, $name = 'Router API' ) {
		$user = wp_authenticate( (string) $username, (string) $password );

		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'invalid_credentials', 'Usuario ou senha invalidos.', [ 'status' => 401 ] );
		}

		if ( ! user_can( $user, 'was_platform_admin' ) && ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'Usuario sem permissao para API Router.', [ 'status' => 403 ] );
		}

		return $this->issue_for_user( (int) $user->ID, $name );
	}

	public function issue_for_user( $user_id, $name = 'Router API', $expires_at = null ) {
		global $wpdb;

		$token = 'wasrt_' . bin2hex( random_bytes( 32 ) );
		$table = TableNameResolver::getRouterApiTokensTable();

		$wpdb->insert(
			$table,
			[
				'user_id'      => $user_id,
				'name'         => sanitize_text_field( $name ),
				'token_hash'   => hash( 'sha256', $token ),
				'capabilities' => wp_json_encode( [ 'router:admin', 'router:service' ] ),
				'status'       => 'active',
				'expires_at'   => $expires_at,
				'created_at'   => current_time( 'mysql', true ),
			]
		);

		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'token_create_failed', 'Nao foi possivel criar o token de API.', [ 'status' => 500 ] );
		}

		return [
			'access_token' => $token,
			'token_type'   => 'bearer',
		];
	}

	public function authenticate_request( ?WP_REST_Request $request = null ) {
		$token = $this->extract_bearer_token( $request );

		if ( ! $token ) {
			return new WP_Error( 'router_unauthorized', 'Bearer token ausente.', [ 'status' => 401 ] );
		}

		$service_secret = RouterSettings::get_service_secret();
		if ( $service_secret && hash_equals( $service_secret, $token ) ) {
			return (object) [
				'type'         => 'service',
				'user_id'      => 0,
				'name'         => 'service-secret',
				'capabilities' => [ 'router:service', 'router:admin' ],
			];
		}

		global $wpdb;
		$table = TableNameResolver::getRouterApiTokensTable();
		$hash = hash( 'sha256', $token );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE token_hash = %s AND status = 'active' LIMIT 1",
				$hash
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'router_unauthorized', 'Token invalido.', [ 'status' => 401 ] );
		}

		if ( ! empty( $row->expires_at ) && strtotime( $row->expires_at ) <= time() ) {
			return new WP_Error( 'router_unauthorized', 'Token expirado.', [ 'status' => 401 ] );
		}

		if ( $row->user_id ) {
			$user = get_user_by( 'id', (int) $row->user_id );
			if ( ! $user || ( ! user_can( $user, 'was_platform_admin' ) && ! user_can( $user, 'manage_options' ) ) ) {
				return new WP_Error( 'router_forbidden', 'Usuario do token nao possui permissao Router.', [ 'status' => 403 ] );
			}
		}

		$wpdb->update(
			$table,
			[ 'last_used_at' => current_time( 'mysql', true ) ],
			[ 'id' => (int) $row->id ]
		);

		$row->type = 'api_token';
		$row->capabilities = json_decode( $row->capabilities ?: '[]', true );
		return $row;
	}

	private function extract_bearer_token( ?WP_REST_Request $request = null ) {
		$header = '';
		if ( $request ) {
			$header = (string) $request->get_header( 'authorization' );
		}

		if ( ! $header ) {
			$header = (string) ( $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '' );
		}

		if ( stripos( $header, 'Bearer ' ) !== 0 ) {
			return '';
		}

		return trim( substr( $header, 7 ) );
	}
}
